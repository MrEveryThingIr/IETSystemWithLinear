<?php

namespace App\Support;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\EconomicInstrumentKind;
use App\Models\EconomicInstrument;
use App\Models\IetValuationQuote;
use App\Models\MarketQuote;
use App\Models\QuoteSource;
use Illuminate\Support\Facades\DB;

final class IetMarketQuoteBridge
{
    public function __construct(
        private readonly EnsureMonetaryUnit $units,
    ) {}

    public function mirror(IetValuationQuote $legacyQuote): MarketQuote
    {
        return DB::transaction(function () use ($legacyQuote): MarketQuote {
            $ietUnit = $this->units->execute('IET');
            $usdUnit = $this->units->execute('USD');

            $iet = EconomicInstrument::query()->firstOrCreate(
                ['code' => 'IET'],
                [
                    'name' => 'IET Internal Settlement Unit',
                    'kind' => EconomicInstrumentKind::InternalUnit,
                    'monetary_unit_id' => $ietUnit->id,
                    'settlement_enabled' => true,
                    'metadata' => ['system_managed' => true],
                ],
            );

            $usd = EconomicInstrument::query()->firstOrCreate(
                ['code' => 'USD'],
                [
                    'name' => 'US Dollar',
                    'kind' => EconomicInstrumentKind::FiatCurrency,
                    'monetary_unit_id' => $usdUnit->id,
                    'settlement_enabled' => true,
                    'metadata' => ['iso_4217' => true],
                ],
            );

            foreach (['IRR', 'IRT'] as $referenceCode) {
                $referenceUnit = $this->units->execute($referenceCode);

                EconomicInstrument::query()->firstOrCreate(
                    ['code' => $referenceCode],
                    [
                        'name' => MonetaryUnitCatalog::get($referenceCode)['name'],
                        'kind' => EconomicInstrumentKind::FiatCurrency,
                        'monetary_unit_id' => $referenceUnit->id,
                        'settlement_enabled' => false,
                        'metadata' => ['system_managed_reference_currency' => true],
                    ],
                );
            }

            abort_unless(
                $iet->kind === EconomicInstrumentKind::InternalUnit
                && (int) $iet->monetary_unit_id === (int) $ietUnit->id,
                422,
                'IET economic instrument binding is inconsistent.',
            );

            abort_unless(
                $usd->kind === EconomicInstrumentKind::FiatCurrency
                && (int) $usd->monetary_unit_id === (int) $usdUnit->id,
                422,
                'USD economic instrument binding is inconsistent.',
            );

            $source = QuoteSource::query()->firstOrCreate(
                ['key' => 'iet-valuation-policy'],
                [
                    'name' => 'IET valuation policy',
                    'source_type' => 'policy',
                    'trust_tier' => 10,
                    'metadata' => ['system_managed' => true],
                ],
            );

            $sourceReference = 'iet-valuation:'.$legacyQuote->uuid;

            $existing = MarketQuote::query()
                ->where('quote_source_id', $source->id)
                ->where('source_reference', $sourceReference)
                ->first();

            if ($existing instanceof MarketQuote) {
                abort_unless(
                    (int) $existing->base_instrument_id === (int) $iet->id
                    && (int) $existing->quote_instrument_id === (int) $usd->id
                    && $this->sameDecimal((string) $existing->price, (string) $legacyQuote->usd_per_iet),
                    409,
                    'Existing generalized IET quote does not match its compatibility source.',
                );

                return $existing;
            }

            return MarketQuote::query()->create([
                'base_instrument_id' => $iet->id,
                'quote_instrument_id' => $usd->id,
                'price' => (string) $legacyQuote->usd_per_iet,
                'quote_source_id' => $source->id,
                'source_reference' => $sourceReference,
                'observed_at' => $legacyQuote->effective_at,
                'effective_at' => $legacyQuote->effective_at,
                'confidence_bps' => 10_000,
                'published_by_user_id' => $legacyQuote->published_by_user_id,
                'metadata' => [
                    'compatibility_source' => 'iet_valuation_quotes',
                    'iet_valuation_quote_uuid' => $legacyQuote->uuid,
                    'policy_version' => $legacyQuote->policy_version,
                    'factors' => $legacyQuote->factors,
                    'rationale' => $legacyQuote->rationale,
                ],
            ])->fresh(['baseInstrument', 'quoteInstrument', 'source', 'publisher']);
        }, attempts: 3);
    }

    private function sameDecimal(string $left, string $right): bool
    {
        return $this->canonicalDecimal($left) === $this->canonicalDecimal($right);
    }

    private function canonicalDecimal(string $value): string
    {
        $value = trim($value);
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $fraction = rtrim($fraction, '0');

        return ($whole === '' ? '0' : $whole).($fraction === '' ? '' : '.'.$fraction);
    }
}
