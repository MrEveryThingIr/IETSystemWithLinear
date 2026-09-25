<?php

namespace Database\Factories;

use App\DomainJourneyKind;
use App\Models\DomainBlueprint;
use App\Models\DomainBlueprintVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DomainBlueprintVersion> */
class DomainBlueprintVersionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'domain_blueprint_id' => DomainBlueprint::factory(),
            'version' => 1,
            'journey_kind' => DomainJourneyKind::Relationship,
            'terminology' => [
                'journey' => 'Test journey',
                'creator_role' => 'initiator',
                'participant_role' => 'participant',
            ],
            'capabilities' => ['conversation', 'content'],
            'content_blueprint_slugs' => ['note-diary'],
            'guided_entry' => [
                'purpose_hint' => 'test',
                'creator_role' => 'initiator',
                'participant_role' => 'participant',
            ],
            'created_by_actor_id' => null,
            'content_hash' => str_repeat('0', 64),
            'published_at' => now(),
        ];
    }
}
