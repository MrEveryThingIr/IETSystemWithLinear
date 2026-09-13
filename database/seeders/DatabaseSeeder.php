<?php

namespace Database\Seeders;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        app(GroupRoleProvisioner::class)->seedPermissions();

        $user = User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create([
                'username' => 'testuser',
                'email' => 'test@example.com',
            ]);

        $user->actor()->firstOrCreate([]);

        if (app()->environment('local')
            && ! $user->platformAccessGrants()->active()->where('role', PlatformRole::Superadmin->value)->exists()) {
            PlatformAccessGrant::factory()->for($user)->create([
                'role' => PlatformRole::Superadmin,
                'reason' => 'Local development bootstrap superadmin.',
            ]);
        }

        $this->call(ConstructionProjectSeeder::class);
    }
}
