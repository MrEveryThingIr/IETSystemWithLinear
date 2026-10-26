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
 * The editable shape of Deal Terms. A draft starts from the pinned Need/Offer, then from the latest
 * negotiated version, and always turns back into schema-1 Terms. Pure apart from reading the pinned version.
 */
final class DealTermsDraft
{
    public const CORE = ['price_min', 'price_max', 'currency', 'basis', 'place', 'starts_on', 'ends_on', 'quantity', 'unit', 'from', 'to'];

    /** @return array{core: array<string, string>, fields: list<array<string, string>>, recurrence: array<string, string>, notes: string} */
    public static function blank(): array
    {
        return ['core' => array_fill_keys(self::CORE, ''), 'fields' => [], 'recurrence' => ['every' => '', 'unit' => '', 'count' => ''], 'notes' => ''];
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
        $d['core'] = array_replace($d['core'], array_map('strval', array_filter([
            'price_min' => $t['price']['min'] ?? null, 'price_max' => $t['price']['max'] ?? null, 'currency' => $t['price']['currency'] ?? null,
            'basis' => $t['price']['basis'] ?? null, 'place' => $t['place'] ?? null,
            'starts_on' => $t['time']['starts_on'] ?? null, 'ends_on' => $t['time']['ends_on'] ?? null,
            'quantity' => $t['quantity']['value'] ?? null, 'unit' => $t['quantity']['unit'] ?? null,
            'from' => $t['route']['from'] ?? null, 'to' => $t['route']['to'] ?? null,
        ], fn ($v) => $v !== null)));
        foreach ($t['fields'] ?? [] as $f) {
            $d['fields'][] = ['key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'] ?? 'text', 'unit' => $f['unit'] ?? '', 'value' => $f['value']];
        }
        $d['recurrence'] = array_replace($d['recurrence'], array_map('strval', array_filter($t['recurrence'] ?? [], fn ($v) => $v !== null)));
        $d['notes'] = (string) ($t['notes'] ?? '');

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
        $min = MarketMoney::legacyToman(isset($core['cash_min']) ? (string) $core['cash_min'] : null, $core['currency_code'] ?? null);
        $max = MarketMoney::legacyToman(isset($core['cash_max']) ? (string) $core['cash_max'] : null, $core['currency_code'] ?? null);
        $d['core'] = array_replace($d['core'], array_map('strval', array_filter([
            'price_min' => $min, 'price_max' => $max, 'currency' => $min !== null || $max !== null ? 'IRT' : null, 'basis' => $core['cash_basis'] ?? null,
            'place' => $core['location_text'] ?? null, 'starts_on' => $core['starts_on'] ?? null, 'ends_on' => $core['ends_on'] ?? null,
            'quantity' => $core['quantity'] ?? null, 'unit' => $core['unit'] ?? null,
            'from' => $core['origin_text'] ?? null, 'to' => $core['destination_text'] ?? null,
        ], fn ($v) => $v !== null && $v !== '')));

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

        return $d;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed> schema-1 Terms (origin is added by the action)
     */
    public static function toTerms(array $d): array
    {
        $c = fn (string $k): ?string => ($x = trim((string) ($d['core'][$k] ?? ''))) === '' ? null : $x;
        $fields = [];
        foreach ($d['fields'] ?? [] as $row) {
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
        $r = $d['recurrence'] ?? [];

        return array_filter([
            'schema' => DealTerms::SCHEMA,
            'price' => $c('price_min') || $c('price_max') || $c('basis') ? ['min' => $c('price_min'), 'max' => $c('price_max'), 'currency' => $c('currency'), 'basis' => $c('basis')] : null,
            'place' => $c('place'),
            'time' => $c('starts_on') || $c('ends_on') ? ['starts_on' => $c('starts_on'), 'ends_on' => $c('ends_on')] : null,
            'quantity' => $c('quantity') || $c('unit') ? ['value' => $c('quantity'), 'unit' => $c('unit')] : null,
            'route' => $c('from') || $c('to') ? ['from' => $c('from'), 'to' => $c('to')] : null,
            'fields' => $fields ?: null,
            'recurrence' => trim((string) ($r['unit'] ?? '')) !== ''
                ? ['every' => trim((string) ($r['every'] ?? '')) ?: 1, 'unit' => $r['unit'], 'count' => trim((string) ($r['count'] ?? '')) ?: null]
                : null,
            'notes' => trim((string) ($d['notes'] ?? '')) ?: null,
        ], fn ($x) => $x !== null);
    }
}
