<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\GroupSpaceParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupSpaceParticipant>
 */
class GroupSpaceParticipantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'group_space_id' => GroupSpace::factory()->restricted(),
            'actor_id' => Actor::factory(),
            'access' => 'allow',
            'role' => 'participant',
            'granted_by_actor_id' => Actor::factory(),
        ];
    }

    public function denied(): static
    {
        return $this->state(fn (): array => [
            'access' => 'deny',
            'role' => 'participant',
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (): array => [
            'access' => 'allow',
            'role' => 'manager',
        ]);
    }
}
