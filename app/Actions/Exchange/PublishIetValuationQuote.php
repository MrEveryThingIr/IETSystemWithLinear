<?php

namespace App\Actions\Exchange;

use App\Models\IetValuationQuote;
use App\Models\User;
use App\PlatformCapability;
use App\Support\IetMarketQuoteBridge;
use Illuminate\Support\Str;

class PublishIetValuationQuote
{
    public function __construct(
        private readonly IetMarketQuoteBridge $marketQuotes,
    ) {}

    /**
     * @param  array<string, mixed>  $factors
     */
    public function execute(
        User $user,
        string $usdPerIet,
        string $rationale,
        array $factors = [],
    ): IetValuationQuote {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        $usdPerIet = trim($usdPerIet);
        $rationale = trim($rationale);

        abort_if($rationale === '' || mb_strlen($rationale) > 4000, 422, 'A valuation rationale is required.');

        abort_unless(
            preg_match('/^(?:0|[1-9]\d*)\.\d{1,18}$/', $usdPerIet) === 1
            && trim(str_replace(['0', '.'], '', $usdPerIet)) !== '',
            422,
            'Enter a positive USD-per-IET value with at most 18 decimals.',
        );

        $quote = IetValuationQuote::query()->create([
            'uuid' => (string) Str::uuid(),
            'usd_per_iet' => $usdPerIet,
            'policy_version' => 'manual-v1',
            'factors' => $factors,
            'rationale' => $rationale,
            'effective_at' => now(),
            'published_by_user_id' => $user->id,
        ]);

        $this->marketQuotes->mirror($quote);

        return $quote;
    }
}
