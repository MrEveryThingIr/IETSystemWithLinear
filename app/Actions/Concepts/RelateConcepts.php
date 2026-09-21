<?php

namespace App\Actions\Concepts;

use App\ConceptAssertionSource;
use App\ConceptStatus;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptRelation;
use App\Models\ConceptRelationType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RelateConcepts
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function execute(
        User $user,
        Concept $from,
        string $relationTypeKey,
        Concept $to,
        ConceptAssertionSource $source = ConceptAssertionSource::Manual,
        ?float $weight = null,
        ?float $confidence = null,
        array $metadata = [],
    ): ConceptRelation {
        $from = $from->canonical();
        $to = $to->canonical();

        abort_if($from->is($to), 422, 'A Concept relation must connect distinct Concepts.');
        abort_unless(
            $from->status === ConceptStatus::Active && $to->status === ConceptStatus::Active,
            422,
            'Concept relations require active Concepts.',
        );
        abort_if($confidence !== null && ($confidence < 0 || $confidence > 1), 422, 'Confidence must be between 0 and 1.');

        $from->loadMissing('vocabulary');
        $to->loadMissing('vocabulary');
        Gate::forUser($user)->authorize('manage', $from->vocabulary);

        if (! $from->vocabulary->is($to->vocabulary)) {
            Gate::forUser($user)->authorize('manage', $to->vocabulary);
        }

        $type = ConceptRelationType::query()
            ->where('key', $relationTypeKey)
            ->where('status', 'active')
            ->firstOrFail();

        if ($type->symmetric && $from->id > $to->id) {
            [$from, $to] = [$to, $from];
        }

        $creator = $this->creator($user);

        return ConceptRelation::query()->firstOrCreate(
            [
                'from_concept_id' => $from->id,
                'relation_type_id' => $type->id,
                'to_concept_id' => $to->id,
            ],
            [
                'weight' => $weight,
                'confidence' => $confidence,
                'source' => $source,
                'created_by_actor_id' => $creator->id,
                'metadata' => $metadata,
            ],
        );
    }

    private function creator(User $user): Actor
    {
        $current = User::query()->with('actor')->findOrFail($user->id);
        abort_unless($current->actor instanceof Actor, 422, 'Concept governance requires an Actor identity.');

        return $current->actor;
    }
}
