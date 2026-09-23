<?php

namespace App\Http\Controllers;

use App\Models\ReferenceContext;
use App\Models\SpaceContent;
use App\Support\SystemManualContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SystemManualController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $binding = ReferenceContext::query()
            ->with('context')
            ->where('key', SystemManualContent::REFERENCE_KEY)
            ->firstOrFail();

        Gate::forUser($request->user())->authorize('view', $binding->context);

        $root = SpaceContent::query()
            ->where('context_id', $binding->context_id)
            ->where('status', 'published')
            ->whereHas('revisions', static fn ($query) => $query->where('title', SystemManualContent::ROOT_TITLE))
            ->orderBy('id')
            ->firstOrFail();

        return redirect()->route('contexts.contents.show', [$binding->context, $root]);
    }
}
