<?php

namespace App\Support\Deals;

use App\Support\Bookkeeping\Toman;

/** Reads Terms as label/value rows and shows what changed between two versions. Pure. */
final class DealTermsDiff
{
    /**
     * The Terms as people read them, in a stable order.
     *
     * @param  array<string, mixed>  $terms
     * @return array<string, array{label: string, value: string}> path => row
     */
    public static function rows(array $terms): array
    {
        $out = [];
        $add = function (string $path, ?string $value, ?string $label = null) use (&$out): void {
            if ($value !== null && $value !== '') {
                $out[$path] = ['label' => $label ?? self::label($path), 'value' => $value];
            }
        };

        $add('title', $terms['title'] ?? null);
        $add('summary', $terms['summary'] ?? null);
        $p = $terms['price'] ?? [];
        $add('price_min', $p['min'] ?? null);
        $add('price_max', $p['max'] ?? null);
        $add('price_currency', $p['currency'] ?? null);
        $add('price_basis', DealTerms::basisLabel($p['basis'] ?? null));
        $add('place', $terms['place'] ?? null);
        $add('time_starts_on', $terms['time']['starts_on'] ?? null);
        $add('time_ends_on', $terms['time']['ends_on'] ?? null);
        $add('time_hours', isset($terms['time']['daily_from']) ? $terms['time']['daily_from'].' – '.$terms['time']['daily_to'].' ('.($terms['time']['timezone'] ?? '').')' : null);
        $add('quantity_value', $terms['quantity']['value'] ?? null);
        $add('quantity_unit', $terms['quantity']['unit'] ?? null);
        $add('route_from', $terms['route']['from'] ?? null);
        $add('route_to', $terms['route']['to'] ?? null);
        foreach ($terms['fields'] ?? [] as $field) {
            $add('fields.'.$field['key'], DealTerms::displayValue($field), $field['label']);
        }
        foreach ($terms['rates'] ?? [] as $rate) {
            $add('rates.'.$rate['key'], Toman::format((int) $rate['rate']).' / '.(DealTerms::basisLabel($rate['unit']) ?? $rate['unit']).(isset($rate['estimate']) ? ' · ≈ '.$rate['estimate'] : ''), $rate['label']);
        }
        if (isset($terms['recurrence'])) {
            $r = $terms['recurrence'];
            $add('recurrence', __('deals.recurrence_every', ['every' => $r['every'], 'unit' => __('deals.recurrence_units.'.$r['unit'])]).(isset($r['count']) ? ' × '.$r['count'] : ''));
        }
        if (isset($terms['payment'])) {
            $pay = $terms['payment'];
            $add('payment', __('deals.payment_modes.'.$pay['mode']).(isset($pay['period']) ? ' ('.__('deals.recurrence_units.'.$pay['period']).')' : '').(isset($pay['note']) ? ' — '.$pay['note'] : ''));
        }
        if (isset($terms['completion'])) {
            $add('completion', __('deals.completion_by.'.$terms['completion']['confirmed_by']));
        }
        foreach ($terms['clauses'] ?? [] as $clause) {
            $add('clause:'.$clause['key'], $clause['text'] ?? null, $clause['title'] ?? __('deals.clauses.'.$clause['kind'].'.title'));
        }
        $add('notes', $terms['notes'] ?? null);

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $from
     * @param  array<string, mixed>  $to
     * @return array{rows: list<array{key: string, label: string, status: string, from: ?string, to: ?string}>, unchanged: int}
     */
    public static function compare(?array $from, array $to): array
    {
        $old = self::rows($from ?? []);
        $new = self::rows($to);
        $rows = [];
        $unchanged = 0;

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $a = $old[$key]['value'] ?? null;
            $b = $new[$key]['value'] ?? null;
            if ($a === $b) {
                $unchanged++;

                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => ($new[$key] ?? $old[$key])['label'],
                'status' => $a === null ? 'added' : ($b === null ? 'removed' : 'changed'),
                'from' => $a,
                'to' => $b,
            ];
        }

        return ['rows' => $rows, 'unchanged' => $unchanged];
    }

    private static function label(string $path): string
    {
        $key = 'deals.change.'.$path;
        $translated = __($key);

        return $translated === $key ? $path : $translated;
    }
}
