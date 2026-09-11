<?php

namespace Database\Factories\Farsi;

use App\Models\Actor;
use Database\Factories\ActorFactory as BaseActorFactory;

class ActorFactory extends BaseActorFactory
{
    protected $model = Actor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => UserFactory::new()];
    }
}
