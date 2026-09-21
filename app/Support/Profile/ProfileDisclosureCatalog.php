<?php

namespace App\Support\Profile;

use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\Models\ActorProfile;
use App\ProfileDisclosureItemKind;
use App\ProfileIntentStatus;

class ProfileDisclosureCatalog
{
    /** @return list<array{key:string,kind:string,label:string,description:string}> */
    public function available(ActorProfile $profile): array
    {
        $items = [];

        foreach (ProfileFieldCatalog::shareable() as $field => $translationKey) {
            $value = $profile->getAttribute($field);

            if (! filled($value)) {
                continue;
            }

            $items[] = [
                'key' => 'field:'.$field,
                'kind' => ProfileDisclosureItemKind::Field->value,
                'label' => __($translationKey),
                'description' => str((string) $value)->limit(90)->toString(),
            ];
        }

        $assertions = \App\Models\ConceptAssertion::query()
            ->where('subject_type', ConceptAssertionSubject::Actor->value)
            ->where('subject_id', $profile->actor_id)
            ->whereIn('predicate', [
                ConceptAssertionPredicate::HasSkill->value,
                ConceptAssertionPredicate::InterestedIn->value,
                ConceptAssertionPredicate::WantsToLearn->value,
            ])
            ->with('concept.labels')
            ->orderBy('predicate')
            ->orderBy('id')
            ->get();

        foreach ($assertions as $assertion) {
            $items[] = [
                'key' => 'assertion:'.$assertion->uuid,
                'kind' => ProfileDisclosureItemKind::ConceptAssertion->value,
                'label' => $assertion->concept->displayLabel(),
                'description' => __('ui.profile.semantic_predicates.'.$assertion->predicate->value),
            ];
        }

        $intents = $profile->intents()
            ->where('status', ProfileIntentStatus::Active->value)
            ->with('concept.labels')
            ->latest('updated_at')
            ->get();

        foreach ($intents as $intent) {
            $items[] = [
                'key' => 'intent:'.$intent->uuid,
                'kind' => ProfileDisclosureItemKind::ProfileIntent->value,
                'label' => $intent->title ?: $intent->concept->displayLabel(),
                'description' => __('ui.profile.intent_kinds.'.$intent->kind->value),
            ];
        }

        return $items;
    }

    /** @return list<string> */
    public function validKeys(ActorProfile $profile): array
    {
        return array_column($this->available($profile), 'key');
    }

    public function kindForKey(string $key): ProfileDisclosureItemKind
    {
        return ProfileDisclosureItemKind::fromItemKey($key);
    }
}
