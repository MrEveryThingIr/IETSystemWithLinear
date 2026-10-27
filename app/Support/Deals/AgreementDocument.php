<?php

namespace App\Support\Deals;

/**
 * The Agreement as a readable, numbered document built from the stored Terms of one version. Pure.
 * Typed sections are shown from their canonical values; narrative clauses are shown as written and mean nothing more.
 * Several clauses of one kind (for example three "Duties of each side") share one numbered heading with 3.1, 3.2 ... beneath.
 */
final class AgreementDocument
{
    /**
     * @param  array<string, mixed>  $terms  normalized Terms
     * @param  array<string, string>  $labels  principal key => name, to show who a duty concerns
     * @return list<array{n: int, id: string, title: string, lines: list<array{label: ?string, value: string, date?: bool}>, text: ?string, typed: bool, clauses: list<array{n: string, title: ?string, text: ?string, who?: ?string}>, who?: ?string}>
     */
    public static function sections(array $terms, array $labels = []): array
    {
        $clauses = collect($terms['clauses'] ?? [])->keyBy('key');
        $kinds = $clauses->map(fn ($c) => $c['kind'])->all();
        $order = self::arrange($terms['order'] ?? [...DealTerms::SECTION_IDS, ...$clauses->keys()->map(fn ($k) => 'clause:'.$k)->all()], $kinds);
        $out = [];
        $done = [];

        foreach ($order as $id) {
            if (str_starts_with($id, 'clause:')) {
                $kind = $kinds[substr($id, 7)] ?? null;
                if ($kind === null || isset($done[$kind])) {
                    continue;
                }
                $done[$kind] = true;
                $group = $clauses->filter(fn ($c) => $c['kind'] === $kind)->values();
                $n = count($out) + 1;
                $out[] = $group->count() === 1
                    ? ['n' => $n, 'id' => 'clause:'.$group[0]['key'], 'title' => (string) ($group[0]['title'] ?? __('deals.clauses.'.$kind.'.title')), 'lines' => [], 'text' => $group[0]['text'] ?? null, 'typed' => false, 'clauses' => [], 'who' => $labels[$group[0]['responsible'] ?? ''] ?? null]
                    : ['n' => $n, 'id' => 'group:'.$kind, 'title' => (string) __('deals.clauses.'.$kind.'.title'), 'lines' => [], 'text' => null, 'typed' => false,
                        'clauses' => $group->map(fn ($c, $i) => ['n' => $n.'.'.($i + 1), 'title' => $c['title'] ?? null, 'text' => $c['text'] ?? null, 'who' => $labels[$c['responsible'] ?? ''] ?? null])->all()];

                continue;
            }
            $section = self::typed($id, $terms);
            if ($section !== null) {
                $out[] = ['n' => count($out) + 1, 'id' => $id] + $section + ['clauses' => []];
            }
        }

        return $out;
    }

    /**
     * Keeps clauses of one kind together, at the position of the first of them. Used by the document and by the composer,
     * so the numbers a drafter sees are the numbers the document prints.
     *
     * @param  list<string>  $items  "price", "clause:<key>" ...
     * @param  array<string, string>  $kinds  clause key => kind
     * @return list<string>
     */
    public static function arrange(array $items, array $kinds): array
    {
        $out = [];
        $placed = [];
        foreach ($items as $id) {
            if (! str_starts_with($id, 'clause:')) {
                $out[] = $id;

                continue;
            }
            $kind = $kinds[substr($id, 7)] ?? null;
            if ($kind === null || isset($placed[$kind])) {
                continue;
            }
            $placed[$kind] = true;
            foreach ($items as $other) {
                if (str_starts_with($other, 'clause:') && ($kinds[substr($other, 7)] ?? null) === $kind) {
                    $out[] = $other;
                }
            }
        }

        return $out;
    }

    /**
     * The number each item carries in the document: "3" or "3.2" for a clause that shares its heading.
     *
     * @param  list<string>  $items  already arranged
     * @param  array<string, string>  $kinds
     * @return array<string, array{no: string, heading: bool}> id => number and whether this item opens its group's heading
     */
    public static function numbering(array $items, array $kinds): array
    {
        $count = array_fill_keys(array_unique(array_filter(array_map(fn ($id) => str_starts_with($id, 'clause:') ? ($kinds[substr($id, 7)] ?? null) : null, $items))), 0);
        $total = [];
        foreach ($items as $id) {
            if (str_starts_with($id, 'clause:')) {
                $kind = $kinds[substr($id, 7)] ?? null;
                $total[$kind] = ($total[$kind] ?? 0) + 1;
            }
        }
        $out = [];
        $n = 0;
        $seen = [];
        foreach ($items as $id) {
            if (str_starts_with($id, 'clause:') && ($kind = $kinds[substr($id, 7)] ?? null) !== null) {
                if (! isset($seen[$kind])) {
                    $seen[$kind] = ++$n;
                }
                $count[$kind]++;
                $out[$id] = ['no' => $total[$kind] > 1 ? $seen[$kind].'.'.$count[$kind] : (string) $seen[$kind], 'heading' => $count[$kind] === 1];

                continue;
            }
            $out[$id] = ['no' => (string) ++$n, 'heading' => true];
        }

        return $out;
    }

    /** @return array{title: string, lines: list<array{label: ?string, value: string}>, text: ?string, typed: bool}|null */
    private static function typed(string $id, array $t): ?array
    {
        $lines = [];
        $text = null;
        $row = function (?string $label, ?string $value, bool $date = false) use (&$lines): void {
            if ($value !== null && $value !== '') {
                $lines[] = ['label' => $label, 'value' => $value, 'date' => $date];
            }
        };

        switch ($id) {
            case 'price':
                $p = $t['price'] ?? null;
                if ($p === null) {
                    return null;
                }
                $row(null, DealTerms::priceLabel($p));
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
                $row(__('deals.change.time_starts_on'), $time['starts_on'] ?? null, true);
                $row(__('deals.change.time_ends_on'), $time['ends_on'] ?? null, true);
                $row(__('deals.change.time_hours'), isset($time['daily_from']) ? $time['daily_from'].' – '.$time['daily_to'].' ('.($time['timezone'] ?? '').')' : null);
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
