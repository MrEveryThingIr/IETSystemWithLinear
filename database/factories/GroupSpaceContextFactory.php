<?php

namespace Database\Factories;

use App\ContextKind;
use App\Models\Context;
use App\Models\GroupSpace;
use App\Models\GroupSpaceContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GroupSpaceContext> */
class GroupSpaceContextFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'context_id' => Context::factory()->state(['kind' => ContextKind::GroupSpace]),
            'group_space_id' => GroupSpace::factory(),
        ];
    }
}
