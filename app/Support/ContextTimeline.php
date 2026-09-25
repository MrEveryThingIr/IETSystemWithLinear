<?php

namespace App\Support;

use App\Models\AdmissionEvent;
use App\Models\CommitmentEvent;
use App\Models\Context;
use App\Models\ContractEvent;
use App\Models\ConversationMessage;
use App\Models\FinancialObligationEvent;
use App\Models\JournalEntry;
use App\Models\PlanEvent;
use App\Models\PlanOccurrenceEvent;
use App\Models\ProposalEvent;
use App\Models\RelationshipEvent;
use App\Models\SpaceContentLifecycleEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ContextTimeline
{
    /** @return Collection<int, TimelineEntry> */
    public function entries(Context $context, User $user, int $limit = 200): Collection
    {
        Gate::forUser($user)->authorize('view', $context);

        /** @var Collection<int, TimelineEntry> $entries */
        $entries = collect();

        ConversationMessage::query()
            ->with(['author.user'])
            ->whereHas('conversation', fn ($query) => $query->where('context_id', $context->id))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(function (ConversationMessage $message) use ($context, $entries): void {
                $entries->push(new TimelineEntry(
                    key: 'message:'.$message->uuid,
                    kind: 'message',
                    title: (string) __('collaboration.timeline.message'),
                    summary: Str::limit($message->body, 240),
                    occurredAt: $message->created_at ?? now(),
                    actor: $message->author,
                    url: route('contexts.conversation', $context).'#message-'.$message->uuid,
                ));
            });

        JournalEntry::query()
            ->with(['ledger', 'creator.user'])
            ->whereHas('ledger', fn ($query) => $query->where('context_id', $context->id))
            ->latest('posted_at')
            ->limit($limit)
            ->get()
            ->each(function (JournalEntry $entry) use ($entries, $user): void {
                if (! Gate::forUser($user)->allows('view', $entry->ledger)) {
                    return;
                }

                $kind = (string) __('accounting.entry_kind.'.$entry->kind->value);
                $description = $entry->description ?: $kind;

                $entries->push(new TimelineEntry(
                    key: 'journal-entry:'.$entry->uuid,
                    kind: 'accounting',
                    title: (string) __('accounting.timeline.entry', [
                        'kind' => $kind,
                        'description' => $description,
                    ]),
                    summary: $entry->description,
                    occurredAt: $entry->posted_at ?? $entry->created_at ?? now(),
                    actor: $entry->creator,
                    url: route('accounting.index', ['ledger' => $entry->ledger->uuid]).'#entry-'.$entry->uuid,
                ));
            });

        PlanEvent::query()
            ->with(['plan', 'actor.user'])
            ->whereHas('plan', fn ($query) => $query->where('context_id', $context->id))
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->each(function (PlanEvent $event) use ($entries): void {
                $entries->push(new TimelineEntry(
                    key: 'plan-event:'.$event->uuid,
                    kind: 'planner',
                    title: (string) __('planner.events.'.$event->event_type->value),
                    summary: $event->plan->title,
                    occurredAt: $event->created_at ?? now(),
                    actor: $event->actor,
                    url: route('planner.show', $event->plan),
                ));
            });

        PlanOccurrenceEvent::query()
            ->with(['occurrence.plan', 'actor.user'])
            ->whereHas('occurrence.plan', fn ($query) => $query->where('context_id', $context->id))
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->each(function (PlanOccurrenceEvent $event) use ($entries): void {
                $entries->push(new TimelineEntry(
                    key: 'plan-occurrence-event:'.$event->uuid,
                    kind: 'planner',
                    title: (string) __('planner.events.occurrence_'.$event->event_type->value),
                    summary: $event->occurrence->plan->title,
                    occurredAt: $event->created_at ?? now(),
                    actor: $event->actor,
                    url: route('planner.show', $event->occurrence->plan).'#occurrence-'.$event->occurrence->uuid,
                ));
            });

        SpaceContentLifecycleEvent::query()
            ->with(['content.activeRevision', 'actor.user'])
            ->whereHas('content', fn ($query) => $query->where('context_id', $context->id))
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->each(function (SpaceContentLifecycleEvent $event) use ($context, $entries, $user): void {
                if (! Gate::forUser($user)->allows('view', $event->content)) {
                    return;
                }

                $title = $event->content->activeRevision?->title
                    ?: (string) __('ui.content.untitled');

                $entries->push(new TimelineEntry(
                    key: 'content:'.$event->uuid,
                    kind: 'content',
                    title: (string) __('collaboration.timeline.content', [
                        'event' => Str::headline($event->event_type),
                        'title' => $title,
                    ]),
                    summary: $event->reason,
                    occurredAt: $event->created_at ?? now(),
                    actor: $event->actor,
                    url: route('contexts.contents.show', [$context, $event->content]),
                ));
            });

        $context->loadMissing([
            'proposalBinding.proposal',
            'contractBinding.contract',
            'relationshipBinding.relationship',
            'admissionBinding.admission',
        ]);

        $proposal = $context->proposalBinding?->proposal;
        if ($proposal !== null) {
            ProposalEvent::query()
                ->with('actor.user')
                ->where('proposal_id', $proposal->id)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->each(function (ProposalEvent $event) use ($proposal, $entries): void {
                    $entries->push(new TimelineEntry(
                        key: 'proposal-event:'.$event->uuid,
                        kind: 'proposal',
                        title: (string) __('proposals.events.'.$event->event_type->value),
                        summary: $proposal->title,
                        occurredAt: $event->created_at ?? now(),
                        actor: $event->actor,
                        url: route('proposals.show', $proposal),
                    ));
                });
        }

        $contract = $context->contractBinding?->contract;
        if ($contract !== null) {
            ContractEvent::query()
                ->with('actor.user')
                ->where('contract_id', $contract->id)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->each(function (ContractEvent $event) use ($contract, $entries): void {
                    $entries->push(new TimelineEntry(
                        key: 'contract-event:'.$event->uuid,
                        kind: 'contract',
                        title: (string) __('contracts.events.'.$event->event_type->value),
                        summary: $contract->title,
                        occurredAt: $event->created_at ?? now(),
                        actor: $event->actor,
                        url: route('contracts.show', $contract),
                    ));
                });

            CommitmentEvent::query()
                ->with(['actor.user', 'commitment'])
                ->whereHas('commitment.contractVersion', fn ($query) => $query->where('contract_id', $contract->id))
                ->latest('id')
                ->limit($limit)
                ->get()
                ->each(function (CommitmentEvent $event) use ($entries): void {
                    $entries->push(new TimelineEntry(
                        key: 'commitment-event:'.$event->uuid,
                        kind: 'commitment',
                        title: (string) __('commitments.events.'.$event->event_type->value),
                        summary: $event->commitment->title,
                        occurredAt: $event->created_at ?? now(),
                        actor: $event->actor,
                        url: route('commitments.show', $event->commitment),
                    ));
                });

            FinancialObligationEvent::query()
                ->with(['actor.user', 'obligation'])
                ->whereHas(
                    'obligation.contractVersion',
                    fn ($query) => $query->where('contract_id', $contract->id),
                )
                ->latest('id')
                ->limit($limit)
                ->get()
                ->each(function (FinancialObligationEvent $event) use ($entries, $user): void {
                    if (! Gate::forUser($user)->allows('view', $event->obligation)) {
                        return;
                    }

                    $entries->push(new TimelineEntry(
                        key: 'financial-obligation-event:'.$event->uuid,
                        kind: 'financial',
                        title: (string) __('financial.events.'.$event->event_type->value),
                        summary: $event->obligation->description,
                        occurredAt: $event->created_at ?? now(),
                        actor: $event->actor,
                        url: route('financial-obligations.show', $event->obligation),
                    ));
                });
        }

        $relationship = $context->relationshipBinding?->relationship;
        if ($relationship !== null) {
            RelationshipEvent::query()
                ->with('actor.user')
                ->where('relationship_id', $relationship->id)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->each(function (RelationshipEvent $event) use ($relationship, $entries): void {
                    $entries->push(new TimelineEntry(
                        key: 'relationship-event:'.$event->id,
                        kind: 'relationship',
                        title: (string) __('relationships.events.'.$event->event_type->value),
                        summary: null,
                        occurredAt: $event->created_at ?? now(),
                        actor: $event->actor,
                        url: route('relationships.show', $relationship),
                    ));
                });
        }

        $admission = $context->admissionBinding?->admission;
        if ($admission !== null) {
            AdmissionEvent::query()
                ->with('actor.user')
                ->where('admission_id', $admission->id)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->each(function (AdmissionEvent $event) use ($admission, $entries): void {
                    $entries->push(new TimelineEntry(
                        key: 'admission-event:'.$event->id,
                        kind: 'admission',
                        title: (string) __('ui.events.'.str_replace('.', '_', $event->event)),
                        summary: $event->note,
                        occurredAt: $event->created_at ?? now(),
                        actor: $event->actor,
                        url: route('admissions.show', $admission),
                    ));
                });
        }

        return $entries
            ->sort(function (TimelineEntry $left, TimelineEntry $right): int {
                $time = $right->occurredAt->getTimestamp() <=> $left->occurredAt->getTimestamp();

                return $time !== 0 ? $time : strcmp($right->key, $left->key);
            })
            ->take($limit)
            ->values();
    }
}
