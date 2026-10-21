<?php

namespace App\Actions\Planner\Meetings;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Models\Actor;
use App\Models\Plan;
use App\Models\Planner\MeetingInvitee;
use App\Models\Planner\MeetingRequest;
use App\Models\Planner\MeetingSlot;
use App\Models\Planner\MeetingVote;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\Support\NotificationOutboxWriter;
use App\Support\Planner\PlanKinds;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Finding a time with other people.
 *
 * 1. The organizer offers one or more time options and invites users (by
 *    username) and/or guests (a private link, name and optional email).
 * 2. Each invitee answers yes / maybe / no per option, may suggest another
 *    time, or declines. With a single invitee, a "yes" to exactly one of the
 *    organizer's options agrees the meeting at once.
 * 3. Otherwise the organizer picks the time. Then an ordinary Plan is created
 *    in the organizer's planner and in every user invitee's planner, each in
 *    their own timezone. Guests get a calendar file; if they register (or
 *    sign in) and open their link, the meeting is added to their planner.
 */
class MeetingScheduler
{
    public const MAX_SLOTS = 12;

    public const MAX_INVITEES = 20;

    public function __construct(
        private readonly CreatePlan $createPlan,
        private readonly CreatePlanScheduleRule $createRule,
        private readonly EnsurePersonalContext $personal,
        private readonly NotificationOutboxWriter $outbox,
    ) {}

    /**
     * @param  array{title: string, description?: ?string, location?: ?string, duration_minutes: int, timezone: string, label_uuid?: ?string}  $details
     * @param  list<CarbonImmutable>  $starts  option start times (any timezone)
     * @param  list<array{username?: string, name?: string, email?: ?string}>  $invitees
     * @return array{meeting: MeetingRequest, links: array<string, string>}
     */
    public function create(User $organizer, array $details, array $starts, array $invitees): array
    {
        $actor = $this->actor($organizer);
        $title = Str::squish($details['title']);
        $duration = (int) $details['duration_minutes'];
        $this->ensure($title !== '' && mb_strlen($title) <= 180, 'title', 'planner.meetings.errors.title');
        $this->ensure($duration >= 5 && $duration <= 1440, 'duration', 'planner.meetings.errors.duration');
        $this->ensure(TemporalPreferences::validTimezone($details['timezone']), 'timezone', 'planner.meetings.errors.timezone');
        $this->ensure($starts !== [] && count($starts) <= self::MAX_SLOTS, 'slots', 'planner.meetings.errors.slots');
        $this->ensure($invitees !== [] && count($invitees) <= self::MAX_INVITEES, 'invitees', 'planner.meetings.errors.invitees');

        $now = CarbonImmutable::now('UTC');
        $starts = collect($starts)->map(fn (CarbonImmutable $start): CarbonImmutable => $start->utc()->startOfMinute())
            ->unique(fn (CarbonImmutable $start): int => $start->getTimestamp())->sort()->values();
        $this->ensure($starts->every(fn (CarbonImmutable $start): bool => $start->gt($now)), 'slots', 'planner.meetings.errors.past');

        $resolved = [];
        foreach ($invitees as $invitee) {
            $username = trim((string) ($invitee['username'] ?? ''));
            if ($username !== '') {
                $user = User::query()->with('actor')->where('username', ltrim($username, '@'))->first();
                $this->ensure($user instanceof User && $user->actor instanceof Actor && $user->status === 'active', 'invitees', 'planner.meetings.errors.unknown_user', ['username' => $username]);
                $this->ensure((int) $user->actor->id !== (int) $actor->id, 'invitees', 'planner.meetings.errors.self');
                $resolved['actor:'.$user->actor->id] = ['actor' => $user->actor, 'name' => $user->username, 'email' => null];

                continue;
            }

            $name = Str::squish((string) ($invitee['name'] ?? ''));
            $email = trim((string) ($invitee['email'] ?? ''));
            $this->ensure($name !== '' && mb_strlen($name) <= 120, 'invitees', 'planner.meetings.errors.guest_name');
            $this->ensure($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'invitees', 'planner.meetings.errors.guest_email');
            $resolved['guest:'.count($resolved)] = ['actor' => null, 'name' => $name, 'email' => $email !== '' ? mb_strtolower($email) : null];
        }

        return DB::transaction(function () use ($actor, $details, $title, $duration, $starts, $resolved): array {
            $meeting = MeetingRequest::query()->create([
                'organizer_actor_id' => $actor->id,
                'title' => $title,
                'description' => $this->text($details['description'] ?? null, 5000),
                'location' => $this->text($details['location'] ?? null, 300),
                'duration_minutes' => $duration,
                'timezone' => $details['timezone'],
                'status' => MeetingRequest::STATUS_OPEN,
                'label_uuid' => $details['label_uuid'] ?? null,
            ]);

            foreach ($starts as $start) {
                $meeting->slots()->create([
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($duration),
                    'status' => MeetingSlot::STATUS_OFFERED,
                ]);
            }

            $links = [];
            foreach ($resolved as $spec) {
                $token = $spec['actor'] === null ? Str::random(40) : null;
                $invitee = $meeting->invitees()->create([
                    'actor_id' => $spec['actor']?->id,
                    'name' => $spec['name'],
                    'email' => $spec['email'],
                    'token_hash' => $token !== null ? MeetingInvitee::hashToken($token) : null,
                    'token_encrypted' => $token !== null ? Crypt::encryptString($token) : null,
                    'status' => MeetingInvitee::STATUS_PENDING,
                ]);

                if ($token !== null) {
                    $links[$invitee->uuid] = route('meetings.guest', $token);
                } else {
                    $this->notify($spec['actor'], $meeting, 'invited', ['organizer' => $this->displayName($actor), 'title' => $title]);
                }
            }

            return ['meeting' => $meeting->fresh(['slots', 'invitees']), 'links' => $links];
        }, 3);
    }

    /**
     * An invitee's answer. $votes maps slot uuid => yes|maybe|no. $suggest
     * proposes another start time (in UTC or any timezone).
     */
    public function respond(MeetingInvitee $invitee, array $votes, ?CarbonImmutable $suggest = null, ?string $note = null, bool $decline = false): MeetingRequest
    {
        return DB::transaction(function () use ($invitee, $votes, $suggest, $note, $decline): MeetingRequest {
            $invitee = MeetingInvitee::query()->lockForUpdate()->findOrFail($invitee->id);
            $meeting = MeetingRequest::query()->with('slots')->lockForUpdate()->findOrFail($invitee->meeting_request_id);
            $this->ensure($meeting->isOpen(), 'meeting', 'planner.meetings.errors.closed');

            if ($decline) {
                $invitee->update(['status' => MeetingInvitee::STATUS_DECLINED, 'note' => $this->text($note, 1000), 'responded_at' => now()]);
                $this->notifyOrganizer($meeting, $invitee, 'declined');

                return $meeting->fresh();
            }

            $slots = $meeting->slots->where('status', MeetingSlot::STATUS_OFFERED)->keyBy('uuid');
            foreach ($votes as $slotUuid => $answer) {
                $slot = $slots->get((string) $slotUuid);
                if (! $slot instanceof MeetingSlot || ! in_array($answer, MeetingVote::ANSWERS, true)) {
                    continue;
                }
                MeetingVote::query()->updateOrCreate(
                    ['meeting_slot_id' => $slot->id, 'meeting_invitee_id' => $invitee->id],
                    ['answer' => $answer],
                );
            }

            if ($suggest instanceof CarbonImmutable) {
                $start = $suggest->utc()->startOfMinute();
                $this->ensure($start->isFuture(), 'suggest', 'planner.meetings.errors.past');
                $this->ensure($slots->count() < self::MAX_SLOTS * 2, 'suggest', 'planner.meetings.errors.slots');
                $slot = $meeting->slots()->create([
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($meeting->duration_minutes),
                    'suggested_by_invitee_id' => $invitee->id,
                    'status' => MeetingSlot::STATUS_OFFERED,
                ]);
                MeetingVote::query()->create(['meeting_slot_id' => $slot->id, 'meeting_invitee_id' => $invitee->id, 'answer' => 'yes']);
            }

            $invitee->update(['status' => MeetingInvitee::STATUS_RESPONDED, 'note' => $this->text($note, 1000) ?? $invitee->note, 'responded_at' => now()]);

            // One person, one clear "yes" to one of the organizer's own options: agreed.
            $meeting->load('invitees');
            if ($meeting->invitees->count() === 1 && ! $suggest instanceof CarbonImmutable) {
                $yes = MeetingVote::query()->where('meeting_invitee_id', $invitee->id)->where('answer', 'yes')
                    ->whereIn('meeting_slot_id', $meeting->slots()->whereNull('suggested_by_invitee_id')->where('status', MeetingSlot::STATUS_OFFERED)->pluck('id'))
                    ->pluck('meeting_slot_id');
                if ($yes->count() === 1) {
                    return $this->agree($meeting, MeetingSlot::query()->findOrFail($yes->first()));
                }
            }

            $this->notifyOrganizer($meeting, $invitee, $suggest instanceof CarbonImmutable ? 'suggested' : 'answered');

            return $meeting->fresh();
        }, 3);
    }

    /** The organizer picks the time. */
    public function finalize(MeetingRequest $meeting, User $organizer, string $slotUuid): MeetingRequest
    {
        $this->ensureOrganizer($meeting, $organizer);

        return DB::transaction(function () use ($meeting, $slotUuid): MeetingRequest {
            $meeting = MeetingRequest::query()->lockForUpdate()->findOrFail($meeting->id);
            $this->ensure($meeting->isOpen(), 'meeting', 'planner.meetings.errors.closed');
            $slot = $meeting->slots()->where('uuid', $slotUuid)->where('status', MeetingSlot::STATUS_OFFERED)->first();
            $this->ensure($slot instanceof MeetingSlot, 'slot', 'planner.meetings.errors.slot');

            return $this->agree($meeting, $slot);
        }, 3);
    }

    public function cancel(MeetingRequest $meeting, User $organizer): MeetingRequest
    {
        $this->ensureOrganizer($meeting, $organizer);
        $this->ensure($meeting->isOpen(), 'meeting', 'planner.meetings.errors.closed');
        $meeting->update(['status' => MeetingRequest::STATUS_CANCELLED, 'cancelled_at' => now()]);

        foreach ($meeting->invitees()->whereNotNull('actor_id')->with('actor')->get() as $invitee) {
            $this->notify($invitee->actor, $meeting, 'cancelled', ['organizer' => $this->displayName($meeting->organizer), 'title' => $meeting->title]);
        }

        return $meeting->fresh();
    }

    /**
     * A guest who now has an account takes over their invitation. If the time
     * was already agreed, the meeting is added to their planner.
     */
    public function claim(MeetingInvitee $invitee, User $user): MeetingInvitee
    {
        $actor = $this->actor($user);

        return DB::transaction(function () use ($invitee, $actor, $user): MeetingInvitee {
            $invitee = MeetingInvitee::query()->lockForUpdate()->findOrFail($invitee->id);
            $meeting = MeetingRequest::query()->with('finalSlot')->findOrFail($invitee->meeting_request_id);

            if ((int) $meeting->organizer_actor_id === (int) $actor->id) {
                return $invitee;
            }
            if ($invitee->actor_id !== null) {
                $this->ensure((int) $invitee->actor_id === (int) $actor->id, 'invitee', 'planner.meetings.errors.claimed');
            } else {
                $this->ensure(
                    ! MeetingInvitee::query()->where('meeting_request_id', $meeting->id)->where('actor_id', $actor->id)->exists(),
                    'invitee',
                    'planner.meetings.errors.claimed',
                );
                $invitee->update(['actor_id' => $actor->id, 'claimed_at' => now()]);
            }

            if ($meeting->status === MeetingRequest::STATUS_AGREED
                && $meeting->finalSlot instanceof MeetingSlot
                && $invitee->status !== MeetingInvitee::STATUS_DECLINED
                && $invitee->plan_id === null) {
                $plan = $this->planFor($user, $meeting, $meeting->finalSlot, $this->displayName($meeting->organizer), null);
                $invitee->update(['plan_id' => $plan?->id]);
            }

            return $invitee->fresh();
        }, 3);
    }

    /** Invitations sent to this email before the person had an account. */
    public function claimByEmail(User $user): int
    {
        if ($user->email_verified_at === null) {
            return 0;
        }

        $claimed = 0;
        $pending = MeetingInvitee::query()
            ->whereNull('actor_id')
            ->where('email', mb_strtolower((string) $user->email))
            ->whereHas('meeting', fn ($query) => $query->whereIn('status', [MeetingRequest::STATUS_OPEN, MeetingRequest::STATUS_AGREED]))
            ->get();

        foreach ($pending as $invitee) {
            try {
                $this->claim($invitee, $user);
                $claimed++;
            } catch (Throwable $exception) {
                Log::info('Meeting invitation could not be claimed.', ['invitee' => $invitee->uuid, 'error' => $exception->getMessage()]);
            }
        }

        return $claimed;
    }

    /** iCalendar file for an agreed meeting (guests add it to their own calendar). */
    public function ics(MeetingRequest $meeting): string
    {
        $slot = $meeting->finalSlot;
        abort_unless($slot instanceof MeetingSlot, 404);
        $escape = fn (?string $value): string => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], (string) $value);

        return implode("\r\n", array_filter([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.config('app.name').'//Planner//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$meeting->uuid.'@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART:'.$slot->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$slot->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$escape($meeting->title),
            $meeting->location ? 'LOCATION:'.$escape($meeting->location) : null,
            $meeting->description ? 'DESCRIPTION:'.$escape($meeting->description) : null,
            'END:VEVENT',
            'END:VCALENDAR',
        ]))."\r\n";
    }

    private function agree(MeetingRequest $meeting, MeetingSlot $slot): MeetingRequest
    {
        $meeting->loadMissing(['organizer.user', 'invitees.actor.user']);
        $withWhom = $meeting->invitees->where('status', '!=', MeetingInvitee::STATUS_DECLINED)->pluck('name')->implode(', ');

        $organizerUser = $meeting->organizer->user;
        $organizerPlan = $organizerUser instanceof User ? $this->planFor($organizerUser, $meeting, $slot, $withWhom, $meeting->label_uuid) : null;

        $meeting->update([
            'status' => MeetingRequest::STATUS_AGREED,
            'final_slot_id' => $slot->id,
            'organizer_plan_id' => $organizerPlan?->id,
            'finalized_at' => now(),
        ]);
        $meeting->slots()->where('id', '!=', $slot->id)->update(['status' => MeetingSlot::STATUS_WITHDRAWN]);

        foreach ($meeting->invitees as $invitee) {
            $user = $invitee->actor?->user;
            if (! $user instanceof User || $invitee->status === MeetingInvitee::STATUS_DECLINED) {
                continue;
            }
            $plan = $this->planFor($user, $meeting, $slot, $this->displayName($meeting->organizer), null);
            $invitee->update(['plan_id' => $plan?->id]);
            $this->notify($invitee->actor, $meeting, 'agreed', ['organizer' => $this->displayName($meeting->organizer), 'title' => $meeting->title]);
        }

        if ($meeting->organizer instanceof Actor) {
            $this->notify($meeting->organizer, $meeting, 'agreed_organizer', ['title' => $meeting->title, 'people' => $withWhom]);
        }

        return $meeting->fresh(['finalSlot', 'invitees']);
    }

    private function planFor(User $user, MeetingRequest $meeting, MeetingSlot $slot, string $withWhom, ?string $labelUuid): ?Plan
    {
        try {
            $timezone = TemporalPreferences::timezoneFor($user);
            $start = $slot->starts_at->setTimezone($timezone);
            $description = trim(implode("\n\n", array_filter([
                (string) __('planner.meetings.plan_with', ['people' => $withWhom], $user->locale),
                $meeting->location ? (string) __('planner.meetings.plan_where', ['place' => $meeting->location], $user->locale) : null,
                $meeting->description,
            ])));

            $plan = $this->createPlan->execute(
                $this->personal->execute($user),
                $user,
                $meeting->title,
                $description,
                $timezone,
                metadata: array_filter([
                    'kind' => 'meeting',
                    'meeting_uuid' => $meeting->uuid,
                    'label_uuid' => $labelUuid,
                ]),
            );
            $this->createRule->execute(
                $plan,
                $user,
                PlanScheduleFrequency::Once,
                $start->toDateString(),
                $start->format('H:i'),
                max(1, (int) $slot->starts_at->diffInMinutes($slot->ends_at)),
                reminderOffsets: [PlanKinds::KINDS['meeting']['reminder']],
            );

            return $plan;
        } catch (Throwable $exception) {
            Log::warning('Meeting plan could not be created.', ['meeting' => $meeting->uuid, 'user' => $user->id, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    private function notifyOrganizer(MeetingRequest $meeting, MeetingInvitee $invitee, string $event): void
    {
        $meeting->loadMissing('organizer');
        if ($meeting->organizer instanceof Actor) {
            $this->notify($meeting->organizer, $meeting, $event, ['name' => $invitee->name, 'title' => $meeting->title]);
        }
    }

    /** @param array<string, string> $params */
    private function notify(?Actor $actor, MeetingRequest $meeting, string $event, array $params): void
    {
        $user = $actor?->user;
        if (! $user instanceof User || $user->status !== 'active' || $user->email_verified_at === null) {
            return;
        }

        try {
            $this->outbox->request(
                $user,
                'meeting:'.$event.':'.$meeting->uuid.':'.$user->id.':'.now()->timestamp,
                'planner.meeting',
                [
                    'title_key' => 'planner.meetings.notifications.'.$event.'_title',
                    'title_params' => $params,
                    'body_key' => 'planner.meetings.notifications.'.$event.'_body',
                    'body_params' => $params,
                    'url' => route('planner.meetings.show', $meeting),
                ],
                null,
                'meeting_request',
                $meeting->uuid,
            );
        } catch (Throwable $exception) {
            Log::warning('Meeting notification could not be queued.', ['event' => $event, 'error' => $exception->getMessage()]);
        }
    }

    private function ensureOrganizer(MeetingRequest $meeting, User $user): void
    {
        abort_unless((int) $meeting->organizer_actor_id === (int) $this->actor($user)->id, 403);
    }

    private function actor(User $user): Actor
    {
        $actor = User::query()->with('actor')->findOrFail($user->id)->actor;
        abort_unless($actor instanceof Actor, 403);

        return $actor;
    }

    private function displayName(?Actor $actor): string
    {
        $actor?->loadMissing(['user', 'profile']);

        return (string) ($actor?->profile?->display_name ?: $actor?->user?->username ?: '—');
    }

    private function text(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** @param array<string, string> $params */
    private function ensure(bool $condition, string $field, string $key, array $params = []): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => __($key, $params)]);
        }
    }
}
