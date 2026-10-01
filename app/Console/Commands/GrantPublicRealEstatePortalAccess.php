<?php

namespace App\Console\Commands;

use App\Models\PublicIntakePortal;
use App\Models\PublicIntakePortalGrant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GrantPublicRealEstatePortalAccess extends Command
{
    protected $signature = 'real-estate:intake-grant
        {portal : Portal UUID or public token}
        {user : Existing user email or username}
        {--role=viewer : viewer or manager}';

    protected $description = 'Grant private real-estate office access';

    public function handle(): int
    {
        $role = (string) $this->option('role');

        if (! in_array($role, ['viewer', 'manager'], true)) {
            $this->error('Role must be viewer or manager.');

            return self::FAILURE;
        }

        $value = (string) $this->argument('portal');

        $portal = PublicIntakePortal::query()
            ->where('uuid', $value)
            ->orWhere('public_token', $value)
            ->first();

        if (! $portal) {
            $this->error('Portal not found.');

            return self::FAILURE;
        }

        $identity = (string) $this->argument('user');
        $userQuery = User::query()->where('email', $identity);

        if (Schema::hasColumn((new User)->getTable(), 'username')) {
            $userQuery->orWhere('username', $identity);
        }

        $user = $userQuery->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        PublicIntakePortalGrant::query()->updateOrCreate(
            [
                'public_intake_portal_id' => $portal->getKey(),
                'user_id' => $user->getKey(),
            ],
            ['role' => $role]
        );

        $this->info("Granted {$role} access to {$identity}");
        $this->line(route('office.real-estate.index', ['portal' => $portal->uuid]));

        return self::SUCCESS;
    }
}
