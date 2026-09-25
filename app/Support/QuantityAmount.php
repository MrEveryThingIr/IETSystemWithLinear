<?php

namespace App\Support;

use InvalidArgumentException;

final class QuantityAmount
{
    public const SCALE = 4;

    public static function normalize(string|int $value): string
    {
        $raw = trim((string) $value);

        if (preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', $raw) !== 1) {
            throw new InvalidArgumentException('Quantity must be a non-negative decimal with at most four decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($fraction, self::SCALE, '0');

        return $whole.'.'.$fraction;
    }

    public static function positive(string|int $value): string
    {
        $normalized = self::normalize($value);

        if (self::toScaledInt($normalized) <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $normalized;
    }

    public static function toScaledInt(string|int $value): int
    {
        $normalized = self::normalize($value);
        [$whole, $fraction] = explode('.', $normalized, 2);

        $scaled = ((int) $whole * 10 ** self::SCALE) + (int) $fraction;

        if ($scaled < 0) {
            throw new InvalidArgumentException('Quantity is outside the supported range.');
        }

        return $scaled;
    }

    public static function fromScaledInt(int $value): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        $factor = 10 ** self::SCALE;
        $whole = intdiv($value, $factor);
        $fraction = $value % $factor;

        return $whole.'.'.str_pad((string) $fraction, self::SCALE, '0', STR_PAD_LEFT);
    }

    public static function add(string|int $left, string|int $right): string
    {
        return self::fromScaledInt(self::toScaledInt($left) + self::toScaledInt($right));
    }

    public static function subtractFloorZero(string|int $left, string|int $right): string
    {
        return self::fromScaledInt(max(0, self::toScaledInt($left) - self::toScaledInt($right)));
    }

    public static function compare(string|int $left, string|int $right): int
    {
        return self::toScaledInt($left) <=> self::toScaledInt($right);
    }
}
