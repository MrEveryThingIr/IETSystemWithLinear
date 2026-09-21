<?php

namespace App\Actions\Profile;

use App\Actions\Concepts\CreateConcept;
use App\Actions\Concepts\CreateConceptVocabulary;
use App\Actions\Concepts\SetConceptLabel;
use App\ConceptLabelKind;
use App\ConceptStatus;
use App\ConceptVocabularyScope;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\Concept;
use App\Models\ConceptLabel;
use App\Models\ConceptVocabulary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ResolveActorProfileConcept
{
    public function execute(User $user, ActorProfile $profile, string $label): Concept
    {
        Gate::forUser($user)->authorize('update', $profile);

        $label = Str::of($label)->squish()->toString();
        abort_if($label === '' || mb_strlen($label) > 120, 422, 'Concept label must be between 1 and 120 characters.');

        $normalized = Str::lower($label);
        $locale = $user->locale ?: app()->getLocale();

        $platformConcept = Concept::query()
            ->where('status', ConceptStatus::Active->value)
            ->whereHas('vocabulary', fn ($query) => $query
                ->where('scope_type', ConceptVocabularyScope::Platform->value)
                ->where('scope_id', 0))
            ->whereHas('labels', fn ($query) => $query->where('normalized_label', $normalized))
            ->with('labels')
            ->first();

        if ($platformConcept instanceof Concept) {
            return $platformConcept->canonical();
        }

        return DB::transaction(function () use ($user, $profile, $label, $normalized, $locale): Concept {
            $actor = Actor::query()->lockForUpdate()->findOrFail($profile->actor_id);
            $vocabulary = ConceptVocabulary::query()
                ->where('scope_type', ConceptVocabularyScope::Actor->value)
                ->where('scope_id', $actor->id)
                ->where('slug', 'profile-concepts')
                ->first();

            if (! $vocabulary instanceof ConceptVocabulary) {
                $vocabulary = app(CreateConceptVocabulary::class)->execute(
                    $user,
                    ConceptVocabularyScope::Actor,
                    'Profile Concepts',
                    'profile-concepts',
                    $actor,
                    ['purpose' => 'actor_profile'],
                );
            }

            $existingLabel = ConceptLabel::query()
                ->where('normalized_label', $normalized)
                ->whereHas('concept', fn ($query) => $query
                    ->where('vocabulary_id', $vocabulary->id)
                    ->where('status', ConceptStatus::Active->value))
                ->with('concept')
                ->first();

            if ($existingLabel instanceof ConceptLabel) {
                return $existingLabel->concept->canonical();
            }

            $baseSlug = Str::slug($label);
            $slug = ($baseSlug !== '' ? $baseSlug : 'concept').'-'.substr(hash('sha256', $normalized), 0, 10);

            $concept = app(CreateConcept::class)->execute($user, $vocabulary, $slug);
            app(SetConceptLabel::class)->execute(
                $user,
                $concept,
                $locale,
                $label,
                ConceptLabelKind::Preferred,
            );

            return $concept->load('labels');
        }, 3);
    }
}
