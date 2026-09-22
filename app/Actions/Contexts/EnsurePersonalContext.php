<?php

namespace App\Actions\Contexts;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\PersonalContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnsurePersonalContext
{
    public function execute(User $user): Context
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

        return DB::transaction(function () use ($current): Context {
            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            $binding = PersonalContext::query()
                ->with('context')
                ->where('actor_id', $actor->id)
                ->first();

            if ($binding instanceof PersonalContext) {
                return $binding->context;
            }

            $context = Context::query()->create([
                'uuid' => (string) Str::uuid(),
                'kind' => ContextKind::Personal,
            ]);

            PersonalContext::query()->create([
                'context_id' => $context->id,
                'actor_id' => $actor->id,
            ]);

            return $context->refresh();
        }, attempts: 3);
    }
}
