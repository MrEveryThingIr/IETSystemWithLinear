<?php

namespace App\Actions\Exchange;

use App\Models\IetValuationQuote;
use App\Models\User;
use App\PlatformCapability;
use Illuminate\Support\Str;

class PublishIetValuationQuote
{
    /**
     * @param array<string, mixed> $factors
     */
    public function execute(
        User $user,
        string $usdPerIet,
        string $rationale,
        array $factors = [],
    ): IetValuationQuote {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        $usdPerIet = trim($usdPerIet);
        abort_unless(
            preg_match('/^(?:0|[1-9]\d*)\.\d{1,10}$/', $usdPerIet) === 1
            && (float) $usdPerIet > 0,
            422,
            'Enter a positive USD-per-IET value with at most 10 decimals.',
        );

        return IetValuationQuote::query()->create([
            'uuid' => (string) Str::uuid(),
            'usd_per_iet' => $usdPerIet,
            'policy_version' => 'manual-v1',
            'factors' => $factors,
            'rationale' => trim($rationale) !== '' ? trim($rationale) : null,
            'effective_at' => now(),
            'published_by_user_id' => $user->id,
        ]);
    }
}
