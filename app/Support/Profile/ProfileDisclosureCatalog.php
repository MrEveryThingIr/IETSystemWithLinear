<?php

namespace App\Support\Profile;

use App\Models\ActorProfile;
use App\Models\Capability;
use App\Models\Interest;
use App\ProfileDisclosureItemKind;
use App\ProfileIntentStatus;
use App\Support\Categories\CategoryCatalog;

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

        $abilities = Capability::query()
            ->where('holder_actor_id', $profile->actor_id)
            ->active()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->limit(100)
            ->get();
        $interests = Interest::query()->heldBy((int) $profile->actor_id)->orderBy('id')->limit(100)->get();
        $labels = app(CategoryCatalog::class)->labels(
            $abilities->pluck('concept_id')->merge($interests->pluck('concept_id'))->map(fn ($id): int => (int) $id)->unique()->values()->all()
        );

        foreach ($abilities as $ability) {
            $items[] = [
                'key' => 'ability:'.$ability->uuid,
                'kind' => ProfileDisclosureItemKind::Ability->value,
                'label' => filled($ability->title) ? (string) $ability->title : ($labels[$ability->concept_id] ?? ''),
                'description' => __('items.abilities.one'),
            ];
        }

        foreach ($interests as $interest) {
            $items[] = [
                'key' => 'interest:'.$interest->uuid,
                'kind' => ProfileDisclosureItemKind::Interest->value,
                'label' => $labels[$interest->concept_id] ?? '',
                'description' => collect($interest->purposes)->map(fn (string $purpose): string => __('items.interests.purposes.'.$purpose))->implode(' · '),
            ];
        }

        $intents = $profile->intents()
            ->where('status', ProfileIntentStatus::Active->value)
            ->with('concept.labels')
            ->latest('updated_at')
            ->limit(100)
            ->get();

        foreach ($intents as $intent) {
            $items[] = [
                'key' => 'intent:'.$intent->uuid,
                'kind' => ProfileDisclosureItemKind::ProfileIntent->value,
                'label' => $intent->title ?: $intent->concept->displayLabel(),
                'description' => __('ui.profile.intents.kinds.'.$intent->kind->value),
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
