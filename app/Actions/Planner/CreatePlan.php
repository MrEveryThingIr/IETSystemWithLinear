<?php

namespace App\Actions\Planner;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Plan;
use App\Models\PlanEvent;
use App\Models\PlanParticipant;
use App\Models\User;
use App\PlanEventType;
use App\Support\TemporalPreferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreatePlan
{
    /**
     * @param  list<array{actor: Actor, role?: string}>  $participants
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        Context $context,
        User $user,
        string $title,
        ?string $description = null,
        ?string $timezone = null,
        array $participants = [],
        ?string $originType = null,
        ?string $originUuid = null,
        array $metadata = [],
    ): Plan {
        $current = $this->currentUser($user);
        $title = Str::squish($title);
        abort_if($title === '' || mb_strlen($title) > 180, 422, 'Plan title must be between 1 and 180 characters.');

        $description = trim((string) $description);
        $description = $description === '' ? null : $description;
        abort_if($description !== null && mb_strlen($description) > 10000, 422, 'Plan description may not exceed 10000 characters.');

        $timezone ??= TemporalPreferences::timezoneFor($current);
        abort_unless(TemporalPreferences::validTimezone($timezone), 422, 'Plan timezone is invalid.');
        abort_if(count($participants) > 20, 422, 'A Plan may add at most twenty participants at creation.');

        [$originType, $originUuid] = $this->normalizeOrigin($originType, $originUuid);

        return DB::transaction(function () use (
            $context,
            $current,
            $title,
            $description,
            $timezone,
            $participants,
            $originType,
            $originUuid,
            $metadata,
        ): Plan {
            $lockedContext = Context::query()->lockForUpdate()->findOrFail($context->id);

            abort_unless(in_array($lockedContext->kind, [
                ContextKind::Personal,
                ContextKind::GroupSpace,
                ContextKind::Relationship,
            ], true), 422, 'Planner is not enabled for this Context kind.');

            Gate::forUser($current)->authorize('interactContent', $lockedContext);

            $creator = Actor::query()->with('user')->lockForUpdate()->findOrFail($current->actor->id);
            $participantSpecs = [];
            $seen = [$creator->id => true];

            foreach ($participants as $spec) {
                $actor = $spec['actor'];
                $participant = Actor::query()->with('user')->lockForUpdate()->findOrFail($actor->id);

                abort_if(isset($seen[$participant->id]), 422, 'A Plan cannot contain the same participant twice.');
                abort_unless(
                    $participant->status === 'active'
                    && $participant->user instanceof User
                    && $participant->user->status === 'active'
                    && $participant->user->email_verified_at !== null
                    && Gate::forUser($participant->user)->allows('view', $lockedContext),
                    422,
                    'Plan participants must be active verified Actors who can view this Context.',
                );

                $seen[$participant->id] = true;
                $participantSpecs[] = [
                    'actor' => $participant,
                    'role' => $this->normalizeRole($spec['role'] ?? 'participant'),
                ];
            }

            $plan = Plan::query()->create([
                'context_id' => $lockedContext->id,
                'created_by_actor_id' => $creator->id,
                'title' => $title,
                'description' => $description,
                'timezone' => $timezone,
                'origin_type' => $originType,
                'origin_uuid' => $originUuid,
                'metadata' => $metadata,
            ]);

            PlanParticipant::query()->create([
                'plan_id' => $plan->id,
                'actor_id' => $creator->id,
                'assigned_by_actor_id' => $creator->id,
                'role' => 'owner',
                'status' => 'active',
            ]);

            foreach ($participantSpecs as $spec) {
                PlanParticipant::query()->create([
                    'plan_id' => $plan->id,
                    'actor_id' => $spec['actor']->id,
                    'assigned_by_actor_id' => $creator->id,
                    'role' => $spec['role'],
                    'status' => 'active',
                ]);
            }

            PlanEvent::query()->create([
                'plan_id' => $plan->id,
                'actor_id' => $creator->id,
                'event_type' => PlanEventType::Created,
                'payload' => [
                    'participant_actor_ids' => collect($participantSpecs)->pluck('actor.id')->values()->all(),
                    'origin_type' => $originType,
                    'origin_uuid' => $originUuid,
                ],
            ]);

            return $plan->fresh([
                'context',
                'creator.user',
                'participants.actor.user',
                'events',
            ]);
        }, attempts: 3);
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }

    private function normalizeRole(string $role): string
    {
        $role = Str::squish($role);
        abort_if($role === '' || mb_strlen($role) > 80, 422, 'Plan participant role must be between 1 and 80 characters.');

        return $role;
    }

    /** @return array{?string, ?string} */
    private function normalizeOrigin(?string $type, ?string $uuid): array
    {
        $type = Str::squish((string) $type);
        $uuid = trim((string) $uuid);

        if ($type === '' && $uuid === '') {
            return [null, null];
        }

        abort_if($type === '' || mb_strlen($type) > 80, 422, 'Plan origin type is invalid.');
        abort_unless(Str::isUuid($uuid), 422, 'Plan origin UUID is invalid.');

        return [$type, $uuid];
    }
}
