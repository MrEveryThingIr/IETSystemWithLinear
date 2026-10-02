<?php

namespace Database\Seeders;

use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        Model::withoutEvents(function (): void {
            $user = User::query()->where('email', 'test@example.com')->first()
                ?? User::factory()->create([
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ]);

            $user->actor()->firstOrCreate([]);

            if (! $user->platformAccessGrants()->active()->where('role', PlatformRole::Superadmin->value)->exists()) {
                PlatformAccessGrant::factory()->for($user)->create([
                    'role' => PlatformRole::Superadmin,
                    'reason' => 'Local development bootstrap superadmin.',
                ]);
            }

            if ((string) config('release.profile') !== 'planning_baseline') {
                $this->call(SystemManualSeeder::class);
            }
        });

        $this->call(PersonalPlannerDemoSeeder::class);

        if (app()->environment('local')) {
            $this->call(CoherenceBaselineDemoSeeder::class);
        }
    }
}
