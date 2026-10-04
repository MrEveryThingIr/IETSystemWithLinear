<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(BootstrapSuperadminSeeder::class);

        if ((string) config('release.profile') !== 'planning_baseline') {
            $this->call(SystemManualSeeder::class);
        }

        if (! (bool) config('bootstrap.demo_data', false)) {
            return;
        }

        $this->call(PersonalPlannerDemoSeeder::class);

        if (app()->environment('local')) {
            $this->call(CoherenceBaselineDemoSeeder::class);
        }
    }
}
