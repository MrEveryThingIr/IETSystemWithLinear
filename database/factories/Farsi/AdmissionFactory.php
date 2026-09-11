<?php

namespace Database\Factories\Farsi;

use App\Models\Admission;
use Database\Factories\AdmissionFactory as BaseAdmissionFactory;

class AdmissionFactory extends BaseAdmissionFactory
{
    protected $model = Admission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => GroupFactory::new(),
            'candidate_actor_id' => ActorFactory::new(),
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

    public function clarificationRequired(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => 'clarification_required',
            'decision_note' => fake('fa_IR')->realText(120),
        ]);
    }

    public function rejected(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => 'rejected',
            'rejected_at' => now(),
            'decision_note' => fake('fa_IR')->realText(120),
        ]);
    }
}
