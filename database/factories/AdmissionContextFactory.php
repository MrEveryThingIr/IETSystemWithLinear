<?php

namespace Database\Factories;

use App\ContextKind;
use App\Models\Admission;
use App\Models\AdmissionContext;
use App\Models\Context;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdmissionContext> */
class AdmissionContextFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'context_id' => Context::factory()->state(['kind' => ContextKind::Admission]),
            'admission_id' => Admission::factory(),
        ];
    }
}
