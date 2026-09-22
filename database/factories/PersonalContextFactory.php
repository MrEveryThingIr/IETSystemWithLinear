<?php

namespace Database\Factories;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\PersonalContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PersonalContext> */
class PersonalContextFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'context_id' => Context::factory()->state(['kind' => ContextKind::Personal]),
            'actor_id' => Actor::factory(),
        ];
    }
}
