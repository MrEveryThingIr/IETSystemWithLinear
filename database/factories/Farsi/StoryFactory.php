<?php

namespace Database\Factories\Farsi;

use App\Models\Story;
use Database\Factories\StoryFactory as BaseStoryFactory;

class StoryFactory extends BaseStoryFactory
{
    protected $model = Story::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => GroupFactory::new(),
            'created_by_actor_id' => ActorFactory::new(),
            'title' => fake('fa_IR')->realText(60),
            'body' => fake('fa_IR')->realText(800),
        ];
    }
}
