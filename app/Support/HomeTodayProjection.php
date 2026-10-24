<?php

namespace App\Support;

use App\ContractStatus;
use App\Enums\Bookkeeping\LedgerKind;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Bookkeeping\Transaction as BookTransaction;
use App\Models\Contract;
use App\Models\ContractContext;
use App\Models\Fulfillment;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\PersonalContext;
use App\Models\Plan;
use App\Models\Planner\MeetingInvitee;
use App\Models\Planner\MeetingRequest;
use App\Models\PlanOccurrence;
use App\Models\Proposal;
use App\Models\ProposalContext;
use App\Models\Relationship;
use App\Models\RelationshipContext;
use App\Models\RelationshipParticipant;
use App\Models\Submission;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\ProfileIntentStatus;
use App\ProposalStatus;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use App\Support\Bookkeeping\BookkeepingOverview;
use App\Support\Bookkeeping\Toman;
use App\Support\Planner\OccurrenceTiming;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class HomeTodayProjection
{
    /** How many of today's occurrences are scanned for the projection. */
    public const OCCURRENCE_SCAN_LIMIT = 250;

    /** How many schedule rows Today renders directly; the rest are summarized. */
    public const SCHEDULE_PREVIEW_LIMIT = 6;

    public function __construct(
        private readonly ContextTimeline $timeline,
    ) {}

    /**
     * @return array{
     *   timezone: string,
     *   today: string,
     *   todayDisplay: string,
     *   money: array{owed_to_me: int, i_owe: int, waiting_for_me: Collection<int, BookTransaction>, today_income: int, today_expense: int, has_books: bool},
     *   todayOccurrences: Collection<int, PlanOccurrence>,
     *   todayOccurrenceCount: int,
     *   todayOccurrencesTruncated: bool,
     *   todayOccurrencePreview: Collection<int, PlanOccurrence>,
     *   nextOccurrence: ?PlanOccurrence,
     *   inProgressOccurrences: Collection<int, PlanOccurrence>,
     *   followUps: Collection<int, PlanOccurrence>,
     *   followUpCount: int,
     *   reportStats: array{told: int, total: int},
     *   changingHabits: Collection<int, Plan>,
     *   waitingOnMe: Collection<int, HomeActionItem>,
     *   waitingOnOthers: Collection<int, HomeActionItem>,
     *   activeIntents: Collection<int, ActorProfileIntent>,
     *   activeRelationships: Collection<int, Relationship>,
     *   groupMemberships: Collection<int, GroupMembership>
     * }
     */
    public function build(User $user): array
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User, 403);

        $timezone = TemporalPreferences::timezoneFor($current);
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();
        $todayDisplay = TemporalCalendar::dateLabel($now, $current, $timezone);

        if (! $current->actor instanceof Actor) {
            return $this->emptyProjection($timezone, $today, $todayDisplay);
        }

        $actor = $current->actor;

        $fetchedOccurrences = PlanOccurrence::query()
            ->with(['plan.context', 'plan.participants.actor.user'])
            ->whereBetween('scheduled_start_at', [
                $now->startOfDay()->utc(),
                $now->endOfDay()->utc(),
            ])
            ->orderBy('scheduled_start_at')
            ->limit(self::OCCURRENCE_SCAN_LIMIT)
            ->get();
        $todayOccurrences = $fetchedOccurrences
            ->filter(fn (PlanOccurrence $occurrence): bool => Gate::forUser($current)->allows('participate', $occurrence->plan))
            ->values();
        $schedule = $this->scheduleSummary($todayOccurrences, $now);

        $activeIntents = ActorProfileIntent::query()
            ->where('status', ProfileIntentStatus::Active->value)
            ->whereHas('profile', fn ($query) => $query->where('actor_id', $actor->id))
            ->with(['concept.labels', 'profile'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $activeRelationships = Relationship::query()
            ->where('status', RelationshipStatus::Active->value)
            ->whereHas('participants', fn ($query) => $query
                ->where('actor_id', $actor->id)
                ->where('status', RelationshipParticipantStatus::Active->value))
            ->with(['purposeConcept.labels', 'participants.actor.user'])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->filter(fn (Relationship $relationship): bool => Gate::forUser($current)->allows('view', $relationship))
            ->values();

        $groupMemberships = GroupMembership::query()
            ->where('actor_id', $actor->id)
            ->where('status', 'active')
            ->with('group')
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->filter(fn (GroupMembership $membership): bool => Gate::forUser($current)->allows('view', $membership->group))
            ->values();

        $money = app(BookkeepingOverview::class)->forUser($current, $today);

        return [
            'timezone' => $timezone,
            'today' => $today,
            'todayDisplay' => $todayDisplay,
            'money' => $money,
            'dontForget' => $this->dontForget($current, $now),
            ...$this->followUps($current, $actor, $now),
            'todayOccurrences' => $todayOccurrences,
            'todayOccurrenceCount' => $todayOccurrences->count(),
            'todayOccurrencesTruncated' => $fetchedOccurrences->count() >= self::OCCURRENCE_SCAN_LIMIT,
            ...$schedule,
            'waitingOnMe' => $this->waitingOnMe($current, $actor)
                ->concat($this->bookkeepingWaiting($money['waiting_for_me']))
                ->concat($this->meetingsWaiting($actor))
                ->sortByDesc(fn (HomeActionItem $item): int => $item->occurredAt->getTimestamp())
                ->take(20)
                ->values(),
            'waitingOnOthers' => $this->waitingOnOthers($current, $actor),
            'activeIntents' => $activeIntents,
            'activeRelationships' => $activeRelationships,
            'groupMemberships' => $groupMemberships,
        ];
    }

    /**
     * Today shows a bounded window of the schedule: whatever is running now,
     * then what comes next, backfilled with the most recent earlier items.
     *
     * @param  Collection<int, PlanOccurrence>  $occurrences
     * @return array{
     *   todayOccurrencePreview: Collection<int, PlanOccurrence>,
     *   nextOccurrence: ?PlanOccurrence,
     *   inProgressOccurrences: Collection<int, PlanOccurrence>
     * }
     */
    private function scheduleSummary(Collection $occurrences, CarbonImmutable $now): array
    {
        $inProgress = $occurrences
            ->filter(fn (PlanOccurrence $occurrence): bool => $occurrence->scheduled_start_at->lte($now)
                && $occurrence->scheduled_end_at->gt($now))
            ->values();
        $upcoming = $occurrences
            ->filter(fn (PlanOccurrence $occurrence): bool => $occurrence->scheduled_start_at->gt($now))
            ->values();
        $earlier = $occurrences
            ->reject(fn (PlanOccurrence $occurrence): bool => $inProgress->contains($occurrence) || $upcoming->contains($occurrence))
            ->values();

        $preview = $inProgress->concat($upcoming)->take(self::SCHEDULE_PREVIEW_LIMIT);
        $room = self::SCHEDULE_PREVIEW_LIMIT - $preview->count();
        if ($room > 0) {
            $preview = $earlier->slice(-$room)->concat($preview);
        }

        return [
            'todayOccurrencePreview' => $preview
                ->sortBy(fn (PlanOccurrence $occurrence): int => $occurrence->scheduled_start_at->getTimestamp())
                ->values(),
            'nextOccurrence' => $upcoming->first(),
            'inProgressOccurrences' => $inProgress,
        ];
    }

    /**
     * @return array{
     *   timezone: string,
     *   today: string,
     *   todayDisplay: string,
     *   money: array{owed_to_me: int, i_owe: int, waiting_for_me: Collection<int, BookTransaction>, today_income: int, today_expense: int, has_books: bool},
     *   todayOccurrences: Collection<int, PlanOccurrence>,
     *   todayOccurrenceCount: int,
     *   todayOccurrencesTruncated: bool,
     *   todayOccurrencePreview: Collection<int, PlanOccurrence>,
     *   nextOccurrence: ?PlanOccurrence,
     *   inProgressOccurrences: Collection<int, PlanOccurrence>,
     *   followUps: Collection<int, PlanOccurrence>,
     *   followUpCount: int,
     *   reportStats: array{told: int, total: int},
     *   changingHabits: Collection<int, Plan>,
     *   waitingOnMe: Collection<int, HomeActionItem>,
     *   waitingOnOthers: Collection<int, HomeActionItem>,
     *   activeIntents: Collection<int, ActorProfileIntent>,
     *   activeRelationships: Collection<int, Relationship>,
     *   groupMemberships: Collection<int, GroupMembership>
     * }
     */
    private function emptyProjection(string $timezone, string $today, string $todayDisplay): array
    {
        return [
            'timezone' => $timezone,
            'today' => $today,
            'todayDisplay' => $todayDisplay,
            'money' => ['owed_to_me' => 0, 'i_owe' => 0, 'waiting_for_me' => collect(), 'today_income' => 0, 'today_expense' => 0, 'has_books' => false],
            'dontForget' => collect(),
            'followUps' => collect(),
            'followUpCount' => 0,
            'reportStats' => ['told' => 0, 'total' => 0],
            'changingHabits' => collect(),
            'todayOccurrences' => collect(),
            'todayOccurrenceCount' => 0,
            'todayOccurrencesTruncated' => false,
            'todayOccurrencePreview' => collect(),
            'nextOccurrence' => null,
            'inProgressOccurrences' => collect(),
            'waitingOnMe' => collect(),
            'waitingOnOthers' => collect(),
            'activeIntents' => collect(),
            'activeRelationships' => collect(),
            'groupMemberships' => collect(),
        ];
    }

    /** @return Collection<int, HomeActionItem> */
    /**
     * Bookkeeping entries other people recorded that wait for this person's
     * confirmation ("Majid recorded a 10,000,000 payment — please confirm").
     *
     * @param  Collection<int, BookTransaction>  $entries
     * @return Collection<int, HomeActionItem>
     */
    private function bookkeepingWaiting(Collection $entries): Collection
    {
        return $entries->map(fn (BookTransaction $entry): HomeActionItem => new HomeActionItem(
            key: 'bookkeeping:'.$entry->uuid,
            kind: 'money',
            title: (string) __('bookkeeping.today.waiting_title', [
                'who' => $entry->recorder->user->username ?? '',
                'type' => __('bookkeeping.types.'.$entry->type->value),
                'amount' => Toman::format((int) $entry->amount),
            ]),
            summary: $entry->ledger->kind === LedgerKind::Personal ? null : $entry->ledger->name,
            url: route('bookkeeping.books.show', $entry->ledger).'#entry-'.$entry->uuid,
            occurredAt: $entry->created_at ?? now(),
        ))->values();
    }

    /**
     * Plans marked important (installments, cheques, bills) that are due in
     * the next two weeks or overdue and not done yet: they stay here until
     * the person handles them. Only the nearest open one of each plan, and
     * only while it can still be done (a past routine is asked about under
     * "How did it go?" instead).
     *
     * @return Collection<int, PlanOccurrence>
     */
    private function dontForget(User $user, CarbonImmutable $now): Collection
    {
        return PlanOccurrence::query()
            ->with(['plan', 'scheduleRule'])
            ->whereIn('status', [PlanOccurrenceStatus::Scheduled->value, PlanOccurrenceStatus::InProgress->value])
            ->where('scheduled_start_at', '<=', $now->addDays(14)->utc())
            ->where('scheduled_start_at', '>=', $now->subDays(60)->utc())
            ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>=', $now->utc()))
            ->whereHas('plan', fn ($query) => $query->where('status', 'active'))
            ->orderBy('scheduled_start_at')
            ->limit(500)
            ->get()
            ->filter(fn (PlanOccurrence $occurrence): bool => (bool) data_get($occurrence->plan->metadata, 'important')
                && Gate::forUser($user)->allows('participate', $occurrence->plan))
            ->unique('plan_id')
            ->take(8)
            ->values();
    }

    /**
     * Past occurrences nobody has told about yet (the last week), so the
     * person can say how they went in one tap; and how many recent ones were
     * told about, said kindly. Also the habits being replaced, for the urge
     * buttons.
     *
     * @return array{followUps: Collection<int, PlanOccurrence>, followUpCount: int, reportStats: array{told: int, total: int}, changingHabits: Collection<int, Plan>}
     */
    private function followUps(User $user, Actor $actor, CarbonImmutable $now): array
    {
        $waiting = PlanOccurrence::query()
            ->with(['plan', 'scheduleRule'])
            ->whereIn('status', [PlanOccurrenceStatus::Scheduled->value, PlanOccurrenceStatus::InProgress->value])
            ->whereBetween('closes_at', [$now->subDays(OccurrenceTiming::REPORT_DAYS)->utc(), $now->utc()])
            ->whereHas('plan', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->orderByDesc('closes_at')
            ->limit(200)
            ->get()
            ->filter(fn (PlanOccurrence $occurrence): bool => Gate::forUser($user)->allows('participate', $occurrence->plan))
            ->values();

        $recent = PlanOccurrence::query()
            ->whereBetween('closes_at', [$now->subDays(14)->utc(), $now->utc()])
            ->whereIn('status', [PlanOccurrenceStatus::Scheduled->value, PlanOccurrenceStatus::InProgress->value, PlanOccurrenceStatus::Completed->value, PlanOccurrenceStatus::Skipped->value])
            ->whereHas('plan', fn ($query) => $query->where('created_by_actor_id', $actor->id))
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as told', [PlanOccurrenceStatus::Completed->value, PlanOccurrenceStatus::Skipped->value])
            ->first();

        $changing = Plan::query()
            ->with(['habit', 'periods'])
            ->where('created_by_actor_id', $actor->id)
            ->where('status', 'active')
            ->whereHas('habit', fn ($query) => $query->where('mode', 'replace'))
            ->latest('id')
            ->limit(3)
            ->get();

        return [
            'followUps' => $waiting->take(5)->values(),
            'followUpCount' => $waiting->count(),
            'reportStats' => ['told' => (int) ($recent->told ?? 0), 'total' => (int) ($recent->total ?? 0)],
            'changingHabits' => $changing,
        ];
    }

    /** Meeting requests waiting for this person's answer. */
    private function meetingsWaiting(Actor $actor): Collection
    {
        return MeetingInvitee::query()
            ->with('meeting.organizer.user')
            ->where('actor_id', $actor->id)
            ->where('status', MeetingInvitee::STATUS_PENDING)
            ->whereHas('meeting', fn ($query) => $query->where('status', MeetingRequest::STATUS_OPEN))
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (MeetingInvitee $invitee): HomeActionItem => new HomeActionItem(
                key: 'meeting:'.$invitee->uuid,
                kind: 'planner',
                title: (string) __('planner.meetings.today_waiting', [
                    'organizer' => $invitee->meeting->organizer->user->username ?? '',
                    'title' => $invitee->meeting->title,
                ]),
                summary: null,
                url: route('planner.meetings.show', $invitee->meeting),
                occurredAt: $invitee->created_at ?? now(),
            ))->values();
    }

    private function waitingOnMe(User $user, Actor $actor): Collection
    {
        /** @var Collection<int, HomeActionItem> $items */
        $items = collect();

        Relationship::query()
            ->where('status', RelationshipStatus::Proposed->value)
            ->whereHas('participants', fn ($query) => $query->where('actor_id', $actor->id))
            ->with(['purposeConcept.labels', 'participants.actor.user'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->filter(fn (Relationship $relationship): bool => Gate::forUser($user)->allows('respond', $relationship))
            ->each(function (Relationship $relationship) use ($items): void {
                $items->push(new HomeActionItem(
                    key: 'relationship:'.$relationship->uuid,
                    kind: 'relationship',
                    title: $relationship->title ?: $relationship->purposeConcept->displayLabel(),
                    summary: (string) __('home.actions.relationship_waiting'),
                    url: route('relationships.show', $relationship),
                    occurredAt: $relationship->updated_at ?? $relationship->created_at ?? now(),
                ));
            });

        Proposal::query()
            ->where('status', ProposalStatus::Negotiating->value)
            ->whereHas('parties', fn ($query) => $query->where('actor_id', $actor->id))
            ->with(['relationship', 'parties.decisions', 'versions'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->filter(fn (Proposal $proposal): bool => Gate::forUser($user)->allows('respond', $proposal))
            ->each(function (Proposal $proposal) use ($items): void {
                $items->push(new HomeActionItem(
                    key: 'proposal:'.$proposal->uuid,
                    kind: 'proposal',
                    title: $proposal->title,
                    summary: (string) __('home.actions.proposal_waiting'),
                    url: route('proposals.show', $proposal),
                    occurredAt: $proposal->updated_at ?? $proposal->created_at ?? now(),
                ));
            });

        Contract::query()
            ->where('status', ContractStatus::Pending->value)
            ->whereHas('versions.parties', fn ($query) => $query->where('actor_id', $actor->id))
            ->with(['versions.parties.acceptance'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->each(function (Contract $contract) use ($items, $user): void {
                $version = $contract->pendingVersionRecord();

                if ($version === null || ! Gate::forUser($user)->allows('accept', [$contract, $version])) {
                    return;
                }

                $items->push(new HomeActionItem(
                    key: 'contract:'.$contract->uuid,
                    kind: 'contract',
                    title: $contract->title,
                    summary: (string) __('home.actions.contract_waiting'),
                    url: route('contracts.show', $contract),
                    occurredAt: $contract->updated_at ?? $contract->created_at ?? now(),
                ));
            });

        Fulfillment::query()
            ->where('status', FulfillmentStatus::Submitted->value)
            ->whereHas('commitment', fn ($query) => $query->where('beneficiary_actor_id', $actor->id))
            ->with(['commitment.contractVersion.contract'])
            ->latest('submitted_at')
            ->limit(40)
            ->get()
            ->filter(fn (Fulfillment $fulfillment): bool => Gate::forUser($user)->allows('review', $fulfillment))
            ->each(function (Fulfillment $fulfillment) use ($items): void {
                $items->push(new HomeActionItem(
                    key: 'fulfillment:'.$fulfillment->uuid,
                    kind: 'fulfillment',
                    title: $fulfillment->commitment->title,
                    summary: (string) __('home.actions.fulfillment_waiting'),
                    url: route('commitments.show', $fulfillment->commitment).'#fulfillment-'.$fulfillment->uuid,
                    occurredAt: $fulfillment->submitted_at ?? $fulfillment->created_at ?? now(),
                ));
            });

        Submission::query()
            ->where('status', Submission::STATUS_SUBMITTED)
            ->with(['context', 'definitionVersion.definition', 'submitter.user'])
            ->latest('submitted_at')
            ->limit(100)
            ->get()
            ->filter(fn (Submission $submission): bool => Gate::forUser($user)->allows('reviewInteractions', $submission->context)
                && Gate::forUser($user)->allows('view', $submission))
            ->take(20)
            ->each(function (Submission $submission) use ($items): void {
                $items->push(new HomeActionItem(
                    key: 'submission:'.$submission->uuid,
                    kind: 'submission',
                    title: $submission->definitionVersion->definition->name,
                    summary: (string) __('home.actions.submission_waiting'),
                    url: route('contexts.submissions.show', [$submission->context, $submission]),
                    occurredAt: $submission->submitted_at ?? $submission->created_at ?? now(),
                ));
            });

        return $items
            ->sortByDesc(fn (HomeActionItem $item): int => $item->occurredAt->getTimestamp())
            ->take(20)
            ->values();
    }

    /** @return Collection<int, HomeActionItem> */
    private function waitingOnOthers(User $user, Actor $actor): Collection
    {
        /** @var Collection<int, HomeActionItem> $items */
        $items = collect();

        Relationship::query()
            ->where('status', RelationshipStatus::Proposed->value)
            ->whereHas('participants', fn ($query) => $query
                ->where('actor_id', $actor->id)
                ->where('status', RelationshipParticipantStatus::Active->value))
            ->with(['purposeConcept.labels', 'participants.actor.user'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->filter(function (Relationship $relationship) use ($user, $actor): bool {
                return Gate::forUser($user)->allows('view', $relationship)
                    && $relationship->participants->contains(
                        fn (RelationshipParticipant $participant): bool => (int) $participant->actor_id !== (int) $actor->id
                            && $participant->status === RelationshipParticipantStatus::Invited,
                    );
            })
            ->each(function (Relationship $relationship) use ($items): void {
                $items->push(new HomeActionItem(
                    key: 'relationship-wait:'.$relationship->uuid,
                    kind: 'relationship',
                    title: $relationship->title ?: $relationship->purposeConcept->displayLabel(),
                    summary: (string) __('home.actions.relationship_other'),
                    url: route('relationships.show', $relationship),
                    occurredAt: $relationship->updated_at ?? $relationship->created_at ?? now(),
                ));
            });

        Proposal::query()
            ->where('status', ProposalStatus::Negotiating->value)
            ->whereHas('parties', fn ($query) => $query->where('actor_id', $actor->id))
            ->with(['parties.decisions', 'versions'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->filter(function (Proposal $proposal) use ($user, $actor): bool {
                if (! Gate::forUser($user)->allows('view', $proposal)) {
                    return false;
                }

                $version = $proposal->currentVersionRecord();
                $party = $proposal->parties->firstWhere('actor_id', $actor->id);

                if ($version === null || $party === null) {
                    return false;
                }

                $currentHasDecided = $party->decisions->contains(
                    fn ($decision): bool => (int) $decision->proposal_version_id === (int) $version->id,
                );

                $otherRequiredPending = $proposal->parties->contains(function ($other) use ($actor, $version): bool {
                    return (int) $other->actor_id !== (int) $actor->id
                        && $other->required
                        && ! $other->decisions->contains(
                            fn ($decision): bool => (int) $decision->proposal_version_id === (int) $version->id,
                        );
                });

                return $currentHasDecided && $otherRequiredPending;
            })
            ->each(function (Proposal $proposal) use ($items): void {
                $items->push(new HomeActionItem(
                    key: 'proposal-wait:'.$proposal->uuid,
                    kind: 'proposal',
                    title: $proposal->title,
                    summary: (string) __('home.actions.proposal_other'),
                    url: route('proposals.show', $proposal),
                    occurredAt: $proposal->updated_at ?? $proposal->created_at ?? now(),
                ));
            });

        Contract::query()
            ->where('status', ContractStatus::Pending->value)
            ->whereHas('versions.parties', fn ($query) => $query->where('actor_id', $actor->id))
            ->with(['versions.parties.acceptance'])
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->filter(function (Contract $contract) use ($user, $actor): bool {
                if (! Gate::forUser($user)->allows('view', $contract)) {
                    return false;
                }

                $version = $contract->pendingVersionRecord();
                if ($version === null) {
                    return false;
                }

                $version->loadMissing('parties.acceptance');
                $party = $version->parties->firstWhere('actor_id', $actor->id);

                return $party !== null
                    && $party->acceptance !== null
                    && $version->parties->contains(
                        fn ($other): bool => (int) $other->actor_id !== (int) $actor->id
                            && $other->required
                            && $other->acceptance === null,
                    );
            })
            ->each(function (Contract $contract) use ($items): void {
                $items->push(new HomeActionItem(
                    key: 'contract-wait:'.$contract->uuid,
                    kind: 'contract',
                    title: $contract->title,
                    summary: (string) __('home.actions.contract_other'),
                    url: route('contracts.show', $contract),
                    occurredAt: $contract->updated_at ?? $contract->created_at ?? now(),
                ));
            });

        Fulfillment::query()
            ->where('status', FulfillmentStatus::Submitted->value)
            ->where('submitted_by_actor_id', $actor->id)
            ->with(['commitment.contractVersion.contract'])
            ->latest('submitted_at')
            ->limit(40)
            ->get()
            ->filter(fn (Fulfillment $fulfillment): bool => Gate::forUser($user)->allows('view', $fulfillment))
            ->each(function (Fulfillment $fulfillment) use ($items, $actor): void {
                if ((int) $fulfillment->commitment->beneficiary_actor_id === (int) $actor->id) {
                    return;
                }

                $items->push(new HomeActionItem(
                    key: 'fulfillment-wait:'.$fulfillment->uuid,
                    kind: 'fulfillment',
                    title: $fulfillment->commitment->title,
                    summary: (string) __('home.actions.fulfillment_other'),
                    url: route('commitments.show', $fulfillment->commitment).'#fulfillment-'.$fulfillment->uuid,
                    occurredAt: $fulfillment->submitted_at ?? $fulfillment->created_at ?? now(),
                ));
            });

        return $items
            ->sortByDesc(fn (HomeActionItem $item): int => $item->occurredAt->getTimestamp())
            ->take(20)
            ->values();
    }

    /**
     * The latest events across the places this person can see. Built on its own
     * (Today loads it after the page appears) because it reads many timelines.
     *
     * @return Collection<int, TimelineEntry>
     */
    public function recentActivity(User $user): Collection
    {
        $user = User::query()->with('actor')->find($user->id);
        $actor = $user?->actor;
        if (! $user instanceof User || ! $actor instanceof Actor) {
            return collect();
        }

        $contexts = collect();

        $personal = PersonalContext::query()->with('context')->where('actor_id', $actor->id)->first();
        if ($personal !== null) {
            $contexts->push($personal->context);
        }

        GroupSpace::query()
            ->where('status', 'active')
            ->with('contextBinding.context')
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->filter(fn (GroupSpace $space): bool => Gate::forUser($user)->allows('view', $space))
            ->take(6)
            ->each(function (GroupSpace $space) use ($contexts): void {
                if ($space->contextBinding?->context !== null) {
                    $contexts->push($space->contextBinding->context);
                }
            });

        RelationshipContext::query()
            ->whereHas('relationship.participants', fn ($query) => $query->where('actor_id', $actor->id))
            ->with('context')
            ->latest('id')
            ->limit(6)
            ->get()
            ->each(fn (RelationshipContext $binding) => $contexts->push($binding->context));

        ProposalContext::query()
            ->whereHas('proposal.parties', fn ($query) => $query->where('actor_id', $actor->id))
            ->with('context')
            ->latest('id')
            ->limit(4)
            ->get()
            ->each(fn (ProposalContext $binding) => $contexts->push($binding->context));

        ContractContext::query()
            ->whereHas('contract.versions.parties', fn ($query) => $query->where('actor_id', $actor->id))
            ->with('context')
            ->latest('id')
            ->limit(4)
            ->get()
            ->each(fn (ContractContext $binding) => $contexts->push($binding->context));

        /** @var Collection<int, TimelineEntry> $entries */
        $entries = collect();

        $contexts
            ->unique('id')
            ->filter(fn ($context): bool => Gate::forUser($user)->allows('view', $context))
            ->take(12)
            ->each(function ($context) use ($entries, $user): void {
                $entries->push(...$this->timeline->entries($context, $user, 8)->all());
            });

        return $entries
            ->sortByDesc(fn (TimelineEntry $entry): int => $entry->occurredAt->getTimestamp())
            ->take(16)
            ->values();
    }
}
