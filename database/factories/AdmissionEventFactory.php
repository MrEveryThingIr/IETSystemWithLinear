<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\AdmissionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionEvent>
 */
class AdmissionEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory(),
            'actor_id' => Actor::factory(),
            'event' => 'admission.created_from_invitation',
            'note' => fake()->optional()->sentence(),
            'metadata' => null,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => ['actor_id' => null]);
    }
}
