<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;
use Throwable;

class LocalDateTime
{
    public static function toUtc(string $value, string $timezone, string $field): CarbonImmutable
    {
        try {
            $zone = new DateTimeZone($timezone);
            $local = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, $zone);
        } catch (Throwable) {
            $local = null;
        }

        if (! $local instanceof CarbonImmutable || $local->format('Y-m-d\TH:i') !== $value) {
            throw ValidationException::withMessages([
                $field => __('ui.messages.invalid_local_datetime', ['timezone' => $timezone]),
            ]);
        }

        return $local->utc();
    }
}
