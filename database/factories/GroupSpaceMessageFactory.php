<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Conversation;
use App\Models\GroupSpaceMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @deprecated Compatibility factory for migrated GroupSpaceMessage callers.
 *
 * @extends Factory<GroupSpaceMessage>
 */
class GroupSpaceMessageFactory extends Factory
{
    protected $model = GroupSpaceMessage::class;

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
