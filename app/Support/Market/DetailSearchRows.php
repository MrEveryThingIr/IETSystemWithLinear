<?php

namespace App\Support\Market;

/**
 * The searchable form of one published detail. A single fact becomes one row; a wish (range or set)
 * becomes rows that carry its bounds, so the Directory can match on overlap without guessing.
 * Years and dates are stored as day spans, never as bare numbers.
 */
final class DetailSearchRows
{
    /**
     * @param  array<string, mixed>  $spec  library definition (type, filter)
     * @param  array<string, mixed>  $payload  published detail payload
     * @return list<array<string, mixed>>
     */
    public static function rows(array $spec, array $payload): array
    {
        $filter = $spec['filter'] ?? null;
        $type = (string) ($spec['type'] ?? 'text');
        $constraint = $payload['constraint'] ?? null;
        $value = $payload['value'] ?? null;
        if ($filter === null) {
            return [];
        }
        if ($filter === 'value_boolean') {
            return $value ? [['value_boolean' => true]] : [];
        }
        if ($filter === 'value_text') {
            $values = $constraint !== null ? ($constraint['values'] ?? []) : (DetailValue::blank($value) ? [] : [(string) $value]);

            return array_map(fn ($text): array => ['value_text' => mb_substr((string) $text, 0, 255)], $values);
        }
        if ($filter === 'value_number') {
            if ($constraint === null) {
                return DetailValue::blank($value) ? [] : [['value_number' => $value]];
            }

            return [['value_number_min' => $constraint['min'] ?? null, 'value_number_max' => $constraint['max'] ?? null]];
        }
        if ($filter === 'value_date') {
            $calendar = $payload['calendar'] ?? null;
            $start = fn ($bound): ?string => DetailValue::blank($bound) ? null : self::bounds($type, $bound, $calendar)[0];
            $end = fn ($bound): ?string => DetailValue::blank($bound) ? null : self::bounds($type, $bound, $calendar)[1];
            if ($constraint === null) {
                return DetailValue::blank($value) ? [] : [['value_date' => $start($value), 'value_date_end' => $end($value)]];
            }

            return [['value_date' => $start($constraint['min'] ?? null), 'value_date_end' => $end($constraint['max'] ?? null)]];
        }

        return [];
    }

    /** @return array{0: string, 1: string} */
    private static function bounds(string $type, mixed $value, ?string $calendar): array
    {
        if ($type === 'year') {
            [$from, $to] = YearSpan::range((int) $value, $calendar ?? YearSpan::guessCalendar((int) $value));

            return [$from->format('Y-m-d'), $to->format('Y-m-d')];
        }

        return [(string) $value, (string) $value];
    }
}
