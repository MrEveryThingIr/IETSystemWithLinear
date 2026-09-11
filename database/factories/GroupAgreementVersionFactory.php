<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupAgreementVersion>
 */
class GroupAgreementVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_agreement_id' => GroupAgreement::factory(),
            'version' => 1,
            'content' => fake()->paragraphs(3, true),
            'rationale' => null,
            'decision_note' => null,
            'status' => 'draft',
            'reacceptance_required' => true,
            'effective_from' => null,
            'effective_until' => null,
            'created_by_actor_id' => Actor::factory(),
            'approved_by_actor_id' => null,
            'approved_at' => null,
            'published_at' => null,
            'activated_at' => null,
            'superseded_by_version_id' => null,
        ];
    }

    public function proposed(): static
    {
        return $this->state(fn (): array => ['status' => 'proposed']);
    }

    public function clarificationRequested(): static
    {
        return $this->state(fn (): array => [
            'status' => 'clarification_requested',
            'decision_note' => fake()->sentence(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'approved_by_actor_id' => Actor::factory(),
            'approved_at' => now(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->approved()->state(fn (): array => [
            'status' => 'scheduled',
            'effective_from' => now()->addWeek(),
            'published_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->approved()->state(fn (): array => [
            'status' => 'active',
            'effective_from' => now()->subWeek(),
            'published_at' => now()->subWeek(),
            'activated_at' => now()->subWeek(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'decision_note' => fake()->sentence(),
        ]);
    }

    public function supersededBy(GroupAgreementVersion $replacement): static
    {
        return $this->active()->state(fn (): array => [
            'status' => 'superseded',
            'effective_until' => now(),
            'superseded_by_version_id' => $replacement->id,
        ]);
    }
}
