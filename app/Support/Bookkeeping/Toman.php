<?php

namespace App\Support\Bookkeeping;

use App\Support\LocalizedNumber;
use Illuminate\Validation\ValidationException;

/** Whole-toman amounts: parsing what people type and showing it back. */
final class Toman
{
    /** Upper bound keeps every sum far below PHP_INT_MAX. */
    public const MAX = 1_000_000_000_000_000;

    /**
     * Accepts Persian/Arabic digits and thousands separators ("۱۰٬۰۰۰٬۰۰۰",
     * "10,000,000", "10 000 000").
     *
     * @throws ValidationException
     */
    public static function parse(string|int|null $value, string $field = 'amount'): int
    {
        if (is_int($value)) {
            $digits = (string) $value;
        } else {
            $normalized = LocalizedNumber::digits((string) $value);
            $digits = (string) preg_replace('/[\s,٬،\'’_]/u', '', $normalized);
        }

        if ($digits === '' || preg_match('/^\d{1,18}$/', $digits) !== 1) {
            throw ValidationException::withMessages([$field => __('bookkeeping.validation.amount')]);
        }

        $amount = (int) $digits;

        if ($amount <= 0 || $amount > self::MAX) {
            throw ValidationException::withMessages([$field => __('bookkeeping.validation.amount')]);
        }

        return $amount;
    }

    public static function format(int $amount): string
    {
        return number_format($amount);
    }
}
