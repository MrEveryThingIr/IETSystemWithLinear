<?php

namespace Database\Seeders\Farsi;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\User;
use Database\Factories\Farsi\UserFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        app(GroupRoleProvisioner::class)->seedPermissions();

        $user = User::query()->where('email', 'test@example.com')->first()
            ?? UserFactory::new()->create([
                'username' => 'کاربر آزمایشی',
                'email' => 'test@example.com',
            ]);

        $user->actor()->firstOrCreate([]);

        $this->call(ConstructionProjectSeeder::class);
    }
}
