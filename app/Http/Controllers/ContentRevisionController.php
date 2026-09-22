<?php

namespace App\Http\Controllers;

use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContentRevisionController extends Controller
{
    public function __invoke(
        Request $request,
        Context $context,
        SpaceContent $content,
        SpaceContentRevision $revision,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless((int) $content->context_id === (int) $context->id, 404);
        abort_unless((int) $revision->space_content_id === (int) $content->id, 404);
        abort_unless($revision->hasVerifiableManifest(), 404);

        Gate::forUser($user)->authorize('view', $content);

        return redirect()->route(
            'contexts.contents.show',
            [$context, $content, 'revision' => $revision->uuid],
        );
    }
}
