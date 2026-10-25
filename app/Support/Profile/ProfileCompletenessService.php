<?php

namespace App\Support\Profile;

use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\Capability;
use App\Models\ConceptAssertion;
use App\Models\Interest;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\ProfileRequirementKind;

class ProfileCompletenessService
{
    /** @return list<ProfileRequirement> */
    public function recommendedRequirements(): array
    {
        return [
            ProfileRequirement::field('display_name', __('ui.profile.display_name')),
            ProfileRequirement::field('headline', __('ui.profile.headline')),
            ProfileRequirement::field('bio', __('ui.profile.bio')),
            ProfileRequirement::concept(ConceptAssertionPredicate::HasSkill, __('items.abilities.one')),
            ProfileRequirement::activeIntent(ProfileIntentKind::Need, __('ui.profile.intents.kinds.need')),
            ProfileRequirement::activeIntent(ProfileIntentKind::Offer, __('ui.profile.intents.kinds.offer')),
        ];
    }

    /**
     * @param  list<ProfileRequirement>  $requirements
     * @return array{completed:int,total:int,percent:int,items:list<array{kind:string,key:string,label:string,satisfied:bool}>}
     */
    public function summarize(ActorProfile $profile, array $requirements): array
    {
        $predicates = ConceptAssertion::query()
            ->where('subject_type', ConceptAssertionSubject::Actor->value)
            ->where('subject_id', $profile->actor_id)
            ->where(function ($query): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->get(['predicate'])
            ->map(fn (ConceptAssertion $assertion): string => $assertion->predicate->value)
            ->unique()
            ->values()
            ->all();

        // Abilities and interests are the home of what used to be skill and interest tags.
        if (Capability::query()->where('holder_actor_id', $profile->actor_id)->active()->exists()) {
            $predicates[] = ConceptAssertionPredicate::HasSkill->value;
        }
        foreach (Interest::query()->heldBy((int) $profile->actor_id)->get(['purposes']) as $interest) {
            $predicates[] = $interest->hasPurpose('learn')
                ? ConceptAssertionPredicate::WantsToLearn->value
                : ConceptAssertionPredicate::InterestedIn->value;
        }

        $intentKinds = $profile->intents()
            ->where('status', ProfileIntentStatus::Active->value)
            ->get(['kind'])
            ->map(fn (ActorProfileIntent $intent): string => $intent->kind->value)
            ->unique()
            ->values()
            ->all();

        $items = [];
        $completed = 0;

        foreach ($requirements as $requirement) {
            $satisfied = match ($requirement->kind) {
                ProfileRequirementKind::Field => filled($profile->getAttribute($requirement->key)),
                ProfileRequirementKind::ConceptPredicate => in_array($requirement->key, $predicates, true),
                ProfileRequirementKind::ActiveIntent => in_array($requirement->key, $intentKinds, true),
            };

            if ($satisfied) {
                $completed++;
            }

            $items[] = [
                'kind' => $requirement->kind->value,
                'key' => $requirement->key,
                'label' => $requirement->label,
                'satisfied' => $satisfied,
            ];
        }

        $total = count($requirements);

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $total === 0 ? 100 : (int) round(($completed / $total) * 100),
            'items' => $items,
        ];
    }
}
