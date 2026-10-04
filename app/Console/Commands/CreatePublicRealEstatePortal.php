<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Business\EnsureRealEstateBusinessIntake;
use Illuminate\Console\Command;

class CreatePublicRealEstatePortal extends Command
{
    protected $signature = 'real-estate:intake-portal
                            {business : Real Estate Business UUID, code, or slug}
                            {--welcome= : Optional public welcome text override}
                            {--locale= : Optional portal locale override}';

    protected $description = 'Ensure the canonical public Real Estate intake channel for an existing Business';

    public function handle(EnsureRealEstateBusinessIntake $intake): int
    {
        $identifier = trim((string) $this->argument('business'));

        $business = Business::query()
            ->where('uuid', $identifier)
            ->orWhere('code', $identifier)
            ->orWhere('slug', $identifier)
            ->first();

        if (! $business instanceof Business) {
            $this->error('Business not found. Pass its UUID, code, or slug.');

            return self::FAILURE;
        }

        if ($business->kind !== 'real_estate') {
            $this->error('This command only applies to a Business whose kind is real_estate.');

            return self::FAILURE;
        }

        $portal = $intake->execute($business);

        $updates = [];

        if ($this->option('welcome') !== null) {
            $updates['welcome_body'] = (string) $this->option('welcome');
        }

        if ($this->option('locale') !== null) {
            $updates['locale'] = (string) $this->option('locale');
        }

        if ($updates !== []) {
            $portal->update($updates);
            $portal->refresh();
        }

        $this->newLine();
        $this->info('Business Real Estate intake channel is ready.');
        $this->line('Business: '.$business->name);
        $this->line('URL: '.route('public.real-estate.show', $portal));
        $this->line('Token: '.$portal->public_token);
        $this->newLine();
        $this->warn('The intake channel belongs to this Business; this command never creates an orphan Real Estate system.');

        return self::SUCCESS;
    }
}
