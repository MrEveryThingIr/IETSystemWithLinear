<?php

namespace App\Support\Profile;

use App\ConceptAssertionSubject;
use App\Models\ActorProfileDisclosureGrant;
use App\Models\ActorProfileIntent;
use App\Models\ConceptAssertion;
use App\Models\User;
use App\ProfileDisclosureItemKind;
use App\ProfileIntentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ProfileDisclosureResolver
{
    /**
     * @return array{
     *     fields:list<array{key:string,label:string,value:string}>,
     *     assertions:Collection<int, ConceptAssertion>,
     *     intents:Collection<int, ActorProfileIntent>
     * }
     */
    public function resolve(User $viewer, ActorProfileDisclosureGrant $grant): array
    {
        Gate::forUser($viewer)->authorize('view', $grant);

        $grant->loadMissing(['profile.actor', 'items']);
        $fieldDefinitions = ProfileFieldCatalog::shareable();
        $fields = [];
        $assertionUuids = [];
        $intentUuids = [];

        foreach ($grant->items as $item) {
            switch ($item->kind) {
                case ProfileDisclosureItemKind::Field:
                    $field = str($item->item_key)->after('field:')->toString();

                    if (! array_key_exists($field, $fieldDefinitions)) {
                        break;
                    }

                    $value = $grant->profile->getAttribute($field);

                    if (filled($value)) {
                        $fields[] = [
                            'key' => $field,
                            'label' => __($fieldDefinitions[$field]),
                            'value' => (string) $value,
                        ];
                    }

                    break;

                case ProfileDisclosureItemKind::ConceptAssertion:
                    $assertionUuids[] = str($item->item_key)->after('assertion:')->toString();

                    break;

                case ProfileDisclosureItemKind::ProfileIntent:
                    $intentUuids[] = str($item->item_key)->after('intent:')->toString();

                    break;
            }
        }

        $assertions = ConceptAssertion::query()
            ->where('subject_type', ConceptAssertionSubject::Actor->value)
            ->where('subject_id', $grant->profile->actor_id)
            ->whereIn('uuid', array_values(array_unique($assertionUuids)))
            ->where(function ($query): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->with('concept.labels')
            ->orderBy('id')
            ->get();

        $intents = ActorProfileIntent::query()
            ->where('actor_profile_id', $grant->profile->getKey())
            ->where('status', ProfileIntentStatus::Active->value)
            ->whereIn('uuid', array_values(array_unique($intentUuids)))
            ->with('concept.labels')
            ->orderBy('id')
            ->get();

        return [
            'fields' => $fields,
            'assertions' => $assertions,
            'intents' => $intents,
        ];
    }
}
