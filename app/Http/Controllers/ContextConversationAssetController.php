<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Context;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContextConversationAssetController extends Controller
{
    public function show(
        Request $request,
        Context $context,
        ConversationMessage $message,
        Asset $asset,
    ): StreamedResponse {
        $this->authorizeAsset($request, $context, $message, $asset);

        return Storage::disk($asset->disk)->response(
            $asset->storage_key,
            $asset->original_filename,
            [
                'Content-Type' => $asset->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            'inline',
        );
    }

    public function download(
        Request $request,
        Context $context,
        ConversationMessage $message,
        Asset $asset,
    ): StreamedResponse {
        $this->authorizeAsset($request, $context, $message, $asset);

        return Storage::disk($asset->disk)->download(
            $asset->storage_key,
            $asset->original_filename,
            [
                'Content-Type' => $asset->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function authorizeAsset(
        Request $request,
        Context $context,
        ConversationMessage $message,
        Asset $asset,
    ): void {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        Gate::forUser($user)->authorize('view', $context);

        $message->loadMissing('conversation');

        abort_unless(
            (int) $message->conversation->context_id === (int) $context->id
            && (int) $asset->context_id === (int) $context->id
            && $message->assets()->whereKey($asset->id)->exists(),
            404,
        );

        abort_unless(Storage::disk($asset->disk)->exists($asset->storage_key), 404);
    }
}
