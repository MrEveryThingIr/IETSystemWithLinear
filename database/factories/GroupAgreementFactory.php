<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupAgreement>
 */
class GroupAgreementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'name' => fake()->randomElement(['Project participation terms', 'Site safety agreement', 'Reporting protocol']),
            'required_for_admission' => true,
        ];
    }

    public function optional(): static
    {
        return $this->state(fn (): array => ['required_for_admission' => false]);
    }
}
