<?php

namespace App\Support\Deals;

use App\Models\IntentVersion;
use App\Models\ProposalVersion;
use App\Models\Relationship;
use App\Support\Market\MarketMoney;

/** The few Terms people actually negotiate, as a flat form <-> schema-1 Terms. Pure apart from reading the pinned version. */
final class DealTermsForm
{
    public const KEYS = ['price_min', 'price_max', 'currency', 'basis', 'place', 'starts_on', 'ends_on', 'quantity', 'unit', 'notes'];

    /** @return array<string, string> */
    public static function initial(Relationship $deal, ?ProposalVersion $latest): array
    {
        $form = array_fill_keys(self::KEYS, '');
        $form['currency'] = 'IRT';

        if ($latest?->terms_data !== null) {
            $t = $latest->terms_data;
            $form = array_replace($form, array_filter([
                'price_min' => $t['price']['min'] ?? null, 'price_max' => $t['price']['max'] ?? null,
                'currency' => $t['price']['currency'] ?? null, 'basis' => $t['price']['basis'] ?? null,
                'place' => $t['place'] ?? null, 'starts_on' => $t['time']['starts_on'] ?? null, 'ends_on' => $t['time']['ends_on'] ?? null,
                'quantity' => $t['quantity']['value'] ?? null, 'unit' => $t['quantity']['unit'] ?? null, 'notes' => $t['notes'] ?? null,
            ], fn ($v) => $v !== null));

            return array_map('strval', $form);
        }

        $version = $deal->originating_intent_version_id ? IntentVersion::query()->find($deal->originating_intent_version_id) : null;
        $core = $version?->snapshot['core'] ?? [];
        $min = MarketMoney::legacyToman(isset($core['cash_min']) ? (string) $core['cash_min'] : null, $core['currency_code'] ?? null);
        $max = MarketMoney::legacyToman(isset($core['cash_max']) ? (string) $core['cash_max'] : null, $core['currency_code'] ?? null);

        return array_map('strval', array_replace($form, array_filter([
            'price_min' => $min, 'price_max' => $max, 'basis' => $core['cash_basis'] ?? null, 'place' => $core['location_text'] ?? null,
            'starts_on' => $core['starts_on'] ?? null, 'ends_on' => $core['ends_on'] ?? null,
            'quantity' => $core['quantity'] ?? null, 'unit' => $core['unit'] ?? null,
        ], fn ($v) => $v !== null && $v !== '')));
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function toTerms(array $form): array
    {
        $v = fn (string $key): ?string => ($x = trim((string) ($form[$key] ?? ''))) === '' ? null : $x;
        $hasPrice = $v('price_min') !== null || $v('price_max') !== null;

        return array_filter([
            'schema' => DealTerms::SCHEMA,
            'price' => $hasPrice || $v('basis') ? ['min' => $v('price_min'), 'max' => $v('price_max'), 'currency' => $v('currency'), 'basis' => $v('basis')] : null,
            'place' => $v('place'),
            'time' => $v('starts_on') || $v('ends_on') ? ['starts_on' => $v('starts_on'), 'ends_on' => $v('ends_on')] : null,
            'quantity' => $v('quantity') || $v('unit') ? ['value' => $v('quantity'), 'unit' => $v('unit')] : null,
            'notes' => $v('notes'),
        ], fn ($x) => $x !== null);
    }
}
