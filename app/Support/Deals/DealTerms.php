<?php

namespace App\Support\Deals;

use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\MarketMoney;
use DateTimeZone;
use Exception;

/**
 * Structured Deal Terms, schema v1 (docs/development/M3-C1-ARCHITECTURE.md §2.1).
 * Pure: validates, normalizes to one canonical shape, hashes and renders. No database access.
 */
final class DealTerms
{
    public const SCHEMA = 1;

    private const KEYS = ['schema', 'origin', 'price', 'place', 'time', 'quantity', 'route', 'category', 'fields', 'recurrence', 'effective', 'notes', 'title', 'summary', 'clauses', 'payment', 'completion', 'order'];

    /** Narrative clause kinds of the small clause library. Narrative clauses are text: they never become automation. */
    public const CLAUSE_KINDS = ['scope', 'deliverables', 'duties', 'evidence', 'cancellation', 'disputes', 'confidentiality', 'special', 'custom', 'materials'];

    /** Clauses that describe what someone must do or provide: each can name the party responsible. */
    public const RESPONSIBLE_KINDS = ['duties', 'deliverables', 'evidence', 'materials'];

    /** Typed sections a document may list in its order. */
    public const SECTION_IDS = ['price', 'payment', 'time', 'place', 'quantity', 'route', 'recurrence', 'completion', 'fields', 'notes'];

    /**
     * Currencies a NEW Agreement may be written in. Toman only for now; later exchange and valuation features widen this list.
     * Stored versions are never re-validated or converted, so an older Agreement keeps whatever currency it was accepted in.
     */
    public const SUPPORTED_CURRENCIES = ['IRT'];

    /** Units a price is quoted per. A basis outside this list is a custom text and is kept as written. */
    public const PAYMENT_BASES = ['total', 'item', 'hour', 'day', 'week', 'month', 'project', 'milestone', 'session', 'kilometer', 'square_metre', 'metre', 'cubic_metre', 'kilogram'];

    public const PAYMENT_MODES = ['none', 'on_acceptance', 'after_confirmed_delivery', 'per_period'];

    public const COMPLETION_CONFIRMERS = ['beneficiary', 'both', 'none'];

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
        $out['title'] = self::text($terms['title'] ?? null, 180, 'title');
        $out['summary'] = self::text($terms['summary'] ?? null, 2000, 'summary');

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
            abort_if($currency !== null && ! in_array(strtoupper($currency), self::SUPPORTED_CURRENCIES, true), 422, 'Deal Terms currently support Toman (IRT) only.');
            $out['price'] = ['min' => $min, 'max' => $max, 'currency' => $currency !== null ? strtoupper($currency) : null, 'basis' => self::text($price['basis'] ?? null, 40, 'price basis')];
        }

        $out['place'] = self::text($terms['place'] ?? null, 255, 'place');

        if ($time = self::section($terms, 'time', ['starts_on', 'ends_on', 'timezone', 'daily_from', 'daily_to'])) {
            foreach (['starts_on', 'ends_on'] as $key) {
                abort_unless(! isset($time[$key]) || preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $time[$key]) === 1, 422, "Deal Terms time {$key} must be YYYY-MM-DD.");
            }
            abort_if(isset($time['starts_on'], $time['ends_on']) && $time['ends_on'] < $time['starts_on'], 422, 'Deal Terms time ends before it starts.');
            foreach (['daily_from', 'daily_to'] as $key) {
                abort_unless(! isset($time[$key]) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $time[$key]) === 1, 422, "Deal Terms time {$key} must be HH:MM.");
            }
            abort_if(isset($time['daily_from']) xor isset($time['daily_to']), 422, 'Deal Terms working hours need both a start and an end.');
            abort_if(isset($time['daily_from'], $time['daily_to']) && $time['daily_from'] === $time['daily_to'], 422, 'Deal Terms working hours start and end at the same time.');
            $zone = self::timezone($time['timezone'] ?? null);
            abort_if(isset($time['daily_from']) && $zone === null, 422, 'Deal Terms working hours need a timezone.');
            $out['time'] = [
                'starts_on' => $time['starts_on'] ?? null, 'ends_on' => $time['ends_on'] ?? null,
                'daily_from' => $time['daily_from'] ?? null, 'daily_to' => $time['daily_to'] ?? null, 'timezone' => $zone,
            ];
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

        if (isset($terms['clauses'])) {
            abort_unless(is_array($terms['clauses']) && array_is_list($terms['clauses']) && count($terms['clauses']) <= 40, 422, 'Deal Terms clauses must be a list of at most 40.');
            $clauses = [];
            foreach ($terms['clauses'] as $clause) {
                abort_unless(is_array($clause), 422, 'Deal Terms clause is invalid.');
                $key = self::text($clause['key'] ?? null, 60, 'clause key');
                abort_if($key === null || preg_match('/^[a-z0-9_-]+$/', $key) !== 1 || isset($clauses[$key]), 422, 'Deal Terms clause key is invalid or repeated.');
                $kind = self::text($clause['kind'] ?? 'custom', 40, 'clause kind');
                abort_unless(in_array($kind, self::CLAUSE_KINDS, true), 422, 'Deal Terms clause kind is invalid.');
                $title = self::text($clause['title'] ?? null, 120, 'clause title');
                $text = self::text($clause['text'] ?? null, 4000, 'clause text');
                if ($title === null && $text === null) {
                    continue;
                }
                $responsible = self::text($clause['responsible'] ?? null, 40, 'clause party');
                abort_if($responsible !== null && preg_match('/^(actor|business|group):\d+$/', $responsible) !== 1, 422, 'Deal Terms clause party is invalid.');
                $clauses[$key] = array_filter(['key' => $key, 'kind' => $kind, 'title' => $title, 'text' => $text, 'responsible' => in_array($kind, self::RESPONSIBLE_KINDS, true) ? $responsible : null], fn ($v) => $v !== null);
            }
            $out['clauses'] = array_values($clauses);
        }

        if ($payment = self::section($terms, 'payment', ['mode', 'period', 'note'])) {
            $mode = self::text($payment['mode'] ?? null, 40, 'payment mode');
            abort_unless(in_array($mode, self::PAYMENT_MODES, true), 422, 'Deal Terms payment mode is invalid.');
            $period = self::text($payment['period'] ?? null, 10, 'payment period');
            abort_if($mode === 'per_period' && ! in_array($period, ['week', 'month'], true), 422, 'Deal Terms periodic payment needs a week or month period.');
            $out['payment'] = ['mode' => $mode, 'period' => $mode === 'per_period' ? $period : null, 'note' => self::text($payment['note'] ?? null, 500, 'payment note')];
        }

        if ($completion = self::section($terms, 'completion', ['confirmed_by'])) {
            $by = self::text($completion['confirmed_by'] ?? null, 20, 'completion confirmer');
            abort_unless(in_array($by, self::COMPLETION_CONFIRMERS, true), 422, 'Deal Terms completion confirmer is invalid.');
            $out['completion'] = ['confirmed_by' => $by];
        }

        if (isset($terms['order'])) {
            abort_unless(is_array($terms['order']) && array_is_list($terms['order']) && count($terms['order']) <= 80, 422, 'Deal Terms order is invalid.');
            $known = [...self::SECTION_IDS, ...array_map(fn ($c) => 'clause:'.$c['key'], $out['clauses'] ?? [])];
            $order = [];
            foreach ($terms['order'] as $id) {
                abort_unless(is_string($id) && in_array($id, $known, true), 422, 'Deal Terms order refers to an unknown item.');
                $order[$id] = $id;
            }
            $out['order'] = array_values($order);
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
        $priceText = isset($price['min']) || isset($price['max']) ? self::priceLabel($price) : null;
        $time = $terms['time'] ?? [];
        $quantity = $terms['quantity'] ?? [];
        $route = $terms['route'] ?? [];
        $effective = $terms['effective'] ?? [];

        $lines = [
            $line('price', $priceText),
            $line('place', $terms['place'] ?? null),
            $line('time', trim(($range($time['starts_on'] ?? null, $time['ends_on'] ?? null) ?? '').(isset($time['daily_from']) ? ' '.$time['daily_from'].'–'.$time['daily_to'].' ('.($time['timezone'] ?? '').')' : ''))),
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
        if (isset($terms['payment'])) {
            $p = $terms['payment'];
            $lines[] = __('deals.terms.payment').': '.__('deals.payment_modes.'.$p['mode']).(isset($p['period']) ? ' ('.__('deals.recurrence_units.'.$p['period']).')' : '').(isset($p['note']) ? ' — '.$p['note'] : '');
        }
        if (isset($terms['completion'])) {
            $lines[] = __('deals.terms.completion').': '.__('deals.completion_by.'.$terms['completion']['confirmed_by']);
        }
        foreach ($terms['clauses'] ?? [] as $clause) {
            $lines[] = ($clause['title'] ?? __('deals.clauses.'.$clause['kind'].'.title')).': '.($clause['text'] ?? '');
        }
        $lines[] = $line('effective', ($effective['mode'] ?? 'on_acceptance') === 'at'
            ? ($effective['at'] ?? '').' ('.($effective['timezone'] ?? 'UTC').')'
            : __('deals.terms.on_acceptance'));
        $lines[] = $line('notes', $terms['notes'] ?? null);

        return implode("\n", array_filter($lines, fn (?string $l): bool => $l !== null));
    }

    /**
     * A price as people read it: whole Toman with separators and the unit ("3,000,000 Toman / square metre"); a price an
     * older Agreement wrote in another currency is shown exactly as written, never converted.
     *
     * @param  array{min?: ?string, max?: ?string, currency?: ?string, basis?: ?string}  $price
     */
    public static function priceLabel(array $price): string
    {
        $show = fn (?string $amount): ?string => $amount === null || $amount === '' ? null
            : (strtoupper((string) ($price['currency'] ?? '')) === 'IRT' ? MarketMoney::format($amount) : trim($amount.' '.($price['currency'] ?? '')));
        $min = $show($price['min'] ?? null);
        $max = $show($price['max'] ?? null);
        $amount = $min !== null && $max !== null && $min !== $max ? $min.' – '.$max : ($min ?? $max);
        $basis = self::basisLabel($price['basis'] ?? null);

        return trim(($amount ?? '').($basis !== null ? ' / '.$basis : ''));
    }

    /** The unit a price is quoted per, in words: a known unit is translated, a custom one is shown as written. */
    public static function basisLabel(?string $basis): ?string
    {
        if ($basis === null || $basis === '') {
            return null;
        }

        return in_array($basis, self::PAYMENT_BASES, true) ? (string) __('deals.bases.'.$basis) : $basis;
    }

    /** A field value as a person reads it: Yes/No, the option's label, the unit after a number. */
    public static function displayValue(array $field): string
    {
        $value = (string) ($field['value'] ?? '');

        return match ($field['type'] ?? 'text') {
            'boolean' => (string) __($value === 'true' ? 'composer.yes' : 'composer.no'),
            'choice' => IntentDetailCatalog::optionLabel($value),
            'number', 'amount' => trim($value.' '.(isset($field['unit']) ? (string) (IntentDetailCatalog::unitLabel($field['unit']) ?? $field['unit']) : '')),
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
            if (is_array($value) && ! in_array($key, ['fields', 'clauses', 'order'], true)) {
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
