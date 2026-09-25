<?php

namespace Database\Seeders;

use App\Actions\Auth\ProvisionVerifiedUserDefaults;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(ProvisionVerifiedUserDefaults $provision): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create([
                'username' => 'testuser',
                'email' => 'test@example.com',
            ]);

        $user->actor()->firstOrCreate([]);
        $provision->execute($user->refresh());

        if (! $user->platformAccessGrants()->active()->where('role', PlatformRole::Superadmin->value)->exists()) {
            PlatformAccessGrant::factory()->for($user)->create([
                'role' => PlatformRole::Superadmin,
                'reason' => 'Local development bootstrap superadmin.',
            ]);
        }

        $this->call(SystemManualSeeder::class);
    }
}
