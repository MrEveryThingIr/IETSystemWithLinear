<?php

namespace App\Support\Market;

use App\Support\LocalizedNumber;
use Illuminate\Validation\ValidationException;

/**
 * What a detail says, by kind of post. An Offer states a fact (one value). A Need may state exactly
 * the same, or an acceptable range or set. Only operators that make sense for the field's type are
 * offered. Descriptive attribute constraints never replace canonical price, quantity or schedule terms.
 *
 * Constraint shape: ['op' => exact|min|max|between|one_of, 'min' => ?, 'max' => ?, 'values' => ?]
 * (min and max are inclusive). "Exactly" lives in the payload's plain value. "Any" is simply no row.
 * Comparison answers true, false, or null (unknown): unknown is never treated as zero or as a match.
 */
final class DetailValue
{
    public const OPERATORS = ['exact', 'min', 'max', 'between', 'one_of'];

    /** @return list<string> */
    public static function operators(string $type, bool $need): array
    {
        if (! $need) {
            return ['exact'];
        }

        return match ($type) {
            'number', 'amount', 'year', 'date' => ['exact', 'min', 'max', 'between'],
            'choice' => ['exact', 'one_of'],
            default => ['exact'],
        };
    }

    /**
     * Validates and structures a non-exact input. Returns the constraint, or throws a localized error.
     *
     * @param  array<string, mixed>  $input  op, min, max, values
     * @param  list<string>  $options
     * @return array<string, mixed>
     */
    public static function constraint(string $type, string $op, array $input, array $options = [], ?string $calendar = null): array
    {
        if (! in_array($op, self::operators($type, true), true) || $op === 'exact') {
            throw ValidationException::withMessages(['details' => __('composer.errors.operator')]);
        }
        if ($op === 'one_of') {
            $values = array_values(array_unique(array_map('strval', (array) ($input['values'] ?? []))));
            if ($values === [] || array_diff($values, $options) !== []) {
                throw ValidationException::withMessages(['details' => __('composer.errors.choose_one')]);
            }

            return ['op' => 'one_of', 'values' => $values];
        }
        $bounds = [];
        foreach (['min', 'max'] as $side) {
            if (($op === 'between' || $op === $side)) {
                $bounds[$side] = self::scalar($type, $input[$side] ?? null, $calendar);
            }
        }
        if ($op === 'between' && self::compare($type, $bounds['min'], $bounds['max'], $calendar) > 0) {
            throw ValidationException::withMessages(['details' => __('composer.errors.range_order')]);
        }

        return ['op' => $op, ...$bounds];
    }

    /** One bound or exact value in its stored form. */
    public static function scalar(string $type, mixed $value, ?string $calendar = null): string|int
    {
        $text = LocalizedNumber::decimal((string) $value);
        if ($type === 'date') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) !== 1) {
                throw ValidationException::withMessages(['details' => __('composer.errors.date')]);
            }

            return $text;
        }
        if (! is_numeric($text) || ($type === 'year' && preg_match('/^\d{3,4}$/', $text) !== 1)) {
            throw ValidationException::withMessages(['details' => $type === 'year' ? __('composer.errors.year') : __('composer.errors.number')]);
        }
        if ($type === 'year') {
            $year = (int) $text;
            if (! YearSpan::valid($year, $calendar ?? YearSpan::guessCalendar($year))) {
                throw ValidationException::withMessages(['details' => __('composer.errors.year_calendar')]);
            }

            return $year;
        }

        return $text;
    }

    /** @return array{0: string|float|null, 1: string|float|null} comparable interval of a single value */
    private static function span(string $type, mixed $value, ?string $calendar): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }
        if ($type === 'year') {
            [$from, $to] = YearSpan::range((int) $value, $calendar ?? YearSpan::guessCalendar((int) $value));

            return [$from->format('Y-m-d'), $to->format('Y-m-d')];
        }
        if ($type === 'date') {
            return [(string) $value, (string) $value];
        }

        return [(float) $value, (float) $value];
    }

    private static function compare(string $type, mixed $a, mixed $b, ?string $calendar): int
    {
        return self::span($type, $a, $calendar)[0] <=> self::span($type, $b, $calendar)[0];
    }

    /**
     * Does an Offer's stated value satisfy a Need's wish? true / false / null when it cannot be known.
     *
     * @param  array<string, mixed>  $need  a Need's detail payload
     * @param  array<string, mixed>  $offer  an Offer's detail payload
     */
    public static function satisfies(string $type, array $need, array $offer): ?bool
    {
        if (! empty($offer['constraint']) || ! array_key_exists('value', $offer) || self::blank($offer['value'])) {
            return null;
        }
        $constraint = $need['constraint'] ?? null;
        if ($constraint === null && self::blank($need['value'] ?? null)) {
            return null;
        }
        $offered = $offer['value'];

        return match ($type) {
            'boolean' => $constraint === null ? (bool) $offered === (bool) $need['value'] : null,
            'choice' => match ($constraint['op'] ?? 'exact') {
                'one_of' => in_array((string) $offered, $constraint['values'] ?? [], true),
                default => (string) $offered === (string) ($need['value'] ?? ''),
            },
            'number', 'amount' => self::numeric($need, $constraint, (float) $offered),
            'year', 'date' => self::temporal($type, $need, $offer, $constraint),
            default => null,
        };
    }

    /** @param array<string, mixed>|null $constraint */
    private static function numeric(array $need, ?array $constraint, float $offered): bool
    {
        $op = $constraint['op'] ?? 'exact';
        $epsilon = 0.0000005;

        return match ($op) {
            'min' => $offered + $epsilon >= (float) $constraint['min'],
            'max' => $offered - $epsilon <= (float) $constraint['max'],
            'between' => $offered + $epsilon >= (float) $constraint['min'] && $offered - $epsilon <= (float) $constraint['max'],
            default => abs($offered - (float) $need['value']) <= $epsilon,
        };
    }

    /**
     * Years are spans, so a wish and an offer are compatible when their spans overlap.
     *
     * @param  array<string, mixed>  $need
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>|null  $constraint
     */
    private static function temporal(string $type, array $need, array $offer, ?array $constraint): bool
    {
        $calendar = $need['calendar'] ?? null;
        [$offerFrom, $offerTo] = self::span($type, $offer['value'], $offer['calendar'] ?? null);
        $op = $constraint['op'] ?? 'exact';
        $from = match ($op) {
            'min', 'between' => self::span($type, $constraint['min'], $calendar)[0],
            'max' => null,
            default => self::span($type, $need['value'], $calendar)[0],
        };
        $to = match ($op) {
            'max', 'between' => self::span($type, $constraint['max'], $calendar)[1],
            'min' => null,
            default => self::span($type, $need['value'], $calendar)[1],
        };

        return ($from === null || $offerTo >= $from) && ($to === null || $offerFrom <= $to);
    }

    public static function blank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** A person-readable statement of a value or constraint, without the unit. */
    public static function describe(string $type, array $payload, ?string $calendarView = null): string
    {
        $constraint = $payload['constraint'] ?? null;
        $show = fn ($value): string => self::show($type, $value, $payload['calendar'] ?? null, $calendarView);
        if ($constraint === null) {
            return $show($payload['value'] ?? '');
        }

        return match ($constraint['op']) {
            'min' => (string) __('composer.constraints.min', ['value' => $show($constraint['min'])]),
            'max' => (string) __('composer.constraints.max', ['value' => $show($constraint['max'])]),
            'between' => (string) __('composer.constraints.between', ['min' => $show($constraint['min']), 'max' => $show($constraint['max'])]),
            'one_of' => implode(' / ', array_map(fn ($option): string => IntentDetailCatalog::optionLabel((string) $option), $constraint['values'])),
            default => $show($payload['value'] ?? ''),
        };
    }

    private static function show(string $type, mixed $value, ?string $calendar, ?string $view): string
    {
        if ($type === 'choice') {
            return IntentDetailCatalog::optionLabel((string) $value);
        }
        if ($type === 'year' && $value !== null && $value !== '') {
            $stored = $calendar ?? YearSpan::guessCalendar((int) $value);
            $text = (string) (int) $value;
            if ($view !== null && $view !== $stored) {
                return $text.' ('.__('composer.about_year', ['year' => YearSpan::approximate((int) $value, $stored, $view)]).')';
            }

            return $text;
        }
        if ($type === 'amount' && is_numeric($value)) {
            return MarketMoney::format((string) $value);
        }

        return (string) $value;
    }
}
