<?php

namespace App\Support\Deals;

use DateTimeZone;
use Exception;

/**
 * Structured Deal Terms, schema v1 (docs/development/M3-C1-ARCHITECTURE.md §2.1).
 * Pure: validates, normalizes to one canonical shape, hashes and renders. No database access.
 */
final class DealTerms
{
    public const SCHEMA = 1;

    private const KEYS = ['schema', 'origin', 'price', 'place', 'time', 'quantity', 'route', 'category', 'fields', 'recurrence', 'effective', 'notes'];

    /** Value kinds a negotiated field may carry; they mirror the Need/Offer field library. */
    public const FIELD_TYPES = ['text', 'long_text', 'number', 'amount', 'boolean', 'choice', 'date', 'year'];

    public const RECURRENCE_UNITS = ['day', 'week', 'month', 'year'];

    /**
     * @param  array<string, mixed>  $terms
     * @return array<string, mixed> canonical terms: empty values dropped, fields sorted by key, decimals as strings
     */
    public static function normalize(array $terms): array
    {
        $unknown = array_diff(array_keys($terms), self::KEYS);
        abort_if($unknown !== [], 422, 'Deal Terms contain unknown keys: '.implode(', ', $unknown).'.');
        abort_unless(($terms['schema'] ?? null) === self::SCHEMA, 422, 'Deal Terms schema version must be 1.');

        $out = ['schema' => self::SCHEMA];

        if ($origin = self::section($terms, 'origin', ['intent_uuid', 'intent_version_id', 'kind'])) {
            $out['origin'] = [
                'intent_uuid' => self::uuid($origin['intent_uuid'] ?? null, 'origin intent'),
                'intent_version_id' => isset($origin['intent_version_id']) ? self::integer($origin['intent_version_id'], 'origin version') : null,
                'kind' => self::text($origin['kind'] ?? null, 20, 'origin kind'),
            ];
        }

        if ($price = self::section($terms, 'price', ['min', 'max', 'currency', 'basis'])) {
            $min = self::decimal($price['min'] ?? null, 'price minimum');
            $max = self::decimal($price['max'] ?? null, 'price maximum');
            abort_if($min !== null && $max !== null && (float) $min > (float) $max, 422, 'Deal Terms price minimum exceeds maximum.');
            $currency = self::text($price['currency'] ?? null, 5, 'currency');
            abort_if(($min !== null || $max !== null) && $currency === null, 422, 'Deal Terms price needs a currency.');
            abort_if($currency !== null && preg_match('/^[A-Za-z]{3,5}$/', $currency) !== 1, 422, 'Deal Terms currency is invalid.');
            $out['price'] = ['min' => $min, 'max' => $max, 'currency' => $currency !== null ? strtoupper($currency) : null, 'basis' => self::text($price['basis'] ?? null, 40, 'price basis')];
        }

        $out['place'] = self::text($terms['place'] ?? null, 255, 'place');

        if ($time = self::section($terms, 'time', ['starts_on', 'ends_on', 'timezone'])) {
            foreach (['starts_on', 'ends_on'] as $key) {
                abort_unless(! isset($time[$key]) || preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $time[$key]) === 1, 422, "Deal Terms time {$key} must be YYYY-MM-DD.");
            }
            abort_if(isset($time['starts_on'], $time['ends_on']) && $time['ends_on'] < $time['starts_on'], 422, 'Deal Terms time ends before it starts.');
            $out['time'] = ['starts_on' => $time['starts_on'] ?? null, 'ends_on' => $time['ends_on'] ?? null, 'timezone' => self::timezone($time['timezone'] ?? null)];
        }

        if ($quantity = self::section($terms, 'quantity', ['value', 'unit'])) {
            $out['quantity'] = ['value' => self::decimal($quantity['value'] ?? null, 'quantity'), 'unit' => self::text($quantity['unit'] ?? null, 40, 'quantity unit')];
        }

        if ($route = self::section($terms, 'route', ['from', 'to'])) {
            $out['route'] = ['from' => self::text($route['from'] ?? null, 255, 'route start'), 'to' => self::text($route['to'] ?? null, 255, 'route end')];
        }

        if ($category = self::section($terms, 'category', ['concept_uuid', 'label'])) {
            $out['category'] = ['concept_uuid' => self::uuid($category['concept_uuid'] ?? null, 'category'), 'label' => self::text($category['label'] ?? null, 120, 'category label')];
        }

        if (isset($terms['fields'])) {
            abort_unless(is_array($terms['fields']) && array_is_list($terms['fields']) && count($terms['fields']) <= 50, 422, 'Deal Terms fields must be a list of at most 50.');
            $fields = [];
            foreach ($terms['fields'] as $field) {
                abort_unless(is_array($field), 422, 'Deal Terms field is invalid.');
                $key = self::text($field['key'] ?? null, 60, 'field key');
                abort_if($key === null || preg_match('/^[A-Za-z0-9_.-]+$/', $key) !== 1, 422, 'Deal Terms field key is invalid.');
                abort_if(isset($fields[$key]), 422, 'Deal Terms field keys must be unique.');
                $value = self::text($field['value'] ?? null, 1000, 'field value');
                if ($value !== null) {
                    $type = self::text($field['type'] ?? null, 20, 'field type');
                    abort_if($type !== null && ! in_array($type, self::FIELD_TYPES, true), 422, 'Deal Terms field type is invalid.');
                    $fields[$key] = array_filter([
                        'key' => $key,
                        'label' => self::text($field['label'] ?? null, 120, 'field label') ?? $key,
                        'value' => $value,
                        'type' => $type,
                        'unit' => self::text($field['unit'] ?? null, 40, 'field unit'),
                    ], fn ($v) => $v !== null);
                }
            }
            ksort($fields);
            $out['fields'] = array_values($fields);
        }

        if ($recurrence = self::section($terms, 'recurrence', ['every', 'unit', 'count'])) {
            $unit = self::text($recurrence['unit'] ?? null, 10, 'recurrence unit');
            abort_unless(in_array($unit, self::RECURRENCE_UNITS, true), 422, 'Deal Terms recurrence unit is invalid.');
            $every = isset($recurrence['every']) && $recurrence['every'] !== '' ? self::integer($recurrence['every'], 'recurrence interval') : 1;
            abort_unless($every >= 1 && $every <= 365, 422, 'Deal Terms recurrence interval is invalid.');
            $count = isset($recurrence['count']) && $recurrence['count'] !== '' ? self::integer($recurrence['count'], 'recurrence count') : null;
            abort_unless($count === null || ($count >= 1 && $count <= 1000), 422, 'Deal Terms recurrence count is invalid.');
            $out['recurrence'] = ['every' => $every, 'unit' => $unit, 'count' => $count];
        }

        $effective = self::section($terms, 'effective', ['mode', 'at', 'timezone']) ?? [];
        $mode = $effective['mode'] ?? 'on_acceptance';
        abort_unless(in_array($mode, ['on_acceptance', 'at'], true), 422, 'Deal Terms effective mode is invalid.');
        if ($mode === 'at') {
            $at = isset($effective['at']) ? strtotime((string) $effective['at']) : false;
            abort_if($at === false, 422, 'Deal Terms effective date is invalid.');
            $out['effective'] = ['mode' => 'at', 'at' => gmdate('Y-m-d\TH:i:s\Z', $at), 'timezone' => self::timezone($effective['timezone'] ?? null) ?? 'UTC'];
        } else {
            $out['effective'] = ['mode' => 'on_acceptance'];
        }

        $out['notes'] = self::text($terms['notes'] ?? null, 10000, 'notes');

        return self::prune($out);
    }

    /** @param array<string, mixed> $normalized */
    public static function canonicalJson(array $normalized): string
    {
        return json_encode(self::sorted($normalized), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $normalized */
    public static function hash(array $normalized): string
    {
        return hash('sha256', self::canonicalJson($normalized));
    }

    /**
     * Readable text for the sealed revision, in the current locale.
     *
     * @param  array<string, mixed>  $terms  normalized terms
     */
    public static function render(array $terms): string
    {
        $line = fn (string $key, ?string $value): ?string => $value === null || $value === '' ? null : __('deals.terms.'.$key).': '.$value;
        $range = function (?string $a, ?string $b): ?string {
            if ($a === null && $b === null) {
                return null;
            }

            return $a !== null && $b !== null && $a !== $b ? $a.' – '.$b : ($a ?? $b);
        };

        $price = $terms['price'] ?? [];
        $priceText = $range($price['min'] ?? null, $price['max'] ?? null);
        $priceText = $priceText === null ? null : trim($priceText.' '.($price['currency'] ?? '').(isset($price['basis']) ? ' / '.$price['basis'] : ''));
        $time = $terms['time'] ?? [];
        $quantity = $terms['quantity'] ?? [];
        $route = $terms['route'] ?? [];
        $effective = $terms['effective'] ?? [];

        $lines = [
            $line('price', $priceText),
            $line('place', $terms['place'] ?? null),
            $line('time', $range($time['starts_on'] ?? null, $time['ends_on'] ?? null)),
            $line('quantity', isset($quantity['value']) || isset($quantity['unit']) ? trim(($quantity['value'] ?? '').' '.($quantity['unit'] ?? '')) : null),
            $line('route', isset($route['from']) || isset($route['to']) ? trim(($route['from'] ?? '').' → '.($route['to'] ?? ''), ' →') : null),
            $line('category', $terms['category']['label'] ?? null),
        ];
        foreach ($terms['fields'] ?? [] as $field) {
            $lines[] = $field['label'].': '.self::displayValue($field);
        }
        if (isset($terms['recurrence'])) {
            $r = $terms['recurrence'];
            $lines[] = __('deals.terms.recurrence').': '.__('deals.recurrence_every', ['every' => $r['every'], 'unit' => __('deals.recurrence_units.'.$r['unit'])]).(isset($r['count']) ? ' × '.$r['count'] : '');
        }
        $lines[] = $line('effective', ($effective['mode'] ?? 'on_acceptance') === 'at'
            ? ($effective['at'] ?? '').' ('.($effective['timezone'] ?? 'UTC').')'
            : __('deals.terms.on_acceptance'));
        $lines[] = $line('notes', $terms['notes'] ?? null);

        return implode("\n", array_filter($lines, fn (?string $l): bool => $l !== null));
    }

    /** A field value as a person reads it: Yes/No, the option's label, the unit after a number. */
    public static function displayValue(array $field): string
    {
        $value = (string) ($field['value'] ?? '');

        return match ($field['type'] ?? 'text') {
            'boolean' => (string) __($value === 'true' ? 'composer.yes' : 'composer.no'),
            'choice' => \App\Support\Market\IntentDetailCatalog::optionLabel($value),
            'number', 'amount' => trim($value.' '.(isset($field['unit']) ? (string) (\App\Support\Market\IntentDetailCatalog::unitLabel($field['unit']) ?? $field['unit']) : '')),
            default => $value,
        };
    }

    /** @return array<string, mixed>|null */
    private static function section(array $terms, string $key, array $allowed): ?array
    {
        if (! isset($terms[$key])) {
            return null;
        }
        abort_unless(is_array($terms[$key]), 422, "Deal Terms {$key} is invalid.");
        $unknown = array_diff(array_keys($terms[$key]), $allowed);
        abort_if($unknown !== [], 422, "Deal Terms {$key} contains unknown keys: ".implode(', ', $unknown).'.');

        return $terms[$key];
    }

    private static function text(mixed $value, int $max, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        abort_unless(is_scalar($value), 422, "Deal Terms {$label} is invalid.");
        $value = trim((string) $value);
        abort_if(mb_strlen($value) > $max, 422, "Deal Terms {$label} is too long.");

        return $value === '' ? null : $value;
    }

    private static function uuid(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        abort_unless(is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1, 422, "Deal Terms {$label} is invalid.");

        return strtolower($value);
    }

    private static function decimal(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        abort_unless(is_int($value) || is_string($value), 422, "Deal Terms {$label} must be a decimal string.");
        abort_unless(preg_match('/^(\d{1,15})(?:\.(\d{1,4}))?$/', trim((string) $value), $m) === 1, 422, "Deal Terms {$label} must be a non-negative decimal.");
        $whole = ltrim($m[1], '0') ?: '0';
        $fraction = rtrim($m[2] ?? '', '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }

    private static function integer(mixed $value, string $label): int
    {
        abort_unless(is_int($value) || (is_string($value) && ctype_digit($value)), 422, "Deal Terms {$label} is invalid.");

        return (int) $value;
    }

    private static function timezone(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return (new DateTimeZone((string) $value))->getName();
        } catch (Exception) {
            abort(422, 'Deal Terms timezone is invalid.');
        }
    }

    /** Drops null values and empty sections. */
    private static function prune(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && $key !== 'fields') {
                $value = self::prune($value);
            }
            if ($value === null || $value === []) {
                unset($data[$key]);
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    private static function sorted(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        return array_map(fn ($v) => is_array($v) ? self::sorted($v) : $v, $data);
    }
}
