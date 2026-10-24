<?php

namespace App\Http\Controllers\Messaging;

use App\Actions\Messaging\OpenDirectConversation;
use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Support\AssetHttpResponse;
use App\Support\Messaging\Inbox;
use App\Support\Messaging\ThreadVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Small JSON answers for polling, private file delivery, and starting a private conversation. */
class MessagingController extends Controller
{
    public function show(Conversation $conversation): RedirectResponse
    {
        return redirect()->route('messages.index', ['c' => $conversation->uuid]);
    }

    /** "Anything new here?" — a version string that changes with messages, edits, reactions and read state. */
    public function state(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('view', Context::query()->findOrFail($conversation->context_id));
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)->where('actor_id', $user->actor?->id)
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subSeconds(30)))
            ->update(['last_seen_at' => now()]);

        return response()->json(['version' => app(ThreadVersion::class)->for($conversation)])
            ->header('Cache-Control', 'no-store');
    }

    public function unread(Request $request, Inbox $inbox): JsonResponse
    {
        return response()->json(['unread' => $inbox->unreadTotal($this->user($request))])->header('Cache-Control', 'no-store');
    }

    public function asset(Request $request, Conversation $conversation, ConversationMessage $message, Asset $asset): Response
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('view', Context::query()->findOrFail($conversation->context_id));
        abort_unless(
            (int) $message->conversation_id === (int) $conversation->id
            && ! $message->isDeleted()
            && $message->assets()->whereKey($asset->id)->exists()
            && Storage::disk($asset->disk)->exists($asset->storage_key),
            404,
        );

        return $request->boolean('download') ? AssetHttpResponse::download($asset) : AssetHttpResponse::inline($asset);
    }

    public function start(Request $request, Actor $actor, OpenDirectConversation $open): RedirectResponse
    {
        $conversation = $open->execute($this->user($request), $actor);

        return redirect()->route('messages.index', ['c' => $conversation->uuid]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
