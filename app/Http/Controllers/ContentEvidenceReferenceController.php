<?php

namespace App\Http\Controllers;

use App\Models\ContentEvidenceReference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContentEvidenceReferenceController extends Controller
{
    public function __invoke(Request $request, ContentEvidenceReference $reference): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $reference->loadMissing(['context', 'content']);
        Gate::forUser($user)->authorize('view', $reference->content);

        return redirect()->route(
            'contexts.contents.show',
            [$reference->context, $reference->content, 'evidence' => $reference->uuid],
        );
    }
}
