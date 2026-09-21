<?php

namespace App\Actions\Concepts;

use App\ConceptStatus;
use App\Models\Concept;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ManageConceptLifecycle
{
    public function deprecate(User $user, Concept $concept): Concept
    {
        $concept->loadMissing('vocabulary');
        Gate::forUser($user)->authorize('manage', $concept->vocabulary);

        return DB::transaction(function () use ($concept): Concept {
            $locked = Concept::query()->lockForUpdate()->findOrFail($concept->id);

            abort_if($locked->status === ConceptStatus::Merged, 422, 'Merged Concepts cannot be deprecated independently.');

            if ($locked->status !== ConceptStatus::Deprecated) {
                $locked->applyLifecycle([
                    'status' => ConceptStatus::Deprecated->value,
                    'merged_into_concept_id' => null,
                ]);
            }

            return $locked->refresh();
        }, 3);
    }

    public function merge(User $user, Concept $source, Concept $target): Concept
    {
        abort_if($source->is($target), 422, 'A Concept cannot be merged into itself.');
        abort_unless(
            (int) $source->vocabulary_id === (int) $target->vocabulary_id,
            422,
            'Concepts can only be merged within the same Vocabulary.',
        );

        $source->loadMissing('vocabulary');
        Gate::forUser($user)->authorize('manage', $source->vocabulary);

        return DB::transaction(function () use ($source, $target): Concept {
            $locked = Concept::query()
                ->whereIn('id', [$source->id, $target->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedSource = $locked->get($source->id);
            $lockedTarget = $locked->get($target->id);

            abort_unless($lockedSource instanceof Concept && $lockedTarget instanceof Concept, 404);

            $canonicalTarget = $lockedTarget->canonical();
            abort_if($lockedSource->is($canonicalTarget), 422, 'Concept merge would create a merge cycle.');
            abort_unless($canonicalTarget->status === ConceptStatus::Active, 422, 'Merge target must resolve to an active Concept.');

            if ($lockedSource->status === ConceptStatus::Merged) {
                abort_unless(
                    (int) $lockedSource->merged_into_concept_id === (int) $canonicalTarget->id,
                    422,
                    'Concept is already merged into a different target.',
                );

                return $lockedSource;
            }

            $lockedSource->applyLifecycle([
                'status' => ConceptStatus::Merged->value,
                'merged_into_concept_id' => $canonicalTarget->id,
            ]);

            return $lockedSource->refresh();
        }, 3);
    }
}
