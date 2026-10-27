<?php

namespace App\Support\Deals;

/**
 * Things in a version's structured Terms that two people should clarify together. It only points them out: nothing is
 * corrected, blocked or reinterpreted, an accepted version never changes, and no warning ever turns into an amount.
 * Pure: the same Terms always give the same warnings, in the order a reader meets the terms.
 */
final class DealTermsWarnings
{
    /** Units that describe time on the job: pay "per hour/day/week/month/session" follows time, anything else follows measured work. */
    private const TIME_BASES = ['hour', 'day', 'week', 'month', 'session'];

    /**
     * @param  array<string, mixed>  $terms  normalized Terms
     * @return list<array{code: string, params: array<string, string>}>
     */
    public static function for(array $terms): array
    {
        $out = [];
        $add = function (string $code, array $params = []) use (&$out): void {
            $out[] = ['code' => $code, 'params' => $params];
        };

        $price = $terms['price'] ?? null;
        $basis = (string) ($price['basis'] ?? '');
        $basisName = (string) (DealTerms::basisLabel($basis) ?? '');
        $mode = $terms['payment']['mode'] ?? null;
        $pays = in_array($mode, DealMoney::MODES, true);
        $measured = $basis !== '' && ! in_array($basis, ['total', 'project', 'milestone', ...self::TIME_BASES], true);

        if ($price !== null && isset($price['min'], $price['max']) && $price['min'] !== $price['max'] && $pays) {
            $add('price_is_a_range');
        }

        $unit = strtolower(trim((string) ($terms['quantity']['unit'] ?? '')));
        if ($basis !== '' && $basis !== 'total' && $unit !== '' && $unit !== strtolower($basis) && in_array($basis, DealTerms::PAYMENT_BASES, true)) {
            $add('unit_differs', ['basis' => $basisName, 'unit' => (string) $terms['quantity']['unit']]);
        }

        $time = $terms['time'] ?? [];
        $hasTime = isset($terms['recurrence']) || isset($time['daily_from']) || (isset($time['starts_on']) && isset($time['ends_on']));
        if ($measured && $hasTime) {
            $add('days_do_not_earn', ['basis' => $basisName]);
        }
        if ($measured && $mode === 'per_period') {
            $add('period_follows_measured', ['basis' => $basisName, 'period' => (string) __('deals.recurrence_units.'.($terms['payment']['period'] ?? 'week'))]);
        }
        if ($mode === 'on_acceptance' && $basis !== '' && ! in_array($basis, ['total', 'project'], true)) {
            $add('acceptance_with_unit_price', ['basis' => $basisName]);
        }
        if ($mode === 'after_confirmed_delivery' && ($terms['completion']['confirmed_by'] ?? null) === 'none') {
            $add('payment_waits_for_unrequired_confirmation');
        }

        foreach ($terms['clauses'] ?? [] as $clause) {
            if (in_array($clause['kind'], DealTerms::RESPONSIBLE_KINDS, true) && empty($clause['responsible'])) {
                $add($clause['kind'] === 'materials' ? 'materials_unassigned' : 'duty_unassigned', ['title' => (string) ($clause['title'] ?? __('deals.clauses.'.$clause['kind'].'.title'))]);
            }
        }

        return $out;
    }
}
