<?php

namespace App\Support\Market;

use App\Support\Bookkeeping\Toman;

/** The Market boundary; Bookkeeping and Catalog retain their monetary authorities. */
final class MarketMoney
{
    public static function parse(string|int|null $amount, string $field = 'amount'): int
    {
        return Toman::parse($amount, $field);
    }

    public static function currency(): string
    {
        return (string) config('market.currency', 'IRT');
    }

    public static function legacyToman(?string $amount, ?string $currency): ?string
    {
        if ($amount === null || ! in_array($currency, ['IRT', 'IRR'], true)) {
            return null;
        }
        // Exact decimal manipulation; no floating point conversion or stored-data rewrite.
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $digits = ltrim($whole, '0') ?: '0';
        if ($currency === 'IRR') {
            $fraction = substr($digits, -1).$fraction;
            $digits = strlen($digits) === 1 ? '0' : substr($digits, 0, -1);
        }
        $fraction = rtrim($fraction, '0');

        return $digits.($fraction === '' ? '' : '.'.$fraction);
    }

    public static function format(int|string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $formatted = Toman::format((int) $whole).($fraction === '' ? '' : '.'.$fraction);
        $locale = app()->getLocale();
        if (in_array($locale, ['fa', 'ar'], true)) {
            $formatted = strtr($formatted, array_combine(range(0, 9), preg_split('//u', $locale === 'fa' ? '۰۱۲۳۴۵۶۷۸۹' : '٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY)));
        }

        return $formatted.' '.__('composer.toman');
    }
}
