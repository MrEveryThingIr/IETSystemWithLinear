<?php

namespace Database\Factories\Farsi;

use App\Models\Responsibility;
use App\Models\StoryRole;
use Database\Factories\StoryRoleFactory as BaseStoryRoleFactory;

class StoryRoleFactory extends BaseStoryRoleFactory
{
    protected $model = StoryRole::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => StoryFactory::new(),
            'actor_id' => ActorFactory::new(),
            'responsibility' => fake()->randomElement(Responsibility::values()),
        ];
    }
}
