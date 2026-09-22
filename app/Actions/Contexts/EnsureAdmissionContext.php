<?php

namespace App\Actions\Contexts;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\AdmissionContext;
use App\Models\Context;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class EnsureAdmissionContext
{
    public function execute(Admission $admission, User $user): Context
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

        return DB::transaction(function () use ($admission, $current): Context {
            $locked = Admission::query()
                ->with('group')
                ->lockForUpdate()
                ->findOrFail($admission->id);

            Gate::forUser($current)->authorize('view', $locked);

            $binding = AdmissionContext::query()
                ->with('context')
                ->where('admission_id', $locked->id)
                ->first();

            if ($binding instanceof AdmissionContext) {
                return $binding->context;
            }

            $context = Context::query()->create([
                'uuid' => (string) Str::uuid(),
                'kind' => ContextKind::Admission,
            ]);

            AdmissionContext::query()->create([
                'context_id' => $context->id,
                'admission_id' => $locked->id,
            ]);

            return $context->refresh();
        }, attempts: 3);
    }
}
