<?php

namespace App\Support\Deals;

use App\Support\Bookkeeping\Toman;
use App\Support\LocalizedNumber;
use Illuminate\Validation\ValidationException;

/**
 * Checks an Agreement draft the way a person experiences it: one clear message, in the person's language, under the field
 * that needs fixing. Amounts typed in Persian/Arabic digits or with separators are accepted and kept as plain digits.
 * DealTerms::normalize stays the authority on what can be stored; this only keeps its English errors away from people.
 */
final class DealDraftValidator
{
    /**
     * @param  array<string, mixed>  $draft  changed in place: amounts become plain digits
     * @return array<string, string> field path => message
     */
    public static function check(array &$draft): array
    {
        $errors = [];
        $items = $draft['items'] ?? [];
        $core = &$draft['core'];
        $text = fn (string $k): string => trim((string) ($core[$k] ?? ''));

        if (in_array('price', $items, true)) {
            foreach (['price_min', 'price_max'] as $key) {
                if ($text($key) === '') {
                    continue;
                }
                try {
                    $core[$key] = (string) Toman::parse($core[$key], 'amount');
                } catch (ValidationException) {
                    $errors['draft.core.'.$key] = (string) __('deals.validation.amount');
                }
            }
            $range = ($core['price_mode'] ?? 'fixed') === 'range';
            if ($text('price_min') === '' && ($text('price_max') !== '' || $text('basis_choice') !== '')) {
                $errors['draft.core.price_min'] ??= (string) __('deals.validation.price_required');
            }
            if ($range && $text('price_max') === '' && ! isset($errors['draft.core.price_max'])) {
                $errors['draft.core.price_max'] = (string) __('deals.validation.price_required');
            }
            if ($range && ! isset($errors['draft.core.price_min'], $errors['draft.core.price_max']) && $text('price_min') !== '' && $text('price_max') !== '' && (int) $core['price_min'] > (int) $core['price_max']) {
                $errors['draft.core.price_max'] = (string) __('deals.validation.price_order');
            }
            if ($text('price_min') !== '' && $text('basis_choice') === '') {
                $errors['draft.core.basis_choice'] = (string) __('deals.validation.basis_required');
            }
            if ($text('basis_choice') === '__other' && $text('basis_custom') === '') {
                $errors['draft.core.basis_custom'] = (string) __('deals.validation.basis_required');
            }
            if ($text('price_min') !== '' && ! in_array(strtoupper($text('currency')), DealTerms::SUPPORTED_CURRENCIES, true)) {
                $errors['draft.core.currency'] = (string) __('deals.composer.toman_only');
            }
        }

        if (in_array('time', $items, true)) {
            if ($text('starts_on') !== '' && $text('ends_on') !== '' && $text('ends_on') < $text('starts_on')) {
                $errors['draft.core.ends_on'] = (string) __('deals.validation.ends_before');
            }
            if (($text('daily_from') === '') xor ($text('daily_to') === '')) {
                $errors[$text('daily_from') === '' ? 'draft.core.daily_from' : 'draft.core.daily_to'] = (string) __('deals.validation.hours_pair');
            } elseif ($text('daily_from') !== '' && $text('daily_from') === $text('daily_to')) {
                $errors['draft.core.daily_to'] = (string) __('deals.validation.hours_same');
            }
        }

        if (in_array('quantity', $items, true) && $text('quantity') !== '') {
            $quantity = (string) preg_replace('/[\s,٬،]/u', '', LocalizedNumber::digits($text('quantity')));
            if (preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', $quantity) !== 1 || (float) $quantity <= 0) {
                $errors['draft.core.quantity'] = (string) __('deals.validation.quantity');
            } else {
                $core['quantity'] = $quantity;
            }
        }

        if (in_array('recurrence', $items, true) && ($draft['recurrence']['unit'] ?? '') !== '') {
            foreach (['every' => [1, 365], 'count' => [1, 1000]] as $key => [$min, $max]) {
                $value = trim((string) ($draft['recurrence'][$key] ?? ''));
                if ($value === '') {
                    continue;
                }
                $digits = LocalizedNumber::digits($value);
                if (preg_match('/^\d{1,4}$/', $digits) !== 1 || (int) $digits < $min || (int) $digits > $max) {
                    $errors['draft.recurrence.'.$key] = (string) __('deals.validation.'.$key, ['min' => $min, 'max' => $max]);
                } else {
                    $draft['recurrence'][$key] = $digits;
                }
            }
        }

        if (in_array('payment', $items, true)) {
            if (($draft['payment']['mode'] ?? '') === '') {
                $errors['draft.payment.mode'] = (string) __('deals.composer.choose_payment');
            } elseif ($draft['payment']['mode'] === 'per_period' && ! in_array($draft['payment']['period'] ?? '', ['week', 'month'], true)) {
                $errors['draft.payment.period'] = (string) __('deals.validation.period');
            }
        }
        if (in_array('completion', $items, true) && ($draft['completion']['confirmed_by'] ?? '') === '') {
            $errors['draft.completion.confirmed_by'] = (string) __('deals.composer.choose_completion');
        }

        foreach ($draft['clauses'] ?? [] as $i => $clause) {
            if (mb_strlen((string) ($clause['text'] ?? '')) > 4000) {
                $errors['draft.clauses.'.$i.'.text'] = (string) __('deals.validation.clause_long');
            }
            if (mb_strlen((string) ($clause['title'] ?? '')) > 120) {
                $errors['draft.clauses.'.$i.'.title'] = (string) __('deals.validation.clause_title_long');
            }
        }

        return $errors;
    }
}
