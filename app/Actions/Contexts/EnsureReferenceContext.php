<?php

namespace App\Actions\Contexts;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\ReferenceContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnsureReferenceContext
{
    public function execute(
        User $manager,
        string $key,
        string $audience = ReferenceContext::AUDIENCE_AUTHENTICATED,
    ): Context {
        $key = strtolower(trim($key));
        $actor = $this->actor($manager);

        abort_unless(preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) === 1, 422);
        abort_unless($audience === ReferenceContext::AUDIENCE_AUTHENTICATED, 422);

        return DB::transaction(function () use ($actor, $key, $audience): Context {
            $binding = ReferenceContext::query()
                ->with('context')
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($binding instanceof ReferenceContext) {
                abort_unless((int) $binding->managed_by_actor_id === (int) $actor->id, 409);

                return $binding->context;
            }

            $context = Context::query()->create([
                'uuid' => (string) Str::uuid(),
                'kind' => ContextKind::Reference,
            ]);

            ReferenceContext::query()->create([
                'context_id' => $context->id,
                'key' => $key,
                'audience' => $audience,
                'managed_by_actor_id' => $actor->id,
            ]);

            return $context->refresh();
        }, attempts: 3);
    }

    private function actor(User $user): Actor
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

        return $current->actor;
    }
}
