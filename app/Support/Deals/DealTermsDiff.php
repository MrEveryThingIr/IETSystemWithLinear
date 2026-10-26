<?php

namespace App\Support\Deals;

/** What changed between two normalized Terms versions. Pure. */
final class DealTermsDiff
{
    /**
     * @param  array<string, mixed>|null  $from
     * @param  array<string, mixed>  $to
     * @return array{rows: list<array{key: string, label: string, status: string, from: ?string, to: ?string}>, unchanged: int}
     */
    public static function compare(?array $from, array $to): array
    {
        $old = self::flatten($from ?? []);
        $new = self::flatten($to);
        $rows = [];
        $unchanged = 0;

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $a = $old[$key] ?? null;
            $b = $new[$key] ?? null;
            if ($a === $b) {
                $unchanged++;

                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => self::label($key),
                'status' => $a === null ? 'added' : ($b === null ? 'removed' : 'changed'),
                'from' => $a,
                'to' => $b,
            ];
        }

        return ['rows' => $rows, 'unchanged' => $unchanged];
    }

    /** @return array<string, string> dotted path => text, in the order people read terms */
    private static function flatten(array $terms): array
    {
        $out = [];
        foreach (['price', 'place', 'time', 'quantity', 'route', 'category', 'fields', 'effective', 'notes'] as $section) {
            $value = $terms[$section] ?? null;
            if ($value === null) {
                continue;
            }
            if ($section === 'fields') {
                foreach ($value as $field) {
                    $out['fields.'.$field['key']] = (string) $field['value'];
                }

                continue;
            }
            if (! is_array($value)) {
                $out[$section] = (string) $value;

                continue;
            }
            foreach ($value as $k => $v) {
                if ($v !== null && $v !== '') {
                    $out[$section.'.'.$k] = (string) $v;
                }
            }
        }

        return $out;
    }

    private static function label(string $path): string
    {
        if (str_starts_with($path, 'fields.')) {
            return substr($path, 7);
        }
        $key = 'deals.change.'.str_replace('.', '_', $path);
        $translated = __($key);

        return $translated === $key ? $path : $translated;
    }
}
