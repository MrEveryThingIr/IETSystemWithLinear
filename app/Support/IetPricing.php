<?php

namespace App\Support;

use App\Models\IetValuationQuote;
use InvalidArgumentException;

final class IetPricing
{
    public function currentQuote(): IetValuationQuote
    {
        return IetValuationQuote::query()
            ->where('effective_at', '<=', now())
            ->latest('effective_at')
            ->latest('id')
            ->firstOrFail();
    }

    public function percentOfUsd(IetValuationQuote|string $quote): string
    {
        $decimal = trim($quote instanceof IetValuationQuote ? (string) $quote->usd_per_iet : $quote);

        if (! preg_match('/^(?:0|[1-9]\d*)\.(\d{1,10})$/', $decimal, $matches)) {
            throw new InvalidArgumentException('Invalid IET valuation precision.');
        }

        [$whole] = explode('.', $decimal, 2);
        $fraction = str_pad($matches[1], 10, '0');
        $percentWhole = ltrim($whole.substr($fraction, 0, 2), '0');
        $percentWhole = $percentWhole === '' ? '0' : $percentWhole;
        $percentFraction = rtrim(substr($fraction, 2), '0');

        return $percentFraction === '' ? $percentWhole : $percentWhole.'.'.$percentFraction;
    }

    public function ietForUsdMinor(int $usdMinor, ?IetValuationQuote $quote = null): int
    {
        if ($usdMinor <= 0) {
            throw new InvalidArgumentException('USD requirement must be positive.');
        }

        $quote ??= $this->currentQuote();
        [$numerator, $denominator] = $this->fraction((string) $quote->usd_per_iet);

        // USD amount is stored in cents. IET is currently a whole-unit
        // monetary unit, so ceil() is required to avoid underfunding a flow.
        return $this->ceilRatioProduct($usdMinor, $denominator, 100 * $numerator);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function fraction(string $decimal): array
    {
        $decimal = trim($decimal);

        if (! preg_match('/^(?:0|[1-9]\d*)\.\d{1,10}$/', $decimal)) {
            throw new InvalidArgumentException('Invalid IET valuation precision.');
        }

        [$whole, $fraction] = explode('.', $decimal, 2);
        $denominator = (int) (10 ** strlen($fraction));
        $numerator = ((int) $whole * $denominator) + (int) $fraction;

        if ($numerator <= 0) {
            throw new InvalidArgumentException('IET valuation must be positive.');
        }

        $gcd = $this->gcd($numerator, $denominator);

        return [intdiv($numerator, $gcd), intdiv($denominator, $gcd)];
    }

    private function ceilRatioProduct(int $left, int $right, int $denominator): int
    {
        $gcd = $this->gcd($left, $denominator);
        $left = intdiv($left, $gcd);
        $denominator = intdiv($denominator, $gcd);

        $gcd = $this->gcd($right, $denominator);
        $right = intdiv($right, $gcd);
        $denominator = intdiv($denominator, $gcd);

        if ($right !== 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            throw new InvalidArgumentException('IET pricing amount is too large.');
        }

        $product = $left * $right;

        $quotient = intdiv($product, $denominator);

        return $product % $denominator === 0 ? $quotient : $quotient + 1;
    }

    private function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);

        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, $a);
    }
}
