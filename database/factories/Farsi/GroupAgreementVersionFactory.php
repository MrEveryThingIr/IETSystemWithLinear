<?php

namespace Database\Factories\Farsi;

use App\Models\GroupAgreementVersion;
use Database\Factories\GroupAgreementVersionFactory as BaseGroupAgreementVersionFactory;

class GroupAgreementVersionFactory extends BaseGroupAgreementVersionFactory
{
    protected $model = GroupAgreementVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_agreement_id' => GroupAgreementFactory::new(),
            'version' => 1,
            'content' => fake('fa_IR')->realText(600),
            'rationale' => null,
            'decision_note' => null,
            'status' => 'draft',
            'reacceptance_required' => true,
            'effective_from' => null,
            'effective_until' => null,
            'created_by_actor_id' => ActorFactory::new(),
            'approved_by_actor_id' => null,
            'approved_at' => null,
            'published_at' => null,
            'activated_at' => null,
            'superseded_by_version_id' => null,
        ];
    }

    public function clarificationRequested(): static
    {
        return $this->state(fn (): array => [
            'status' => 'clarification_requested',
            'decision_note' => fake('fa_IR')->realText(120),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'approved_by_actor_id' => ActorFactory::new(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'decision_note' => fake('fa_IR')->realText(120),
        ]);
    }
}
