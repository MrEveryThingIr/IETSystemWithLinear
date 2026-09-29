<?php

namespace App\Support;

use App\ContractStatus;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Contract;
use App\Models\ContractContext;
use App\Models\FinancialObligation;
use App\Models\Fulfillment;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\PersonalContext;
use App\Models\PlanOccurrence;
use App\Models\Proposal;
use App\Models\ProposalContext;
use App\Models\Relationship;
use App\Models\RelationshipContext;
use App\Models\RelationshipParticipant;
use App\Models\Settlement;
use App\Models\Submission;
use App\Models\User;
use App\ProfileIntentStatus;
use App\ProposalStatus;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use App\SettlementStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class HomeTodayProjection
{
    public function __construct(
        private readonly AccountingSummary $accounting,
        private readonly ContextTimeline $timeline,
    ) {}

    /**
     * @return array{
     *   timezone: string,
     *   today: string,
     *   todayDisplay: string,
     *   todayOccurrences: Collection<int, PlanOccurrence>,
     *   waitingOnMe: Collection<int, HomeActionItem>,
     *   waitingOnOthers: Collection<int, HomeActionItem>,
     *   activeIntents: Collection<int, ActorProfileIntent>,
     *   activeRelationships: Collection<int, Relationship>,
     *   groupMemberships: Collection<int, GroupMembership>,
     *   accountingToday: Collection<int, array{code:string, exponent:int, income_minor:int, expense_minor:int, net_minor:int}>,
     *   obligations: Collection<int, array{code:string, exponent:int, receivable_total_minor:int, receivable_paid_minor:int, receivable_outstanding_minor:int, payable_total_minor:int, payable_paid_minor:int, payable_outstanding_minor:int}>,
     *   recentActivity: Collection<int, TimelineEntry>
     * }
     */
    public function build(User $user): array
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User, 403);

        $timezone = TemporalPreferences::timezoneFor($current);
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();
        $todayDisplay = TemporalCalendar::dateLabelWithEquivalent($now, $current, $timezone);

        if (! $current->actor instanceof Actor) {
            return $this->emptyProjection($timezone, $today, $todayDisplay);
        }

        $actor = $current->actor;

        $todayOccurrences = PlanOccurrence::query()
            ->with(['plan.context', 'plan.participants.actor.user'])
            ->whereBetween('scheduled_start_at', [
                $now->startOfDay()->utc(),
                $now->endOfDay()->utc(),
            ])
            ->orderBy('scheduled_start_at')
            ->limit(250)
            ->get()
            ->filter(fn (PlanOccurrence $occurrence): bool => Gate::forUser($current)->allows('participate', $occurrence->plan))
            ->values();

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

        return [
            'timezone' => $timezone,
            'today' => $today,
            'todayDisplay' => $todayDisplay,
            'todayOccurrences' => $todayOccurrences,
            'waitingOnMe' => $this->waitingOnMe($current, $actor),
            'waitingOnOthers' => $this->waitingOnOthers($current, $actor),
            'activeIntents' => $activeIntents,
            'activeRelationships' => $activeRelationships,
            'groupMemberships' => $groupMemberships,
            'accountingToday' => $this->accountingToday($current, $actor, $today),
            'obligations' => $this->obligationSummary($current, $actor),
            'recentActivity' => $this->recentActivity($current, $actor),
        ];
    }

    /**
     * @return array{
     *   timezone: string,
     *   today: string,
     *   todayDisplay: string,
     *   todayOccurrences: Collection<int, PlanOccurrence>,
     *   waitingOnMe: Collection<int, HomeActionItem>,
     *   waitingOnOthers: Collection<int, HomeActionItem>,
     *   activeIntents: Collection<int, ActorProfileIntent>,
     *   activeRelationships: Collection<int, Relationship>,
     *   groupMemberships: Collection<int, GroupMembership>,
     *   accountingToday: Collection<int, array{code:string, exponent:int, income_minor:int, expense_minor:int, net_minor:int}>,
     *   obligations: Collection<int, array{code:string, exponent:int, receivable_total_minor:int, receivable_paid_minor:int, receivable_outstanding_minor:int, payable_total_minor:int, payable_paid_minor:int, payable_outstanding_minor:int}>,
     *   recentActivity: Collection<int, TimelineEntry>
     * }
     */
    private function emptyProjection(string $timezone, string $today, string $todayDisplay): array
    {
        return [
            'timezone' => $timezone,
            'today' => $today,
            'todayDisplay' => $todayDisplay,
            'todayOccurrences' => collect(),
            'waitingOnMe' => collect(),
            'waitingOnOthers' => collect(),
            'activeIntents' => collect(),
            'activeRelationships' => collect(),
            'groupMemberships' => collect(),
            'accountingToday' => collect(),
            'obligations' => collect(),
            'recentActivity' => collect(),
        ];
    }

    /** @return Collection<int, HomeActionItem> */
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

        Settlement::query()
            ->where('status', SettlementStatus::PendingConfirmation->value)
            ->whereHas('obligation', fn ($query) => $query
                ->where('debtor_actor_id', $actor->id)
                ->orWhere('creditor_actor_id', $actor->id))
            ->with(['obligation.monetaryUnit'])
            ->latest('paid_at')
            ->limit(40)
            ->get()
            ->filter(fn (Settlement $settlement): bool => Gate::forUser($user)->allows('respond', $settlement))
            ->each(function (Settlement $settlement) use ($items): void {
                $items->push(new HomeActionItem(
                    key: 'settlement:'.$settlement->uuid,
                    kind: 'settlement',
                    title: $settlement->obligation->description ?: (string) __('financial.obligation.title'),
                    summary: (string) __('home.actions.settlement_waiting'),
                    url: route('financial-obligations.show', $settlement->obligation),
                    occurredAt: $settlement->paid_at ?? $settlement->created_at ?? now(),
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

        Settlement::query()
            ->where('status', SettlementStatus::PendingConfirmation->value)
            ->where('proposed_by_actor_id', $actor->id)
            ->with(['obligation.monetaryUnit'])
            ->latest('paid_at')
            ->limit(40)
            ->get()
            ->filter(fn (Settlement $settlement): bool => Gate::forUser($user)->allows('view', $settlement))
            ->each(function (Settlement $settlement) use ($items): void {
                $items->push(new HomeActionItem(
                    key: 'settlement-wait:'.$settlement->uuid,
                    kind: 'settlement',
                    title: $settlement->obligation->description ?: (string) __('financial.obligation.title'),
                    summary: (string) __('home.actions.settlement_other'),
                    url: route('financial-obligations.show', $settlement->obligation),
                    occurredAt: $settlement->paid_at ?? $settlement->created_at ?? now(),
                ));
            });

        return $items
            ->sortByDesc(fn (HomeActionItem $item): int => $item->occurredAt->getTimestamp())
            ->take(20)
            ->values();
    }

    /**
     * @return Collection<int, array{code:string, exponent:int, income_minor:int, expense_minor:int, net_minor:int}>
     */
    private function accountingToday(User $user, Actor $actor, string $today): Collection
    {
        $personal = PersonalContext::query()
            ->with('context.ledgers.monetaryUnit')
            ->where('actor_id', $actor->id)
            ->first();

        if ($personal === null) {
            return collect();
        }

        $buckets = [];

        foreach ($personal->context->ledgers as $ledger) {
            if (! Gate::forUser($user)->allows('view', $ledger)) {
                continue;
            }

            $period = $this->accounting->period($ledger, $today, $today);
            $code = $ledger->monetaryUnit->code;

            $buckets[$code] ??= [
                'code' => $code,
                'exponent' => $ledger->monetaryUnit->exponent,
                'income_minor' => 0,
                'expense_minor' => 0,
                'net_minor' => 0,
            ];

            $buckets[$code]['income_minor'] += $period['income_minor'];
            $buckets[$code]['expense_minor'] += $period['expense_minor'];
            $buckets[$code]['net_minor'] += $period['net_minor'];
        }

        return collect(array_values($buckets));
    }

    /**
     * @return Collection<int, array{code:string, exponent:int, receivable_total_minor:int, receivable_paid_minor:int, receivable_outstanding_minor:int, payable_total_minor:int, payable_paid_minor:int, payable_outstanding_minor:int}>
     */
    private function obligationSummary(User $user, Actor $actor): Collection
    {
        $obligations = FinancialObligation::query()
            ->where(fn ($query) => $query
                ->where('debtor_actor_id', $actor->id)
                ->orWhere('creditor_actor_id', $actor->id))
            ->with(['monetaryUnit', 'settlements'])
            ->latest('recognized_at')
            ->limit(250)
            ->get()
            ->filter(fn (FinancialObligation $obligation): bool => Gate::forUser($user)->allows('view', $obligation));

        $buckets = [];

        foreach ($obligations as $obligation) {
            $code = $obligation->monetaryUnit->code;
            $paid = (int) $obligation->settlements
                ->filter(fn (Settlement $settlement): bool => $settlement->status === SettlementStatus::Confirmed)
                ->sum('amount_minor');
            $outstanding = max(0, (int) $obligation->amount_minor - $paid);

            $buckets[$code] ??= [
                'code' => $code,
                'exponent' => $obligation->monetaryUnit->exponent,
                'receivable_total_minor' => 0,
                'receivable_paid_minor' => 0,
                'receivable_outstanding_minor' => 0,
                'payable_total_minor' => 0,
                'payable_paid_minor' => 0,
                'payable_outstanding_minor' => 0,
            ];

            if ((int) $obligation->creditor_actor_id === (int) $actor->id) {
                $buckets[$code]['receivable_total_minor'] += (int) $obligation->amount_minor;
                $buckets[$code]['receivable_paid_minor'] += $paid;
                $buckets[$code]['receivable_outstanding_minor'] += $outstanding;
            }

            if ((int) $obligation->debtor_actor_id === (int) $actor->id) {
                $buckets[$code]['payable_total_minor'] += (int) $obligation->amount_minor;
                $buckets[$code]['payable_paid_minor'] += $paid;
                $buckets[$code]['payable_outstanding_minor'] += $outstanding;
            }
        }

        return collect(array_values($buckets));
    }

    /** @return Collection<int, TimelineEntry> */
    private function recentActivity(User $user, Actor $actor): Collection
    {
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
