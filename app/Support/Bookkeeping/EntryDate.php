<?php

namespace App\Support\Bookkeeping;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Civil dates (Y-m-d) for bookkeeping entries; no time zone, never shifted. */
final class EntryDate
{
    /** @throws ValidationException */
    public static function parse(string $value, string $field): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (Throwable) {
            $date = false;
        }

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value || $date->year < 1900 || $date->year > 2200) {
            throw ValidationException::withMessages([$field => __('bookkeeping.validation.date')]);
        }

        return $date;
    }
}
