<?php

namespace App\Support;

use App\DomainJourneyKind;

class SystemDomainBlueprints
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return [
            $this->relationship(
                'simple-sale',
                'Simple Sale',
                'A lightweight buyer/seller journey with optional discussion, agreement, and financial follow-through.',
                'commerce',
                'seller',
                'buyer',
                ['conversation', 'content', 'proposal', 'contract', 'financial', 'accounting'],
                ['note-diary', 'proposal-terms', 'contract-terms'],
                'sale',
            ),
            $this->relationship(
                'rental',
                'Rental',
                'A rental/lease journey with agreement, scheduling, fulfillment evidence, and financial consequences.',
                'housing',
                'lessor',
                'tenant',
                ['conversation', 'content', 'planner', 'proposal', 'contract', 'commitment', 'fulfillment', 'financial', 'accounting'],
                ['note-diary', 'activity-report', 'proposal-terms', 'contract-terms'],
                'rental',
            ),
            $this->relationship(
                'service-job',
                'Service Job',
                'A client/provider service journey from discussion through performance and settlement.',
                'work',
                'client',
                'service provider',
                ['conversation', 'content', 'planner', 'proposal', 'contract', 'commitment', 'fulfillment', 'financial', 'accounting'],
                ['note-diary', 'activity-report', 'evidence-work-sample', 'proposal-terms', 'contract-terms'],
                'service',
            ),
            $this->relationship(
                'paid-work',
                'Employment / Paid Work',
                'A paid-work journey with explicit agreement, planned work, performance review, earned obligations, and settlement.',
                'work',
                'employer',
                'worker',
                ['conversation', 'content', 'planner', 'proposal', 'contract', 'commitment', 'fulfillment', 'financial', 'accounting'],
                ['note-diary', 'activity-report', 'evidence-work-sample', 'proposal-terms', 'contract-terms'],
                'paid work',
            ),
            $this->relationship(
                'construction-partnership',
                'Construction Partnership',
                'A multi-capability construction collaboration using the proven Relationship → Contract → Planner → Fulfillment → Financial chain.',
                'project',
                'project partner',
                'construction partner',
                ['conversation', 'content', 'planner', 'proposal', 'contract', 'commitment', 'fulfillment', 'financial', 'accounting'],
                ['article', 'activity-report', 'evidence-work-sample', 'proposal-terms', 'contract-terms'],
                'construction',
            ),
            [
                'slug' => 'personal-activity',
                'name' => 'Personal Activity',
                'description' => 'A private planning journey for appointments, routines, study, exercise, errands, and other personal activity.',
                'category' => 'personal',
                'version' => [
                    'journey_kind' => DomainJourneyKind::PersonalActivity,
                    'terminology' => [
                        'journey' => 'Personal activity',
                        'plan' => 'Activity',
                        'occurrence' => 'Occurrence',
                    ],
                    'capabilities' => ['planner', 'content'],
                    'content_blueprint_slugs' => ['note-diary', 'activity-report'],
                    'guided_entry' => [
                        'title_hint' => 'Personal activity',
                        'description_hint' => 'What do you want to plan?',
                        'frequency' => 'once',
                        'duration_minutes' => 60,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param list<string> $capabilities
     * @param list<string> $contentBlueprintSlugs
     * @return array<string, mixed>
     */
    private function relationship(
        string $slug,
        string $name,
        string $description,
        string $category,
        string $creatorRole,
        string $participantRole,
        array $capabilities,
        array $contentBlueprintSlugs,
        string $purposeHint,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'version' => [
                'journey_kind' => DomainJourneyKind::Relationship,
                'terminology' => [
                    'journey' => $name,
                    'relationship' => $name,
                    'creator_role' => $creatorRole,
                    'participant_role' => $participantRole,
                ],
                'capabilities' => $capabilities,
                'content_blueprint_slugs' => $contentBlueprintSlugs,
                'guided_entry' => [
                    'purpose_hint' => $purposeHint,
                    'creator_role' => $creatorRole,
                    'participant_role' => $participantRole,
                ],
            ],
        ];
    }
}
