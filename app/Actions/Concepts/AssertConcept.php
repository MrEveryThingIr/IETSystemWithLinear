<?php

namespace App\Actions\Concepts;

use App\ConceptAssertionPredicate;
use App\ConceptAssertionSource;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\ConceptStatus;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptAssertion;
use App\Models\ConceptScheme;
use App\Models\ConceptSchemeMembership;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class AssertConcept
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        User $user,
        Model $subject,
        Concept $concept,
        ConceptAssertionPredicate $predicate,
        ?ConceptScheme $scheme = null,
        ConceptAssertionSource $source = ConceptAssertionSource::Manual,
        ?ConceptAssertionVisibility $visibility = null,
        ?float $weight = null,
        ?float $confidence = null,
        ?Carbon $validFrom = null,
        ?Carbon $validUntil = null,
        array $metadata = [],
    ): ConceptAssertion {
        Gate::forUser($user)->authorize('create', [ConceptAssertion::class, $subject]);

        $subjectType = ConceptAssertionSubject::fromModel($subject);
        $concept = $concept->canonical();

        abort_unless($concept->status === ConceptStatus::Active, 422, 'New assertions require an active Concept.');
        abort_if($confidence !== null && ($confidence < 0 || $confidence > 1), 422, 'Confidence must be between 0 and 1.');
        abort_if(
            $validFrom !== null && $validUntil !== null && $validUntil->lessThanOrEqualTo($validFrom),
            422,
            'Concept assertion validity end must be after its start.',
        );

        if ($subject instanceof SpaceContentRevision) {
            abort_if($subject->hasVerifiableManifest(), 409, 'Published revision-bound Concept assertions are immutable evidence.');
        }

        if ($scheme instanceof ConceptScheme) {
            abort_unless(
                ConceptSchemeMembership::query()
                    ->where('scheme_id', $scheme->id)
                    ->where('concept_id', $concept->id)
                    ->exists(),
                422,
                'Concept must belong to the selected Scheme.',
            );
        }

        $existing = ConceptAssertion::query()
            ->where('subject_type', $subjectType->value)
            ->where('subject_id', $subject->getKey())
            ->where('concept_id', $concept->id)
            ->where('predicate', $predicate->value)
            ->first();

        if ($existing instanceof ConceptAssertion) {
            abort_if(
                $scheme instanceof ConceptScheme && (int) $existing->scheme_id !== (int) $scheme->id,
                409,
                'Concept assertion already exists with a different Scheme context.',
            );

            return $existing;
        }

        $creator = $this->creator($user);
        $visibility ??= $subject instanceof Actor
            ? ConceptAssertionVisibility::Private
            : ConceptAssertionVisibility::Inherited;

        return ConceptAssertion::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subject->getKey(),
            'concept_id' => $concept->id,
            'predicate' => $predicate,
            'scheme_id' => $scheme?->id,
            'weight' => $weight,
            'confidence' => $confidence,
            'source' => $source,
            'visibility' => $visibility,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'created_by_actor_id' => $creator->id,
            'metadata' => $metadata,
        ]);
    }

    private function creator(User $user): Actor
    {
        $current = User::query()->with('actor')->findOrFail($user->id);
        abort_unless($current->actor instanceof Actor, 422, 'Concept assertions require an Actor identity.');

        return $current->actor;
    }
}
