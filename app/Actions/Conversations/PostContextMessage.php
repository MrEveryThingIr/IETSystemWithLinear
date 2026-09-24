<?php

namespace App\Actions\Conversations;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\ContentEvidenceReference;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PostContextMessage
{
    /**
     * @param  list<int>  $assetIds
     * @param  list<int>  $evidenceReferenceIds
     */
    public function execute(
        Context $context,
        User $user,
        string $body,
        ?int $replyToMessageId = null,
        array $assetIds = [],
        array $evidenceReferenceIds = [],
    ): ConversationMessage {
        $body = trim($body);
        abort_if($body === '', 422, 'A message cannot be empty.');
        abort_if(mb_strlen($body) > 4000, 422, 'A message may not be longer than 4000 characters.');
        abort_if(count($assetIds) > 10 || count($evidenceReferenceIds) > 10, 422, 'A message may reference at most ten Assets and ten evidence references.');

        $current = $this->currentUser($user);
        $currentContext = Context::query()->findOrFail($context->id);

        Gate::forUser($current)->authorize('interactContent', $currentContext);

        return DB::transaction(function () use (
            $current,
            $currentContext,
            $body,
            $replyToMessageId,
            $assetIds,
            $evidenceReferenceIds,
        ): ConversationMessage {
            $now = now();

            DB::table('conversations')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'context_id' => $currentContext->id,
                'key' => 'main',
                'status' => 'active',
                'created_by_actor_id' => $current->actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $conversation = Conversation::query()
                ->where('context_id', $currentContext->id)
                ->where('key', 'main')
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($conversation->status === 'active', 422, 'This Conversation is not active.');

            $replyTo = null;
            if ($replyToMessageId !== null) {
                $replyTo = ConversationMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->whereKey($replyToMessageId)
                    ->first();
                abort_unless($replyTo instanceof ConversationMessage, 422, 'The message being replied to is not in this Conversation.');
            }

            $assets = Asset::query()
                ->where('context_id', $currentContext->id)
                ->whereIn('id', array_values(array_unique($assetIds)))
                ->get();
            abort_unless($assets->count() === count(array_unique($assetIds)), 422, 'Every attached Asset must belong to this Context.');

            $evidence = ContentEvidenceReference::query()
                ->where('context_id', $currentContext->id)
                ->whereIn('id', array_values(array_unique($evidenceReferenceIds)))
                ->get();
            abort_unless(
                $evidence->count() === count(array_unique($evidenceReferenceIds)),
                422,
                'Every evidence reference must belong to this Context.',
            );

            $message = $conversation->messages()->create([
                'author_actor_id' => $current->actor->id,
                'reply_to_message_id' => $replyTo?->id,
                'body' => $body,
            ]);

            if ($assets->isNotEmpty()) {
                $message->assets()->attach(
                    $assets->mapWithKeys(fn (Asset $asset): array => [
                        $asset->id => ['uuid' => (string) Str::uuid()],
                    ])->all(),
                );
            }

            if ($evidence->isNotEmpty()) {
                $message->evidenceReferences()->attach(
                    $evidence->mapWithKeys(fn (ContentEvidenceReference $reference): array => [
                        $reference->id => ['uuid' => (string) Str::uuid()],
                    ])->all(),
                );
            }

            return $message->fresh([
                'conversation.context',
                'author.user',
                'replyTo.author.user',
                'assets',
                'evidenceReferences.content',
                'evidenceReferences.revision',
            ]);
        }, attempts: 3);
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }
}
