<?php

namespace App\Console\Commands;

use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use App\Services\Access\SuperAdminBootstrapper;
use Illuminate\Console\Command;

class InitializeIet extends Command
{
    protected $signature = 'iet:initialize
        {--superadmin= : Existing user email or username to guarantee as super-admin}';

    protected $description = 'Initialize IET platform invariants and guarantee at least one existing super-admin.';

    public function handle(SuperAdminBootstrapper $bootstrapper): int
    {
        try {
            $admin = $bootstrapper->ensure($this->option('superadmin'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('IET initialization checks passed.');
        $this->line('Super-admin: '.($admin->username ?? $admin->email ?? $admin->getKey()));
        $this->line('Active super-admin grants: '.PlatformAccessGrant::query()
            ->active()
            ->where('role', PlatformRole::Superadmin->value)
            ->count());

        return self::SUCCESS;
    }
}
