<?php

namespace App\Support\Messaging;

use App\Models\Context;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The one authoritative thread of a Context (key "main"). Every surface (Messages inbox, Deal, Group, legacy
 * /contexts/.../conversation links) shows this same record through the same Thread component.
 */
final class ThreadFor
{
    public static function conversation(Context $context, User $viewer): Conversation
    {
        abort_unless(Gate::forUser($viewer)->allows('view', $context), 403);

        DB::table('conversations')->insertOrIgnore([
            'uuid' => (string) Str::uuid(),
            'context_id' => $context->id,
            'key' => 'main',
            'status' => 'active',
            'created_by_actor_id' => $viewer->actor?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Conversation::query()->where('context_id', $context->id)->where('key', 'main')->firstOrFail();
    }
}
