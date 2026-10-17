<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\Support\AssetHttpResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Presentation over Assets attached to a Plan occurrence as evidence.
 *
 * The occurrence/Asset link stays authoritative; nothing is copied into
 * Content. Every request re-checks the full chain:
 * occurrence ∈ plan, asset linked to occurrence, asset ∈ plan Context,
 * viewer may see the plan, stored file exists.
 */
class PlanOccurrenceAssetController extends Controller
{
    public function show(Request $request, Plan $plan, PlanOccurrence $occurrence, Asset $asset): Response
    {
        $this->authorizeAsset($request, $plan, $occurrence, $asset);

        return AssetHttpResponse::inline($asset);
    }

    public function download(Request $request, Plan $plan, PlanOccurrence $occurrence, Asset $asset): Response
    {
        $this->authorizeAsset($request, $plan, $occurrence, $asset);

        return AssetHttpResponse::download($asset);
    }

    public function evidence(Request $request, Plan $plan, PlanOccurrence $occurrence, Asset $asset): View
    {
        $this->authorizeAsset($request, $plan, $occurrence, $asset);

        $link = $occurrence->assets()->whereKey($asset->id)->firstOrFail();
        $addedBy = Actor::query()
            ->with(['user', 'profile'])
            ->find($link->pivot->added_by_actor_id);

        return view('planner.evidence', [
            'plan' => $plan,
            'occurrence' => $occurrence,
            'asset' => $asset,
            'addedBy' => $addedBy,
            'attachedAt' => $link->pivot->created_at,
            'inlineUrl' => route('planner.occurrences.assets.show', [$plan, $occurrence, $asset]),
            'downloadUrl' => route('planner.occurrences.assets.download', [$plan, $occurrence, $asset]),
        ]);
    }

    private function authorizeAsset(Request $request, Plan $plan, PlanOccurrence $occurrence, Asset $asset): void
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        Gate::forUser($user)->authorize('view', $plan);

        // Links that do not belong together are indistinguishable from missing ones.
        abort_unless(
            (int) $occurrence->plan_id === (int) $plan->id
            && (int) $asset->context_id === (int) $plan->context_id
            && $occurrence->assets()->whereKey($asset->id)->exists(),
            404,
        );

        abort_unless(Storage::disk($asset->disk)->exists($asset->storage_key), 404);
    }
}
