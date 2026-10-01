<?php

namespace App\Console\Commands;

use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use Illuminate\Console\Command;

class AuditSuperAdmins extends Command
{
    protected $signature = 'iet:superadmin-audit';

    protected $description = 'Verify the platform has at least one active, verified super-admin grant.';

    public function handle(): int
    {
        $admins = PlatformAccessGrant::query()
            ->with('user')
            ->active()
            ->where('role', PlatformRole::Superadmin->value)
            ->whereHas('user', fn ($query) => $query
                ->where('status', 'active')
                ->whereNotNull('email_verified_at'))
            ->oldest('id')
            ->get();

        if ($admins->isEmpty()) {
            $this->error('CRITICAL: no active, verified platform super-admin grant exists.');

            return self::FAILURE;
        }

        $this->info('Super-admin invariant OK.');
        $this->table(
            ['Grant', 'User', 'Email', 'Granted at', 'Correlation'],
            $admins->map(fn (PlatformAccessGrant $grant): array => [
                $grant->getKey(),
                $grant->user->username,
                $grant->user->email,
                $grant->granted_at->toDateTimeString(),
                $grant->correlation_id,
            ])->all()
        );

        return self::SUCCESS;
    }
}
