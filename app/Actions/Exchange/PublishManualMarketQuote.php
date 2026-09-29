<?php

namespace App\Actions\Exchange;

use App\Models\EconomicInstrument;
use App\Models\MarketQuote;
use App\Models\QuoteSource;
use App\Models\User;
use App\PlatformCapability;
use Illuminate\Support\Str;

class PublishManualMarketQuote
{
    public function __construct(
        private readonly PublishMarketQuote $publish,
    ) {}

    public function execute(
        User $user,
        string $baseUuid,
        string $quoteUuid,
        string $price,
        string $rationale,
        ?string $sourceReference = null,
    ): MarketQuote {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ManageExchange), 403);

        $rationale = trim($rationale);
        abort_if($rationale === '' || mb_strlen($rationale) > 4000, 422, 'A quote rationale is required.');

        $sourceReference = filled($sourceReference) ? Str::squish((string) $sourceReference) : null;
        abort_if($sourceReference !== null && mb_strlen($sourceReference) > 255, 422, 'Quote source reference is too long.');

        $base = EconomicInstrument::query()->where('uuid', $baseUuid)->firstOrFail();
        $counter = EconomicInstrument::query()->where('uuid', $quoteUuid)->firstOrFail();

        $source = QuoteSource::query()->firstOrCreate(
            ['key' => 'manual-admin'],
            [
                'name' => 'Manual administrator quote',
                'source_type' => 'manual',
                'trust_tier' => 5,
                'metadata' => ['system_managed' => true],
            ],
        );

        return $this->publish->execute(
            $user,
            $base,
            $counter,
            $source,
            $price,
            now(),
            sourceReference: $sourceReference,
            metadata: [
                'rationale' => $rationale,
                'manual' => true,
            ],
        );
    }
}
