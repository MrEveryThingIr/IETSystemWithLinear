<?php

namespace Database\Seeders;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\User;
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

        $this->call(ConstructionProjectSeeder::class);
    }
}
