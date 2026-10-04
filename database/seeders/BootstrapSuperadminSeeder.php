<?php

namespace Database\Seeders;

use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapSuperadminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $username = (string) config('bootstrap.superadmin.username', 'MrEveryThing');
        $email = (string) config('bootstrap.superadmin.email', 'mreverything@example.test');
        $password = (string) config('bootstrap.superadmin.password', 'password');

        $user = User::query()->where('username', $username)->first();

        if (! $user instanceof User) {
            $user = new User;
        }

        $user->forceFill([
            'username' => $username,
            'email' => $email,
            'email_verified_at' => now(),
            'status' => 'active',
            'password' => Hash::make($password),
        ])->save();

        $user->actor()->firstOrCreate([]);

        if (! $user->platformAccessGrants()
            ->active()
            ->where('role', PlatformRole::Superadmin->value)
            ->exists()) {
            $grant = new PlatformAccessGrant;
            $grant->user()->associate($user);
            $grant->role = PlatformRole::Superadmin;
            $grant->granted_at = now();
            $grant->reason = 'Local fresh-start bootstrap superadmin.';
            $grant->correlation_id = (string) Str::uuid();
            $grant->save();
        }

        $this->command?->info('Fresh-start superadmin ready.');
        $this->command?->line('Username: '.$username);
        $this->command?->line('Email: '.$email);
    }
}
