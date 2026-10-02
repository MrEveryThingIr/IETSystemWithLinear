<?php

namespace App\Support;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\EconomicInstrumentKind;
use App\Models\EconomicInstrument;
use App\Models\IetValuationQuote;
use App\Models\MarketQuote;
use App\Models\MonetaryUnit;
use InvalidArgumentException;

final class IetReferencePricing
{
    public function __construct(
        private readonly EnsureMonetaryUnit $units,
        private readonly IetPricing $ietPricing,
    ) {}

    /**
     * @return array{
     *   reference_unit: MonetaryUnit,
     *   market_quote: ?MarketQuote,
     *   iet_quote: IetValuationQuote,
     *   reference_amount_minor: int,
     *   usd_amount_minor: int,
     *   iet_amount: int
     * }
     */
    public function quote(string $referenceCode, int $referenceAmountMinor): array
    {
        abort_if($referenceAmountMinor <= 0, 422, 'Reference amount must be positive.');

        $referenceUnit = $this->units->execute(strtoupper(trim($referenceCode)));
        abort_if($referenceUnit->code === 'IET', 422, 'Reference money must be external to IET.');

        $ietQuote = $this->ietPricing->currentQuote();

        if ($referenceUnit->code === 'USD') {
            $usdAmountMinor = $this->rescaleMinor(
                $referenceAmountMinor,
                (int) $referenceUnit->exponent,
                2,
            );

            return [
                'reference_unit' => $referenceUnit,
                'market_quote' => null,
                'iet_quote' => $ietQuote,
                'reference_amount_minor' => $referenceAmountMinor,
                'usd_amount_minor' => $usdAmountMinor,
                'iet_amount' => $this->ietPricing->ietForUsdMinor($usdAmountMinor, $ietQuote),
            ];
        }

        $referenceInstrument = $this->ensureFiatInstrument($referenceUnit);
        $usd = EconomicInstrument::query()->where('code', 'USD')->firstOrFail();

        [$marketQuote, $inverse] = $this->currentUsdQuote($referenceInstrument, $usd);

        $usdAmountMinor = $this->usdMinor(
            $referenceAmountMinor,
            (int) $referenceUnit->exponent,
            (string) $marketQuote->price,
            $inverse,
        );

        return [
            'reference_unit' => $referenceUnit,
            'market_quote' => $marketQuote,
            'iet_quote' => $ietQuote,
            'reference_amount_minor' => $referenceAmountMinor,
            'usd_amount_minor' => $usdAmountMinor,
            'iet_amount' => $this->ietPricing->ietForUsdMinor($usdAmountMinor, $ietQuote),
        ];
    }

    private function ensureFiatInstrument(MonetaryUnit $unit): EconomicInstrument
    {
        $existing = EconomicInstrument::query()->where('code', $unit->code)->first();

        if ($existing instanceof EconomicInstrument) {
            abort_unless(
                $existing->kind === EconomicInstrumentKind::FiatCurrency,
                422,
                'Reference monetary instrument is not registered as fiat currency.',
            );

            return $existing;
        }

        return EconomicInstrument::query()->create([
            'code' => $unit->code,
            'name' => MonetaryUnitCatalog::get($unit->code)['name'],
            'kind' => EconomicInstrumentKind::FiatCurrency,
            'monetary_unit_id' => $unit->id,
            'settlement_enabled' => false,
            'active' => true,
            'metadata' => ['system_managed_reference_currency' => true],
        ]);
    }

    /**
     * @return array{0: MarketQuote, 1: bool}
     */
    private function currentUsdQuote(
        EconomicInstrument $reference,
        EconomicInstrument $usd,
    ): array {
        $base = MarketQuote::query()
            ->where('base_instrument_id', $reference->id)
            ->where('quote_instrument_id', $usd->id)
            ->where('effective_at', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->latest('effective_at')
            ->latest('id')
            ->first();

        if ($base instanceof MarketQuote) {
            return [$base, false];
        }

        $inverse = MarketQuote::query()
            ->where('base_instrument_id', $usd->id)
            ->where('quote_instrument_id', $reference->id)
            ->where('effective_at', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->latest('effective_at')
            ->latest('id')
            ->first();

        abort_unless(
            $inverse instanceof MarketQuote,
            422,
            'No active '.$reference->code.'/USD market quote exists. Publish one in Exchange first.',
        );

        return [$inverse, true];
    }

    private function usdMinor(
        int $referenceMinor,
        int $referenceExponent,
        string $price,
        bool $inverse,
    ): int {
        [$priceNumerator, $priceDenominator] = $this->fraction($price);

        $numerators = [$referenceMinor, 100];
        $denominators = [10 ** $referenceExponent];

        if ($inverse) {
            $denominators[] = $priceNumerator;
            $numerators[] = $priceDenominator;
        } else {
            $numerators[] = $priceNumerator;
            $denominators[] = $priceDenominator;
        }

        return $this->roundedRatio($numerators, $denominators);
    }

    private function rescaleMinor(int $amountMinor, int $fromExponent, int $toExponent): int
    {
        if ($fromExponent === $toExponent) {
            return $amountMinor;
        }

        if ($fromExponent < $toExponent) {
            $factor = 10 ** ($toExponent - $fromExponent);
            abort_if($amountMinor > intdiv(PHP_INT_MAX, $factor), 422, 'Reference amount is too large.');

            return $amountMinor * $factor;
        }

        $factor = 10 ** ($fromExponent - $toExponent);

        return intdiv($amountMinor + intdiv($factor, 2), $factor);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function fraction(string $decimal): array
    {
        $decimal = trim($decimal);
        abort_unless(
            preg_match('/^(?:0|[1-9]\d*)(?:\.\d+)?$/', $decimal) === 1
                && preg_match('/[1-9]/', $decimal) === 1,
            422,
            'Market quote must be a positive decimal.',
        );

        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        abort_if(strlen($fraction) > 18, 422, 'Market quote precision exceeds supported internal conversion precision.');

        $denominator = 10 ** strlen($fraction);
        $wholeDigits = ltrim($whole, '0');
        $digits = ($wholeDigits === '' ? '0' : $wholeDigits).$fraction;
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;

        abort_if(strlen($digits) > 18, 422, 'Market quote magnitude exceeds supported internal conversion range.');

        $numerator = (int) $digits;
        abort_if($numerator <= 0, 422, 'Market quote must be positive.');

        $gcd = $this->gcd($numerator, $denominator);

        return [intdiv($numerator, $gcd), intdiv($denominator, $gcd)];
    }

    /**
     * @param list<int> $numerators
     * @param list<int> $denominators
     */
    private function roundedRatio(array $numerators, array $denominators): int
    {
        foreach ($numerators as $nIndex => $numerator) {
            foreach ($denominators as $dIndex => $denominator) {
                $gcd = $this->gcd($numerator, $denominator);
                if ($gcd > 1) {
                    $numerators[$nIndex] = intdiv($numerators[$nIndex], $gcd);
                    $denominators[$dIndex] = intdiv($denominators[$dIndex], $gcd);
                    $numerator = $numerators[$nIndex];
                }
            }
        }

        $top = 1;
        foreach ($numerators as $factor) {
            abort_if($factor !== 0 && $top > intdiv(PHP_INT_MAX, $factor), 422, 'Reference conversion is too large.');
            $top *= $factor;
        }

        $bottom = 1;
        foreach ($denominators as $factor) {
            abort_if($factor !== 0 && $bottom > intdiv(PHP_INT_MAX, $factor), 422, 'Reference conversion precision is too large.');
            $bottom *= $factor;
        }

        abort_if($bottom <= 0, 422, 'Reference conversion denominator is invalid.');

        $whole = intdiv($top, $bottom);
        $remainder = $top % $bottom;

        return $remainder >= intdiv($bottom + 1, 2) ? $whole + 1 : $whole;
    }

    private function gcd(int $left, int $right): int
    {
        $left = abs($left);
        $right = abs($right);

        while ($right !== 0) {
            [$left, $right] = [$right, $left % $right];
        }

        return max(1, $left);
    }
}
