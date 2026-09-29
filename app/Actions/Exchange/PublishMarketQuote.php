<?php

namespace App\Actions\Exchange;

use App\Models\EconomicInstrument;
use App\Models\MarketQuote;
use App\Models\QuoteSource;
use App\Models\User;
use App\PlatformCapability;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PublishMarketQuote
{
    public function execute(
        User $publisher,
        EconomicInstrument $base,
        EconomicInstrument $counter,
        QuoteSource $source,
        string $price,
        CarbonInterface $observedAt,
        ?CarbonInterface $effectiveAt = null,
        ?CarbonInterface $expiresAt = null,
        ?string $sourceReference = null,
        ?int $confidenceBps = null,
        ?string $bid = null,
        ?string $ask = null,
        ?array $metadata = null,
    ): MarketQuote {
        abort_unless($publisher->hasPlatformCapability(PlatformCapability::ManageExchange), 403);
        abort_if($base->is($counter), 422, 'A quote requires two different instruments.');
        abort_unless($base->active && $counter->active && $source->active, 422, 'Inactive instruments or quote sources cannot publish quotes.');

        $price = $this->positiveDecimal($price, 'price');
        $bid = $bid === null ? null : $this->positiveDecimal($bid, 'bid');
        $ask = $ask === null ? null : $this->positiveDecimal($ask, 'ask');

        if ($confidenceBps !== null) {
            abort_if($confidenceBps < 0 || $confidenceBps > 10_000, 422, 'Quote confidence must be between 0 and 10000 basis points.');
        }

        if ($expiresAt !== null) {
            abort_if($expiresAt->lessThanOrEqualTo($effectiveAt ?? $observedAt), 422, 'Quote expiry must be later than its effective time.');
        }

        return DB::transaction(fn (): MarketQuote => MarketQuote::query()->create([
            'base_instrument_id' => $base->id,
            'quote_instrument_id' => $counter->id,
            'price' => $price,
            'bid' => $bid,
            'ask' => $ask,
            'quote_source_id' => $source->id,
            'source_reference' => filled($sourceReference) ? trim((string) $sourceReference) : null,
            'observed_at' => $observedAt,
            'effective_at' => $effectiveAt ?? $observedAt,
            'expires_at' => $expiresAt,
            'confidence_bps' => $confidenceBps,
            'published_by_user_id' => $publisher->id,
            'metadata' => $metadata,
        ])->fresh(['baseInstrument', 'quoteInstrument', 'source', 'publisher']));
    }

    private function positiveDecimal(string $value, string $field): string
    {
        $value = trim($value);

        abort_unless(
            preg_match('/^(?:0|[1-9]\d{0,29})(?:\.\d{1,20})?$/', $value) === 1
                && preg_match('/[1-9]/', $value) === 1,
            422,
            ucfirst($field).' must be a positive decimal with at most 20 fractional digits.',
        );

        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value;
    }
}
