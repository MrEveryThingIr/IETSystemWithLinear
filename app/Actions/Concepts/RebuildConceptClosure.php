<?php

namespace App\Actions\Concepts;

use App\Models\ConceptHierarchyEdge;
use App\Models\ConceptScheme;
use App\Models\ConceptSchemeMembership;
use Illuminate\Support\Facades\DB;

class RebuildConceptClosure
{
    public function execute(ConceptScheme $scheme): void
    {
        $conceptIds = ConceptSchemeMembership::query()
            ->where('scheme_id', $scheme->id)
            ->pluck('concept_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $adjacency = [];
        ConceptHierarchyEdge::query()
            ->where('scheme_id', $scheme->id)
            ->get(['parent_concept_id', 'child_concept_id'])
            ->each(function (ConceptHierarchyEdge $edge) use (&$adjacency): void {
                $adjacency[(int) $edge->parent_concept_id][] = (int) $edge->child_concept_id;
            });

        $rows = [];

        foreach ($conceptIds as $ancestorId) {
            $depths = [$ancestorId => 0];
            $queue = [[$ancestorId, 0]];
            $offset = 0;

            while (isset($queue[$offset])) {
                [$currentId, $depth] = $queue[$offset];
                $offset++;

                foreach ($adjacency[$currentId] ?? [] as $childId) {
                    $nextDepth = $depth + 1;

                    if (isset($depths[$childId]) && $depths[$childId] <= $nextDepth) {
                        continue;
                    }

                    $depths[$childId] = $nextDepth;
                    $queue[] = [$childId, $nextDepth];
                }
            }

            foreach ($depths as $descendantId => $depth) {
                $rows[] = [
                    'scheme_id' => $scheme->id,
                    'ancestor_concept_id' => $ancestorId,
                    'descendant_concept_id' => $descendantId,
                    'min_depth' => $depth,
                ];
            }
        }

        DB::table('concept_closure')->where('scheme_id', $scheme->id)->delete();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('concept_closure')->insert($chunk);
        }
    }
}
