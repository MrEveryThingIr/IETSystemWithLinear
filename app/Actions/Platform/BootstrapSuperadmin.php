<?php

namespace App\Actions\Platform;

use App\Models\Actor;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BootstrapSuperadmin
{
    public function execute(string $email, ?string $username = null, ?string $password = null): PlatformAccessGrant
    {
        return Cache::lock('platform:bootstrap-superadmin', 15)->block(5, function () use ($email, $username, $password): PlatformAccessGrant {
            return DB::transaction(function () use ($email, $username, $password): PlatformAccessGrant {
                $alreadyBootstrapped = PlatformAccessGrant::query()
                    ->where('role', PlatformRole::Superadmin)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyBootstrapped) {
                    throw new DomainException('An active platform administrator already exists.');
                }

                $user = User::query()->where('email', $email)->lockForUpdate()->first();

                if (! $user instanceof User) {
                    if ($username === null || $password === null) {
                        throw new DomainException('A username and password are required to create the first administrator.');
                    }

                    $user = new User;
                    $user->username = $username;
                    $user->email = $email;
                    $user->email_verified_at = now();
                    $user->password = $password;
                    $user->save();
                } elseif ($user->status !== 'active') {
                    throw new DomainException('The selected account must be active.');
                } elseif ($user->email_verified_at === null) {
                    $user->email_verified_at = now();
                    $user->save();
                }

                if (! $user->actor()->exists()) {
                    $actor = new Actor;
                    $actor->user()->associate($user);
                    $actor->save();
                }

                $grant = new PlatformAccessGrant;
                $grant->user()->associate($user);
                $grant->role = PlatformRole::Superadmin;
                $grant->granted_at = now();
                $grant->reason = 'Initial platform administrator bootstrap.';
                $grant->correlation_id = (string) Str::uuid();
                $grant->save();

                return $grant;
            }, attempts: 3);
        });
    }
}
