<?php

namespace App\Http\Controllers;

use App\Models\ReferenceContext;
use App\Models\SpaceContent;
use App\Support\SystemManualContent;
use App\Support\SystemManualHelpMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SystemManualController extends Controller
{
    public function __invoke(Request $request, SystemManualHelpMap $help): RedirectResponse
    {
        $binding = ReferenceContext::query()
            ->with('context')
            ->where('key', SystemManualContent::REFERENCE_KEY)
            ->firstOrFail();

        Gate::forUser($request->user())->authorize('view', $binding->context);

        $topic = $request->string('topic')->trim()->toString();
        $title = $help->chapterTitle($topic) ?? SystemManualContent::ROOT_TITLE;

        $target = SpaceContent::query()
            ->where('context_id', $binding->context_id)
            ->where('status', 'published')
            ->whereHas('revisions', static fn ($query) => $query->where('title', $title))
            ->orderBy('id')
            ->first();

        if (! $target instanceof SpaceContent && $title !== SystemManualContent::ROOT_TITLE) {
            $target = SpaceContent::query()
                ->where('context_id', $binding->context_id)
                ->where('status', 'published')
                ->whereHas('revisions', static fn ($query) => $query->where('title', SystemManualContent::ROOT_TITLE))
                ->orderBy('id')
                ->first();
        }

        abort_unless($target instanceof SpaceContent, 404);

        $url = route('contexts.contents.show', [
            $binding->context,
            $target,
            'manual' => 1,
        ]);

        if ($title !== SystemManualContent::ROOT_TITLE) {
            $url .= '#field-how_to_use';
        }

        return redirect()->to($url);
    }
}
