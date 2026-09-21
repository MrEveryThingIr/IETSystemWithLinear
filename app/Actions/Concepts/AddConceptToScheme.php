<?php

namespace App\Actions\Concepts;

use App\ConceptStatus;
use App\Models\Concept;
use App\Models\ConceptScheme;
use App\Models\ConceptSchemeMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AddConceptToScheme
{
    public function __construct(private readonly RebuildConceptClosure $closure) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public function execute(
        User $user,
        ConceptScheme $scheme,
        Concept $concept,
        array $metadata = [],
    ): ConceptSchemeMembership {
        $scheme->loadMissing('vocabulary');
        Gate::forUser($user)->authorize('manage', $scheme->vocabulary);

        $canonical = $concept->canonical();
        abort_unless($canonical->status === ConceptStatus::Active, 422, 'Only active Concepts may join a Scheme.');
        abort_unless(
            (int) $canonical->vocabulary_id === (int) $scheme->vocabulary_id,
            422,
            'Concept and Scheme must belong to the same Vocabulary.',
        );

        return DB::transaction(function () use ($scheme, $canonical, $metadata): ConceptSchemeMembership {
            $lockedScheme = ConceptScheme::query()->lockForUpdate()->findOrFail($scheme->id);

            $membership = ConceptSchemeMembership::query()->firstOrCreate(
                [
                    'scheme_id' => $lockedScheme->id,
                    'concept_id' => $canonical->id,
                ],
                ['metadata' => $metadata],
            );

            $this->closure->execute($lockedScheme);

            return $membership->refresh();
        }, 3);
    }
}
