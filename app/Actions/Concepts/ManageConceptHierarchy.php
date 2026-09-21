<?php

namespace App\Actions\Concepts;

use App\ConceptStatus;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptClosure;
use App\Models\ConceptHierarchyEdge;
use App\Models\ConceptScheme;
use App\Models\ConceptSchemeMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ManageConceptHierarchy
{
    public function __construct(private readonly RebuildConceptClosure $closure) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function add(
        User $user,
        ConceptScheme $scheme,
        Concept $parent,
        Concept $child,
        int $sortOrder = 0,
        array $metadata = [],
    ): ConceptHierarchyEdge {
        $scheme->loadMissing('vocabulary');
        Gate::forUser($user)->authorize('manage', $scheme->vocabulary);
        $creator = $this->creator($user);
        $parent = $parent->canonical();
        $child = $child->canonical();

        abort_if($parent->is($child), 422, 'A Concept cannot be its own hierarchy parent.');
        abort_unless(
            $parent->status === ConceptStatus::Active && $child->status === ConceptStatus::Active,
            422,
            'Hierarchy edges require active Concepts.',
        );

        return DB::transaction(function () use ($scheme, $parent, $child, $sortOrder, $metadata, $creator): ConceptHierarchyEdge {
            $lockedScheme = ConceptScheme::query()->lockForUpdate()->findOrFail($scheme->id);

            $this->assertMember($lockedScheme, $parent);
            $this->assertMember($lockedScheme, $child);

            abort_if(
                ConceptClosure::query()
                    ->where('scheme_id', $lockedScheme->id)
                    ->where('ancestor_concept_id', $child->id)
                    ->where('descendant_concept_id', $parent->id)
                    ->exists(),
                422,
                'Concept hierarchy edge would create a cycle.',
            );

            $edge = ConceptHierarchyEdge::query()->firstOrCreate(
                [
                    'scheme_id' => $lockedScheme->id,
                    'parent_concept_id' => $parent->id,
                    'child_concept_id' => $child->id,
                ],
                [
                    'sort_order' => max(0, $sortOrder),
                    'created_by_actor_id' => $creator->id,
                    'metadata' => $metadata,
                ],
            );

            $this->closure->execute($lockedScheme);

            return $edge->refresh();
        }, 3);
    }

    public function remove(User $user, ConceptHierarchyEdge $edge): void
    {
        $edge->loadMissing('scheme.vocabulary');
        Gate::forUser($user)->authorize('manage', $edge->scheme->vocabulary);

        DB::transaction(function () use ($edge): void {
            $lockedScheme = ConceptScheme::query()->lockForUpdate()->findOrFail($edge->scheme_id);
            $lockedEdge = ConceptHierarchyEdge::query()->lockForUpdate()->find($edge->id);

            if (! $lockedEdge instanceof ConceptHierarchyEdge) {
                return;
            }

            $lockedEdge->delete();
            $this->closure->execute($lockedScheme);
        }, 3);
    }

    private function assertMember(ConceptScheme $scheme, Concept $concept): void
    {
        abort_unless(
            ConceptSchemeMembership::query()
                ->where('scheme_id', $scheme->id)
                ->where('concept_id', $concept->id)
                ->exists(),
            422,
            'Both Concepts must belong to the Scheme before adding a hierarchy edge.',
        );
    }

    private function creator(User $user): Actor
    {
        $current = User::query()->with('actor')->findOrFail($user->id);
        abort_unless($current->actor instanceof Actor, 422, 'Concept governance requires an Actor identity.');

        return $current->actor;
    }
}
