<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Context;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'context_id' => Context::factory(),
            'key' => 'main',
            'status' => 'active',
            'created_by_actor_id' => Actor::factory(),
        ];
    }
}
