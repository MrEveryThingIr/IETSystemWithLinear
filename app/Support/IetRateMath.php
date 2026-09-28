<?php

namespace App\Support;

use App\Models\IetRateVersion;
use InvalidArgumentException;
use OverflowException;

final class IetRateMath
{
    public function ietForUsdMinor(int $usdMinor, IetRateVersion $rate): int
    {
        if ($usdMinor <= 0) {
            throw new InvalidArgumentException('USD reference amount must be positive.');
        }

        $numerator = (int) $rate->usd_numerator;
        $denominator = (int) $rate->usd_denominator;

        if ($numerator <= 0 || $denominator <= 0) {
            throw new InvalidArgumentException('Invalid IET rate.');
        }

        return $this->ceilMulDiv($usdMinor, $denominator, 100 * $numerator);
    }

    public function usdMinorForIet(int $ietAmount, IetRateVersion $rate): int
    {
        if ($ietAmount <= 0) {
            throw new InvalidArgumentException('IET amount must be positive.');
        }

        $numerator = (int) $rate->usd_numerator;
        $denominator = (int) $rate->usd_denominator;

        if ($numerator <= 0 || $denominator <= 0) {
            throw new InvalidArgumentException('Invalid IET rate.');
        }

        return intdiv($this->checkedMultiply($ietAmount, 100 * $numerator), $denominator);
    }

    /** @return array{numerator:int,denominator:int} */
    public function adjustedRatio(IetRateVersion $rate, int $adjustmentPpm): array
    {
        if ($adjustmentPpm <= -1_000_000 || $adjustmentPpm > 1_000_000) {
            throw new InvalidArgumentException('IET adjustment must be greater than -100% and no more than +100%.');
        }

        $a = (int) $rate->usd_numerator;
        $b = (int) $rate->usd_denominator;
        $c = 1_000_000 + $adjustmentPpm;
        $d = 1_000_000;

        $g1 = $this->gcd($a, $d);
        $a = intdiv($a, $g1);
        $d = intdiv($d, $g1);

        $g2 = $this->gcd($c, $b);
        $c = intdiv($c, $g2);
        $b = intdiv($b, $g2);

        $numerator = $this->checkedMultiply($a, $c);
        $denominator = $this->checkedMultiply($b, $d);
        $g = $this->gcd($numerator, $denominator);

        return [
            'numerator' => intdiv($numerator, $g),
            'denominator' => intdiv($denominator, $g),
        ];
    }

    public function usdPerIet(IetRateVersion $rate, int $places = 12): string
    {
        return $this->ratioDecimal((int) $rate->usd_numerator, (int) $rate->usd_denominator, $places);
    }

    public function percentOfDollar(IetRateVersion $rate, int $places = 9): string
    {
        return $this->ratioDecimal(
            $this->checkedMultiply((int) $rate->usd_numerator, 100),
            (int) $rate->usd_denominator,
            $places,
        );
    }

    private function ceilMulDiv(int $a, int $b, int $c): int
    {
        if ($a <= 0 || $b <= 0 || $c <= 0) {
            throw new InvalidArgumentException('Positive integer arithmetic required.');
        }

        $g = $this->gcd($a, $c);
        $a = intdiv($a, $g);
        $c = intdiv($c, $g);

        $g = $this->gcd($b, $c);
        $b = intdiv($b, $g);
        $c = intdiv($c, $g);

        $product = $this->checkedMultiply($a, $b);

        return intdiv($product + $c - 1, $c);
    }

    private function checkedMultiply(int $a, int $b): int
    {
        if ($a !== 0 && abs($b) > intdiv(PHP_INT_MAX, abs($a))) {
            throw new OverflowException('IET arithmetic exceeds supported integer range.');
        }

        return $a * $b;
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

    private function ratioDecimal(int $numerator, int $denominator, int $places): string
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Ratio denominator must be positive.');
        }

        $whole = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($places <= 0 || $remainder === 0) {
            return (string) $whole;
        }

        $digits = '';

        for ($i = 0; $i < $places; $i++) {
            $remainder *= 10;
            $digits .= (string) intdiv($remainder, $denominator);
            $remainder %= $denominator;

            if ($remainder === 0) {
                break;
            }
        }

        return $whole.'.'.rtrim($digits, '0');
    }
}
