<?php

namespace App\Support\Deals;

/**
 * The Agreement as a readable, numbered document built from the stored Terms of one version. Pure.
 * Typed sections are shown from their canonical values; narrative clauses are shown as written and mean nothing more.
 */
final class AgreementDocument
{
    /**
     * @param  array<string, mixed>  $terms normalized Terms
     * @return list<array{n: int, id: string, title: string, lines: list<array{label: ?string, value: string}>, text: ?string, typed: bool}>
     */
    public static function sections(array $terms): array
    {
        $clauses = collect($terms['clauses'] ?? [])->keyBy(fn ($c) => 'clause:'.$c['key']);
        $order = $terms['order'] ?? [...DealTerms::SECTION_IDS, ...$clauses->keys()->all()];
        $out = [];

        foreach ($order as $id) {
            $section = $clauses->has($id) ? self::clause($clauses[$id]) : self::typed($id, $terms);
            if ($section !== null) {
                $out[] = ['n' => count($out) + 1, 'id' => $id] + $section;
            }
        }

        return $out;
    }

    /** @return array{title: string, lines: list<array{label: ?string, value: string}>, text: ?string, typed: bool} */
    private static function clause(array $c): array
    {
        return ['title' => (string) ($c['title'] ?? __('deals.clauses.'.$c['kind'].'.title')), 'lines' => [], 'text' => $c['text'] ?? null, 'typed' => false];
    }

    /** @return array{title: string, lines: list<array{label: ?string, value: string}>, text: ?string, typed: bool}|null */
    private static function typed(string $id, array $t): ?array
    {
        $lines = [];
        $text = null;
        $row = function (?string $label, ?string $value) use (&$lines): void {
            if ($value !== null && $value !== '') {
                $lines[] = ['label' => $label, 'value' => $value];
            }
        };

        switch ($id) {
            case 'price':
                $p = $t['price'] ?? null;
                if ($p === null) {
                    return null;
                }
                $range = isset($p['min'], $p['max']) && $p['min'] !== $p['max'] ? $p['min'].' – '.$p['max'] : ($p['min'] ?? $p['max'] ?? null);
                $row(null, trim(($range ?? '').' '.($p['currency'] ?? '').(isset($p['basis']) ? ' / '.$p['basis'] : '')));
                break;
            case 'payment':
                $p = $t['payment'] ?? null;
                if ($p === null) {
                    return null;
                }
                $row(null, __('deals.payment_modes.'.$p['mode']).(isset($p['period']) ? ' ('.__('deals.recurrence_units.'.$p['period']).')' : ''));
                $text = $p['note'] ?? null;
                break;
            case 'time':
                $time = $t['time'] ?? null;
                if ($time === null) {
                    return null;
                }
                $row(__('deals.change.time_starts_on'), $time['starts_on'] ?? null);
                $row(__('deals.change.time_ends_on'), $time['ends_on'] ?? null);
                break;
            case 'place':
                if (! isset($t['place'])) {
                    return null;
                }
                $row(null, $t['place']);
                break;
            case 'quantity':
                $q = $t['quantity'] ?? null;
                if ($q === null) {
                    return null;
                }
                $row(null, trim(($q['value'] ?? '').' '.($q['unit'] ?? '')));
                break;
            case 'route':
                $r = $t['route'] ?? null;
                if ($r === null) {
                    return null;
                }
                $row(__('deals.change.route_from'), $r['from'] ?? null);
                $row(__('deals.change.route_to'), $r['to'] ?? null);
                break;
            case 'recurrence':
                $r = $t['recurrence'] ?? null;
                if ($r === null) {
                    return null;
                }
                $row(null, __('deals.recurrence_every', ['every' => $r['every'], 'unit' => __('deals.recurrence_units.'.$r['unit'])]).(isset($r['count']) ? ' × '.$r['count'] : ''));
                break;
            case 'completion':
                if (! isset($t['completion'])) {
                    return null;
                }
                $row(null, __('deals.completion_by.'.$t['completion']['confirmed_by']));
                break;
            case 'fields':
                foreach ($t['fields'] ?? [] as $field) {
                    $row($field['label'], DealTerms::displayValue($field));
                }
                if ($lines === []) {
                    return null;
                }
                break;
            case 'notes':
                if (! isset($t['notes'])) {
                    return null;
                }
                $text = $t['notes'];
                break;
            default:
                return null;
        }

        return ['title' => (string) __('deals.doc.sections.'.$id), 'lines' => $lines, 'text' => $text, 'typed' => true];
    }
}
