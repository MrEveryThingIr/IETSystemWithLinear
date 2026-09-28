<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class IetValueMath
{
    public const INITIAL_X_PERCENT = '0.000001';

    public const INITIAL_USD_PICO_PER_IET = 10000;

    private const USD_PICO_PER_CENT = '10000000000';

    private const IET_MINOR_PER_IET = '100';

    private const QUOTE_DENOMINATOR = '1000000000000';

    public static function ietMinorForUsdMinor(int $usdMinor, int $usdPicoPerIet): int
    {
        if ($usdMinor <= 0 || $usdPicoPerIet <= 0) {
            throw new InvalidArgumentException('IET quote inputs must be positive.');
        }

        $numerator = BigInteger::of((string) $usdMinor)
            ->multipliedBy(self::USD_PICO_PER_CENT)
            ->multipliedBy(self::IET_MINOR_PER_IET);
        $denominator = BigInteger::of((string) $usdPicoPerIet);
        [$quotient, $remainder] = $numerator->quotientAndRemainder($denominator);

        if (! $remainder->isZero()) {
            $quotient = $quotient->plus(1);
        }

        return self::toNativeInt($quotient);
    }

    public static function usdMinorForIetMinor(int $ietMinor, int $usdPicoPerIet): int
    {
        if ($ietMinor <= 0 || $usdPicoPerIet <= 0) {
            throw new InvalidArgumentException('IET quote inputs must be positive.');
        }

        $numerator = BigInteger::of((string) $ietMinor)
            ->multipliedBy((string) $usdPicoPerIet);
        [$quotient] = $numerator->quotientAndRemainder(
            BigInteger::of(self::QUOTE_DENOMINATOR),
        );

        return self::toNativeInt($quotient);
    }

    public static function picosFromXPercent(string $xPercent): int
    {
        $xPercent = trim($xPercent);

        if (! preg_match('/^\d+(?:\.\d{1,12})?$/', $xPercent)) {
            throw new InvalidArgumentException('Invalid IET percentage.');
        }

        try {
            $scaled = BigDecimal::of($xPercent)
                ->multipliedBy('10000000000')
                ->toScale(0, RoundingMode::HALF_UP);
        } catch (MathException $exception) {
            throw new InvalidArgumentException('Invalid IET percentage.', previous: $exception);
        }

        $picos = BigInteger::of((string) $scaled);

        if ($picos->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('IET percentage must be positive.');
        }

        return self::toNativeInt($picos);
    }

    public static function usdPerIet(int $usdPicoPerIet): string
    {
        return self::trimDecimal(
            (string) BigDecimal::of((string) $usdPicoPerIet)
                ->dividedBy('1000000000000', 12, RoundingMode::DOWN),
        );
    }

    public static function xPercent(int $usdPicoPerIet): string
    {
        return self::trimDecimal(
            (string) BigDecimal::of((string) $usdPicoPerIet)
                ->dividedBy('10000000000', 10, RoundingMode::DOWN),
        );
    }

    public static function formatIetMinor(int $ietMinor): string
    {
        return self::trimDecimal(MoneyAmount::format($ietMinor, 2));
    }

    private static function trimDecimal(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private static function toNativeInt(BigInteger $value): int
    {
        $string = (string) $value;
        $max = (string) PHP_INT_MAX;

        if (
            strlen($string) > strlen($max)
            || (strlen($string) === strlen($max) && strcmp($string, $max) > 0)
        ) {
            throw new InvalidArgumentException('IET amount exceeds supported ledger precision.');
        }

        return (int) $string;
    }
}
