<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConversationMessage> */
class ConversationMessageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'author_actor_id' => Actor::factory(),
            'reply_to_message_id' => null,
            'body' => fake()->paragraph(),
        ];
    }
}
