<?php

namespace App\Support;

use App\CommitmentEventType;
use App\ContractEventType;
use App\FinancialObligationEventType;
use App\Models\Actor;
use App\Models\CommitmentEvent;
use App\Models\ConversationMessage;
use App\Models\ContractEvent;
use App\Models\Evaluation;
use App\Models\FinancialObligationEvent;
use App\Models\ProposalEvent;
use App\Models\RelationshipEvent;
use App\Models\Submission;
use App\Models\User;
use App\ProposalEventType;
use App\RelationshipEventType;
use App\RelationshipParticipantStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class DomainNotificationProjector
{
    public function __construct(
        private readonly NotificationOutboxWriter $outbox,
        private readonly ContextNotificationRecipients $recipients,
    ) {}

    public function relationship(RelationshipEvent $event): void
    {
        $event->loadMissing([
            'relationship.contextBinding.context',
            'relationship.participants.actor.user',
        ]);

        $relationship = $event->relationship;
        $context = $relationship->contextBinding?->context;
        $participants = $relationship->participants;

        $recipients = $event->event_type === RelationshipEventType::Proposed
            ? $participants->where('status', RelationshipParticipantStatus::Invited)
            : $participants;

        $users = $this->usersFromActors($recipients->pluck('actor'), $event->actor_id);

        foreach ($users as $user) {
            $this->outbox->request(
                $user,
                "relationship-event:{$event->id}:{$user->id}",
                'relationship.event',
                [
                    'title_key' => $event->event_type === RelationshipEventType::Proposed
                        ? 'notifications.messages.relationship_proposed_title'
                        : 'notifications.messages.relationship_updated_title',
                    'title_params' => ['title' => $relationship->title ?: '#'.$relationship->uuid],
                    'body_key' => 'notifications.messages.relationship_event_body',
                    'body_params' => ['event' => str_replace('_', ' ', $event->event_type->value)],
                    'url' => route('relationships.show', $relationship),
                ],
                $context,
                'relationship',
                $relationship->uuid,
            );
        }
    }

    public function proposal(ProposalEvent $event): void
    {
        $event->loadMissing([
            'proposal.contextBinding.context',
            'proposal.parties.actor.user',
        ]);

        $proposal = $event->proposal;
        $context = $proposal->contextBinding?->context;
        $users = $this->usersFromActors($proposal->parties->pluck('actor'), $event->actor_id);

        foreach ($users as $user) {
            $this->outbox->request(
                $user,
                "proposal-event:{$event->id}:{$user->id}",
                'proposal.event',
                [
                    'title_key' => $event->event_type === ProposalEventType::VersionProposed
                        ? 'notifications.messages.proposal_version_title'
                        : 'notifications.messages.proposal_updated_title',
                    'title_params' => ['title' => $proposal->title],
                    'body_key' => 'notifications.messages.proposal_event_body',
                    'body_params' => ['event' => str_replace('_', ' ', $event->event_type->value)],
                    'url' => route('proposals.show', $proposal),
                ],
                $context,
                'proposal',
                $proposal->uuid,
            );
        }
    }

    public function contract(ContractEvent $event): void
    {
        $event->loadMissing([
            'contract.contextBinding.context',
            'contractVersion.parties.actor.user',
            'contract.versions.parties.actor.user',
        ]);

        $contract = $event->contract;
        $version = $event->contractVersion ?? $contract->versions->sortByDesc('version')->first();

        if ($version === null) {
            return;
        }

        $context = $contract->contextBinding?->context;
        $users = $this->usersFromActors($version->parties->pluck('actor'), $event->actor_id);

        foreach ($users as $user) {
            $this->outbox->request(
                $user,
                "contract-event:{$event->id}:{$user->id}",
                'contract.event',
                [
                    'title_key' => $event->event_type === ContractEventType::VersionProposed
                        ? 'notifications.messages.contract_version_title'
                        : 'notifications.messages.contract_updated_title',
                    'title_params' => ['title' => $contract->title],
                    'body_key' => 'notifications.messages.contract_event_body',
                    'body_params' => ['event' => str_replace('_', ' ', $event->event_type->value)],
                    'url' => route('contracts.show', $contract),
                ],
                $context,
                'contract',
                $contract->uuid,
            );
        }
    }

    public function commitment(CommitmentEvent $event): void
    {
        $event->loadMissing([
            'commitment.contractVersion.contract.contextBinding.context',
            'commitment.obligor.user',
            'commitment.beneficiary.user',
        ]);

        $commitment = $event->commitment;
        $context = $commitment->contractVersion->contract->contextBinding?->context;

        $actors = match ($event->event_type) {
            CommitmentEventType::FulfillmentSubmitted => collect([$commitment->beneficiary]),
            CommitmentEventType::FulfillmentReviewed => collect([$commitment->obligor]),
            default => collect([$commitment->obligor, $commitment->beneficiary]),
        };

        $users = $this->usersFromActors($actors, $event->actor_id);

        foreach ($users as $user) {
            $this->outbox->request(
                $user,
                "commitment-event:{$event->id}:{$user->id}",
                'commitment.event',
                [
                    'title_key' => match ($event->event_type) {
                        CommitmentEventType::FulfillmentSubmitted => 'notifications.messages.fulfillment_submitted_title',
                        CommitmentEventType::FulfillmentReviewed => 'notifications.messages.fulfillment_reviewed_title',
                        default => 'notifications.messages.commitment_updated_title',
                    },
                    'title_params' => ['title' => $commitment->title],
                    'body_key' => 'notifications.messages.commitment_event_body',
                    'body_params' => ['event' => str_replace('_', ' ', $event->event_type->value)],
                    'url' => route('commitments.show', $commitment),
                ],
                $context,
                'commitment',
                $commitment->uuid,
            );
        }
    }

    public function financial(FinancialObligationEvent $event): void
    {
        if (in_array($event->event_type, [
            FinancialObligationEventType::AccountingPosted,
            FinancialObligationEventType::SettlementAccountingPosted,
        ], true)) {
            return;
        }

        $event->loadMissing([
            'obligation.contractVersion.contract.contextBinding.context',
            'obligation.debtor.user',
            'obligation.creditor.user',
        ]);

        $obligation = $event->obligation;
        $context = $obligation->contractVersion->contract->contextBinding?->context;
        $users = $this->usersFromActors(
            collect([$obligation->debtor, $obligation->creditor]),
            $event->actor_id,
        );

        foreach ($users as $user) {
            $this->outbox->request(
                $user,
                "financial-event:{$event->id}:{$user->id}",
                'financial.event',
                [
                    'title_key' => match ($event->event_type) {
                        FinancialObligationEventType::SettlementProposed => 'notifications.messages.settlement_proposed_title',
                        FinancialObligationEventType::SettlementConfirmed => 'notifications.messages.settlement_confirmed_title',
                        FinancialObligationEventType::SettlementRejected => 'notifications.messages.settlement_rejected_title',
                        default => 'notifications.messages.financial_obligation_title',
                    },
                    'body_key' => 'notifications.messages.financial_event_body',
                    'body_params' => ['event' => str_replace('_', ' ', $event->event_type->value)],
                    'url' => route('financial-obligations.show', $obligation),
                ],
                $context,
                'financial_obligation',
                $obligation->uuid,
            );
        }
    }

    public function conversation(ConversationMessage $message): void
    {
        $message->loadMissing('conversation.context', 'author.user');

        $context = $message->conversation->context;
        $users = $this->recipients->viewers($context, $message->author_actor_id);

        foreach ($users as $user) {
            $this->outbox->request(
                $user,
                "conversation-message:{$message->id}:{$user->id}",
                'conversation.message',
                [
                    'title_key' => 'notifications.messages.conversation_message_title',
                    'body_key' => 'notifications.messages.conversation_message_body',
                    'body_params' => ['author' => $message->author->user?->username ?? ''],
                    'url' => route('contexts.conversation', $context).'#message-'.$message->uuid,
                ],
                $context,
                'conversation_message',
                $message->uuid,
            );
        }
    }

    public function submission(Submission $submission): void
    {
        $submission->loadMissing('context', 'submitter.user');

        if ($submission->status !== Submission::STATUS_SUBMITTED) {
            return;
        }

        $users = $this->recipients->viewers($submission->context, $submission->submitted_by_actor_id)
            ->filter(fn (User $user): bool => Gate::forUser($user)->allows('reviewInteractions', $submission->context)
                && Gate::forUser($user)->allows('view', $submission))
            ->values();

        foreach ($users as $user) {
            $this->outbox->request(
                $user,
                "submission-submitted:{$submission->uuid}:{$user->id}",
                'submission.submitted',
                [
                    'title_key' => 'notifications.messages.submission_submitted_title',
                    'body_key' => 'notifications.messages.submission_submitted_body',
                    'body_params' => ['author' => $submission->submitter->user?->username ?? ''],
                    'url' => route('contexts.submissions.show', [$submission->context, $submission]),
                ],
                $submission->context,
                'submission',
                $submission->uuid,
            );
        }
    }

    public function evaluation(Evaluation $evaluation): void
    {
        if ($evaluation->status !== Evaluation::STATUS_FINALIZED) {
            return;
        }

        $evaluation->loadMissing('submission.context', 'submission.submitter.user');

        $submission = $evaluation->submission;
        $user = $submission->submitter->user;

        if ($user instanceof User === false) {
            return;
        }

        if ($user->status !== 'active' || $user->email_verified_at === null) {
            return;
        }

        if (Gate::forUser($user)->denies('view', $submission)) {
            return;
        }

        $this->outbox->request(
            $user,
            "evaluation-finalized:{$evaluation->uuid}:{$user->id}",
            'evaluation.finalized',
            [
                'title_key' => 'notifications.messages.evaluation_finalized_title',
                'body_key' => 'notifications.messages.evaluation_finalized_body',
                'url' => route('contexts.contents.index', $submission->context),
            ],
            $submission->context,
            'evaluation',
            $evaluation->uuid,
        );
    }

    /**
     * @param Collection<int, Actor|null> $actors
     * @return Collection<int, User>
     */
    private function usersFromActors(Collection $actors, ?int $excludeActorId): Collection
    {
        return $actors
            ->filter(fn ($actor): bool => $actor instanceof Actor)
            ->reject(fn (Actor $actor): bool => $excludeActorId !== null && (int) $actor->id === $excludeActorId)
            ->map(fn (Actor $actor): ?User => $actor->user)
            ->filter(fn ($user): bool => $user instanceof User
                && $user->status === 'active'
                && $user->email_verified_at !== null)
            ->unique('id')
            ->values();
    }
}
