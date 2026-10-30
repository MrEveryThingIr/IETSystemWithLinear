<?php

namespace App\Support\Deals;

use App\Models\IntentVersion;
use App\Models\ProposalVersion;
use App\Models\Relationship;
use App\Support\Market\DetailValue;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\MarketMoney;
use Illuminate\Support\Str;

/**
 * The editable Agreement draft. It starts from the pinned Need/Offer, then from the latest negotiated version, and always
 * turns back into schema-1 Terms. `items` is the ordered list of what the document contains: typed sections
 * (price, payment, time, place, quantity, route, recurrence, completion, fields, notes) and narrative clauses ("clause:<key>").
 * Pure apart from reading the pinned version.
 */
final class DealTermsDraft
{
    public const CORE = ['price_min', 'price_max', 'currency', 'basis', 'place', 'starts_on', 'ends_on', 'daily_from', 'daily_to', 'timezone', 'quantity', 'unit', 'from', 'to', 'basis_choice', 'basis_custom', 'price_mode'];

    /** @return array<string, mixed> */
    public static function blank(): array
    {
        return [
            'title' => '', 'summary' => '', 'items' => [],
            'core' => array_fill_keys(self::CORE, ''),
            'fields' => [],
            'rates' => [],
            'recurrence' => ['every' => '', 'unit' => '', 'count' => ''],
            'payment' => ['mode' => '', 'period' => '', 'note' => ''],
            'completion' => ['confirmed_by' => ''],
            'clauses' => [],
            'notes' => '',
        ];
    }

    /** The draft people see next: the latest negotiated version, or the pinned Need/Offer when nothing has been proposed. */
    public static function start(Relationship $deal, ?ProposalVersion $latest): array
    {
        return $latest?->terms_data !== null ? self::fromTerms($latest->terms_data) : self::fromOrigin($deal);
    }

    /** @param array<string, mixed> $t normalized Terms */
    public static function fromTerms(array $t): array
    {
        $d = self::blank();
        $d['title'] = (string) ($t['title'] ?? '');
        $d['summary'] = (string) ($t['summary'] ?? '');
        $d['core'] = array_replace($d['core'], array_map('strval', array_filter([
            'price_min' => $t['price']['min'] ?? null, 'price_max' => $t['price']['max'] ?? null, 'currency' => $t['price']['currency'] ?? null,
            'basis' => $t['price']['basis'] ?? null, 'place' => $t['place'] ?? null,
            'starts_on' => $t['time']['starts_on'] ?? null, 'ends_on' => $t['time']['ends_on'] ?? null,
            'daily_from' => $t['time']['daily_from'] ?? null, 'daily_to' => $t['time']['daily_to'] ?? null, 'timezone' => $t['time']['timezone'] ?? null,
            'quantity' => $t['quantity']['value'] ?? null, 'unit' => $t['quantity']['unit'] ?? null,
            'from' => $t['route']['from'] ?? null, 'to' => $t['route']['to'] ?? null,
        ], fn ($v) => $v !== null)));
        self::splitBasis($d);
        $d['core']['price_mode'] = isset($t['price']['min'], $t['price']['max']) && $t['price']['min'] !== $t['price']['max'] ? 'range' : 'fixed';
        foreach ($t['fields'] ?? [] as $f) {
            $d['fields'][] = ['key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'] ?? 'text', 'unit' => $f['unit'] ?? '', 'value' => $f['value']];
        }
        foreach ($t['rates'] ?? [] as $r) {
            $d['rates'][] = ['key' => $r['key'], 'label' => $r['label'], 'unit' => $r['unit'], 'rate' => $r['rate'], 'estimate' => $r['estimate'] ?? ''];
        }
        $d['recurrence'] = array_replace($d['recurrence'], array_map('strval', array_filter($t['recurrence'] ?? [], fn ($v) => $v !== null)));
        $d['payment'] = array_replace($d['payment'], array_map('strval', array_filter($t['payment'] ?? [], fn ($v) => $v !== null)));
        $d['completion'] = array_replace($d['completion'], array_map('strval', $t['completion'] ?? []));
        foreach ($t['clauses'] ?? [] as $c) {
            $d['clauses'][] = ['key' => $c['key'], 'kind' => $c['kind'], 'title' => $c['title'] ?? '', 'text' => $c['text'] ?? '', 'responsible' => $c['responsible'] ?? ''];
        }
        $d['notes'] = (string) ($t['notes'] ?? '');
        $d['items'] = $t['order'] ?? self::defaultItems($d);

        return $d;
    }

    public static function fromOrigin(Relationship $deal): array
    {
        $d = self::blank();
        $version = $deal->originating_intent_version_id ? IntentVersion::query()->find($deal->originating_intent_version_id) : null;
        if (! $version instanceof IntentVersion) {
            return $d;
        }

        $snapshot = $version->snapshot;
        $core = $snapshot['core'] ?? [];
        $d['title'] = (string) ($core['title'] ?? '');
        $min = MarketMoney::legacyToman(isset($core['cash_min']) ? (string) $core['cash_min'] : null, $core['currency_code'] ?? null);
        $max = MarketMoney::legacyToman(isset($core['cash_max']) ? (string) $core['cash_max'] : null, $core['currency_code'] ?? null);
        $d['core'] = array_replace($d['core'], array_map('strval', array_filter([
            'price_min' => $min, 'price_max' => $max, 'currency' => $min !== null || $max !== null ? 'IRT' : null, 'basis' => $core['cash_basis'] ?? null,
            'place' => $core['location_text'] ?? null, 'starts_on' => $core['starts_on'] ?? null, 'ends_on' => $core['ends_on'] ?? null,
            'quantity' => $core['quantity'] ?? null, 'unit' => $core['unit'] ?? null,
            'from' => $core['origin_text'] ?? null, 'to' => $core['destination_text'] ?? null,
        ], fn ($v) => $v !== null && $v !== '')));

        self::splitBasis($d);
        $d['core']['price_mode'] = $min !== null && $max !== null && $min !== $max ? 'range' : 'fixed';

        $catalog = IntentDetailCatalog::all();
        $topic = $deal->originatingIntent?->metadata['composer_topic'] ?? null;
        $seen = [];

        foreach ($snapshot['details'] ?? [] as $detail) {
            if (($detail['visibility'] ?? 'same') === 'private') {
                continue; // a private detail never becomes a Term the other side can read
            }
            $payload = $detail['payload'] ?? ['value' => $detail['value'] ?? null];
            $value = $payload['value'] ?? null;
            $key = $detail['canonical_key'] ?? null;
            $spec = $key !== null ? ($catalog[$key] ?? []) : [];
            $type = $spec['type'] ?? 'text';

            if (($payload['constraint'] ?? null) !== null) {
                $text = DetailValue::describe($type, $payload);
                $type = 'text';
            } elseif (is_bool($value)) {
                $text = $value ? 'true' : 'false';
                $type = 'boolean';
            } elseif (is_scalar($value) && (string) $value !== '') {
                $text = (string) $value;
            } elseif (is_array($value) && $value !== [] && array_is_list($value) && array_filter($value, 'is_scalar') === $value) {
                $text = implode(', ', $value);
                $type = 'text';
            } else {
                continue;
            }

            $type = in_array($type, DealTerms::FIELD_TYPES, true) ? $type : 'text';
            $label = $key !== null ? IntentDetailCatalog::label($key, null, $topic) : (string) ($detail['label'] ?? '');
            $fieldKey = $key ?? 'custom_'.Str::slug((string) ($detail['label'] ?? 'detail'), '_');
            if ($label === '' || isset($seen[$fieldKey])) {
                continue;
            }
            $seen[$fieldKey] = true;
            $d['fields'][] = ['key' => $fieldKey, 'label' => $label, 'type' => $type, 'unit' => (string) ($spec['unit'] ?? ''), 'value' => $text];
        }

        $d['items'] = self::defaultItems($d);

        return $d;
    }

    /** The price unit is a pick from the known units, or a custom text kept as written. */
    private static function splitBasis(array &$d): void
    {
        $basis = (string) ($d['core']['basis'] ?? '');
        if ($basis === '') {
            return;
        }
        $known = in_array($basis, DealTerms::PAYMENT_BASES, true);
        $d['core']['basis_choice'] = $known ? $basis : '__other';
        $d['core']['basis_custom'] = $known ? '' : $basis;
    }

    /** Sections that already carry something, in the order a reader expects, then the narrative clauses. */
    public static function defaultItems(array $d): array
    {
        $c = $d['core'];
        $filled = fn (array $keys): bool => collect($keys)->contains(fn ($k) => trim((string) ($c[$k] ?? '')) !== '');
        $items = [];
        $on = [
            'price' => $filled(['price_min', 'price_max', 'basis']),
            'payment' => ($d['payment']['mode'] ?? '') !== '',
            'time' => $filled(['starts_on', 'ends_on', 'daily_from', 'daily_to']),
            'place' => $filled(['place']),
            'quantity' => $filled(['quantity', 'unit']),
            'route' => $filled(['from', 'to']),
            'recurrence' => ($d['recurrence']['unit'] ?? '') !== '',
            'completion' => ($d['completion']['confirmed_by'] ?? '') !== '',
            'fields' => ($d['fields'] ?? []) !== [],
            'rates' => ($d['rates'] ?? []) !== [],
            'notes' => trim((string) ($d['notes'] ?? '')) !== '',
        ];
        foreach (DealTerms::SECTION_IDS as $id) {
            if ($on[$id]) {
                $items[] = $id;
            }
        }
        foreach ($d['clauses'] ?? [] as $clause) {
            $items[] = 'clause:'.$clause['key'];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed> schema-1 Terms (origin is added by the action). Only what is in `items` is included.
     */
    public static function toTerms(array $d): array
    {
        $items = array_values(array_unique($d['items'] ?? []));
        $has = fn (string $id): bool => in_array($id, $items, true);
        $c = fn (string $k): ?string => ($x = trim((string) ($d['core'][$k] ?? ''))) === '' ? null : $x;

        $fields = [];
        foreach ($has('fields') ? ($d['fields'] ?? []) : [] as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $key = Str::slug((string) ($row['key'] ?? ''), '_') ?: 'custom_'.Str::slug((string) ($row['label'] ?? 'detail'), '_');
            $fields[] = array_filter([
                'key' => $key,
                'label' => trim((string) ($row['label'] ?? '')) ?: $key,
                'value' => $value,
                'type' => $row['type'] ?? 'text',
                'unit' => trim((string) ($row['unit'] ?? '')) ?: null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        $clauses = [];
        foreach ($d['clauses'] ?? [] as $clause) {
            // A clause nobody wrote anything in is not part of the Terms (and so not of their order either).
            if (! $has('clause:'.$clause['key']) || (trim((string) ($clause['title'] ?? '')) === '' && trim((string) ($clause['text'] ?? '')) === '')) {
                continue;
            }
            $clauses[] = array_filter([
                'key' => $clause['key'], 'kind' => $clause['kind'],
                'title' => trim((string) ($clause['title'] ?? '')) ?: null, 'text' => trim((string) ($clause['text'] ?? '')) ?: null,
                'responsible' => trim((string) ($clause['responsible'] ?? '')) ?: null,
            ], fn ($v) => $v !== null);
        }

        $rates = [];
        foreach ($d['rates'] ?? [] as $row) {
            $rates[] = ['key' => $row['key'], 'label' => trim((string) ($row['label'] ?? '')), 'unit' => trim((string) ($row['unit'] ?? '')), 'rate' => trim((string) ($row['rate'] ?? '')), 'estimate' => trim((string) ($row['estimate'] ?? '')) ?: null];
        }
        $r = $d['recurrence'] ?? [];
        $p = $d['payment'] ?? [];
        $basis = $c('basis_choice') === '__other' ? $c('basis_custom') : ($c('basis_choice') ?: $c('basis'));
        // One price by default: the amount is both ends. A range is written only when the person chose one.
        $priceMax = ($c('price_mode') ?: 'fixed') === 'fixed' ? $c('price_min') : $c('price_max');
        $present = array_filter($items, fn ($id) => str_starts_with($id, 'clause:') ? collect($clauses)->contains(fn ($cl) => 'clause:'.$cl['key'] === $id) : true);

        return array_filter([
            'schema' => DealTerms::SCHEMA,
            'title' => trim((string) ($d['title'] ?? '')) ?: null,
            'summary' => trim((string) ($d['summary'] ?? '')) ?: null,
            'price' => $has('price') && ($c('price_min') || $c('price_max') || $basis) ? ['min' => $c('price_min'), 'max' => $priceMax, 'currency' => $c('currency'), 'basis' => $basis] : null,
            'payment' => $has('payment') && trim((string) ($p['mode'] ?? '')) !== ''
                ? ['mode' => $p['mode'], 'period' => trim((string) ($p['period'] ?? '')) ?: null, 'note' => trim((string) ($p['note'] ?? '')) ?: null]
                : null,
            'place' => $has('place') ? $c('place') : null,
            'time' => $has('time') && ($c('starts_on') || $c('ends_on') || $c('daily_from')) ? ['starts_on' => $c('starts_on'), 'ends_on' => $c('ends_on'), 'daily_from' => $c('daily_from'), 'daily_to' => $c('daily_to'), 'timezone' => $c('daily_from') ? ($c('timezone') ?: 'UTC') : null] : null,
            'quantity' => $has('quantity') && ($c('quantity') || $c('unit')) ? ['value' => $c('quantity'), 'unit' => $c('unit')] : null,
            'route' => $has('route') && ($c('from') || $c('to')) ? ['from' => $c('from'), 'to' => $c('to')] : null,
            'fields' => $fields ?: null,
            'rates' => $has('rates') && $rates !== [] ? $rates : null,
            'recurrence' => $has('recurrence') && trim((string) ($r['unit'] ?? '')) !== ''
                ? ['every' => trim((string) ($r['every'] ?? '')) ?: 1, 'unit' => $r['unit'], 'count' => trim((string) ($r['count'] ?? '')) ?: null]
                : null,
            'completion' => $has('completion') && trim((string) ($d['completion']['confirmed_by'] ?? '')) !== '' ? ['confirmed_by' => $d['completion']['confirmed_by']] : null,
            'clauses' => $clauses ?: null,
            'notes' => $has('notes') ? (trim((string) ($d['notes'] ?? '')) ?: null) : null,
            'order' => array_values($present) ?: null,
        ], fn ($x) => $x !== null);
    }
}
