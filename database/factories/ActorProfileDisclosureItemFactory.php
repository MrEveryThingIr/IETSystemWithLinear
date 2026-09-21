<?php

namespace Database\Factories;

use App\Models\ActorProfileDisclosureGrant;
use App\Models\ActorProfileDisclosureItem;
use App\ProfileDisclosureItemKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActorProfileDisclosureItem> */
class ActorProfileDisclosureItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grant_id' => ActorProfileDisclosureGrant::factory(),
            'kind' => ProfileDisclosureItemKind::Field,
            'item_key' => 'field:display_name',
        ];
    }
}
