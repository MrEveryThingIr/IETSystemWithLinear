<?php

namespace App\Actions\Accounting;

use App\AccountType;
use App\Models\Account;
use App\Models\Actor;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateLedgerAccount
{
    public function execute(
        Ledger $ledger,
        User $user,
        string $name,
        AccountType $type,
        ?string $systemKey = null,
    ): Account {
        Gate::forUser($user)->authorize('manage', $ledger);

        $name = trim($name);
        abort_if($name === '' || mb_strlen($name) > 180, 422, 'Invalid Account name.');

        if ($systemKey !== null) {
            $existing = $ledger->accounts()->where('system_key', $systemKey)->first();
            if ($existing instanceof Account) {
                abort_unless($existing->type === $type, 422, 'System Account type mismatch.');

                return $existing;
            }
        } else {
            $existing = $ledger->accounts()
                ->where('type', $type->value)
                ->where('name', $name)
                ->first();

            if ($existing instanceof Account) {
                return $existing;
            }
        }

        $actor = $this->actor($user);
        $base = $systemKey !== null ? Str::slug($systemKey, '_') : Str::slug($name, '_');
        $base = $base !== '' ? mb_substr($base, 0, 60) : 'account';
        $code = $base;
        $suffix = 2;

        while ($ledger->accounts()->where('code', $code)->exists()) {
            $code = mb_substr($base, 0, 70)."_{$suffix}";
            $suffix++;
        }

        return $ledger->accounts()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'system_key' => $systemKey,
            'created_by_actor_id' => $actor->id,
        ]);
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
