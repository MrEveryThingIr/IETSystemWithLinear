<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\ProfileIntentArrangementKind;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentSubjectKind;
use App\ProfileIntentScheduleKind;
use App\ProfileItemVisibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActorProfileIntent> */
class ActorProfileIntentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_profile_id' => ActorProfile::factory(),
            'concept_id' => Concept::factory(),
            'kind' => ProfileIntentKind::Need,
            'subject_kind' => ProfileIntentSubjectKind::Other,
            'arrangement_kind' => ProfileIntentArrangementKind::Other,
            'exchange_preference' => ProfileIntentExchangePreference::DiscussLater,
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'schedule_kind' => ProfileIntentScheduleKind::Once,
            'starts_on' => now()->toDateString(),
            'timezone' => 'UTC',
            'visibility' => ProfileItemVisibility::Inherited,
            'created_by_actor_id' => Actor::factory(),
            'metadata' => [],
        ];
    }

    public function offer(): static
    {
        return $this->state(fn (): array => ['kind' => ProfileIntentKind::Offer]);
    }

    public function weekly(): static
    {
        return $this->state(fn (): array => [
            'schedule_kind' => ProfileIntentScheduleKind::Weekly,
            'recurrence_interval' => 1,
            'recurrence_weekdays' => [6],
        ]);
    }
}
