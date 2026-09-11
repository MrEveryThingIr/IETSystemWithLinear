<?php

namespace Database\Factories\Farsi;

use App\Models\Group;
use Database\Factories\GroupFactory as BaseGroupFactory;

class GroupFactory extends BaseGroupFactory
{
    protected $model = Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'پروژه عمرانی '.fake('fa_IR')->unique()->cityName(),
            'description' => fake('fa_IR')->realText(300),
            'created_by_actor_id' => ActorFactory::new(),
        ];
    }
}
