<?php

namespace App\Support;

use InvalidArgumentException;

final class MoneyAmount
{
    public static function parse(string $value, int $exponent): int
    {
        $value = trim($value);

        if ($exponent < 0 || $exponent > 6) {
            throw new InvalidArgumentException('Unsupported monetary exponent.');
        }

        $pattern = $exponent === 0
            ? '/^\d+$/'
            : '/^\d+(?:\.\d{1,'.$exponent.'})?$/';

        if (! preg_match($pattern, $value)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, $exponent, '0');

        $minor = ltrim($whole.$fraction, '0');
        $minor = $minor === '' ? '0' : $minor;

        if (strlen($minor) > strlen((string) PHP_INT_MAX)
            || (strlen($minor) === strlen((string) PHP_INT_MAX) && strcmp($minor, (string) PHP_INT_MAX) > 0)) {
            throw new InvalidArgumentException('Monetary amount is too large.');
        }

        return (int) $minor;
    }

    public static function format(int $minor, int $exponent): string
    {
        $negative = $minor < 0;
        $digits = (string) abs($minor);

        if ($exponent === 0) {
            return ($negative ? '-' : '').$digits;
        }

        $digits = str_pad($digits, $exponent + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$exponent);
        $fraction = substr($digits, -$exponent);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }
}
