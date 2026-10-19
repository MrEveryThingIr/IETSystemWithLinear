<?php

namespace App\Support;

use App\ContextKind;
use App\Models\Context;

/**
 * Human wording for a Context: where something lives and who can see it.
 *
 * The Context kernel stays the authority; this only turns it into words such
 * as "English Class · Unit 1" and "Visible to group members", so ordinary
 * screens never have to show a Context id.
 */
final class ContextPresenter
{
    /** Relations needed by label() without lazy-loading per Context. */
    public const RELATIONS = [
        'personalBinding.actor.user',
        'businessBinding.business',
        'groupSpaceBinding.groupSpace.group',
        'admissionBinding.admission.group',
        'relationshipBinding.relationship.purposeConcept.labels',
        'proposalBinding.proposal',
        'contractBinding.contract',
        'referenceBinding',
        'ledgerBinding',
    ];

    public function label(Context $context): string
    {
        return match ($context->kind) {
            ContextKind::Personal => __('library.context.personal'),
            ContextKind::Business => $context->businessBinding?->business->name
                ?? __('content_workspace.context.business'),
            ContextKind::GroupSpace => trim(implode(' · ', array_filter([
                $context->groupSpaceBinding?->groupSpace?->group?->name,
                $context->groupSpaceBinding?->groupSpace?->name,
            ]))) ?: __('library.context.group_space'),
            ContextKind::Admission => __('library.context.admission', [
                'group' => $context->admissionBinding?->admission?->group->name ?? '#'.$context->admissionBinding?->admission_id,
            ]),
            ContextKind::Relationship => __('library.context.relationship', [
                'title' => $context->relationshipBinding?->relationship?->title
                    ?: $context->relationshipBinding?->relationship?->purposeConcept?->displayLabel()
                    ?: __('content_workspace.context.untitled'),
            ]),
            ContextKind::Negotiation => __('library.context.negotiation', [
                'title' => $context->proposalBinding?->proposal->title ?? __('content_workspace.context.untitled'),
            ]),
            ContextKind::Contract => __('library.context.contract', [
                'title' => $context->contractBinding?->contract->title ?? __('content_workspace.context.untitled'),
            ]),
            ContextKind::Reference => __('library.context.reference', [
                'key' => $context->referenceBinding->key ?? __('content_workspace.context.untitled'),
            ]),
            ContextKind::System => __('content_workspace.context.system'),
            ContextKind::Ledger => $context->ledgerBinding->name ?? __('bookkeeping.book'),
        };
    }

    /** Who can read published Content in this Context, in plain words. */
    public function audience(Context $context): string
    {
        return __('content_workspace.audience.'.match ($context->kind) {
            ContextKind::Personal => 'personal',
            ContextKind::Business => 'business',
            ContextKind::GroupSpace => 'group',
            ContextKind::Admission => 'admission',
            ContextKind::Relationship, ContextKind::Negotiation, ContextKind::Contract, ContextKind::Ledger => 'participants',
            ContextKind::Reference, ContextKind::System => 'everyone_signed_in',
        });
    }

    public function icon(Context $context): string
    {
        return match ($context->kind) {
            ContextKind::Personal => 'user',
            ContextKind::Business => 'building-office',
            ContextKind::GroupSpace, ContextKind::Admission => 'user-group',
            ContextKind::Relationship, ContextKind::Negotiation, ContextKind::Contract => 'briefcase',
            ContextKind::Reference, ContextKind::System => 'book-open',
            ContextKind::Ledger => 'banknotes',
        };
    }
}
