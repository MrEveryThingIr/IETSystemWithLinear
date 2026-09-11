<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\Group;
use App\Models\GroupInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admission>
 */
class AdmissionFactory extends Factory
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
            'candidate_actor_id' => Actor::factory(),
            'source_invitation_id' => null,
            'status' => 'draft',
            'submitted_at' => null,
            'approved_at' => null,
            'finalized_at' => null,
            'rejected_at' => null,
            'cancelled_at' => null,
            'decision_note' => null,
        ];
    }

    public function fromInvitation(GroupInvitation $invitation): static
    {
        return $this->state(fn (): array => [
            'group_id' => $invitation->group_id,
            'source_invitation_id' => $invitation->id,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => ['status' => 'submitted', 'submitted_at' => now()->subDays(2)]);
    }

    public function underReview(): static
    {
        return $this->submitted()->state(fn (): array => ['status' => 'under_review']);
    }

    public function clarificationRequired(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => 'clarification_required',
            'decision_note' => fake()->sentence(),
        ]);
    }

    public function approved(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => 'approved',
            'approved_at' => now()->subDay(),
        ]);
    }

    public function finalized(): static
    {
        return $this->approved()->state(fn (): array => [
            'status' => 'finalized',
            'finalized_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => 'rejected',
            'rejected_at' => now(),
            'decision_note' => fake()->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}
