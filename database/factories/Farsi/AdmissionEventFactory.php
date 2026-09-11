<?php

namespace Database\Factories\Farsi;

use App\Models\AdmissionEvent;
use Database\Factories\AdmissionEventFactory as BaseAdmissionEventFactory;

class AdmissionEventFactory extends BaseAdmissionEventFactory
{
    protected $model = AdmissionEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_id' => AdmissionFactory::new(),
            'actor_id' => ActorFactory::new(),
            'event' => 'admission.created_from_invitation',
            'note' => fake('fa_IR')->optional()->realText(100),
            'metadata' => null,
        ];
    }
}
