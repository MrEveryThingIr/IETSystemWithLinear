<?php

namespace App\Support\Market;

/**
 * Reads a few unambiguous phrases people type into a numeric field ("at least 8", "1389-1398",
 * "حداقل ۸") and returns the structured meaning for the person to confirm. It never guesses: anything
 * not clearly one of these shapes returns null and the normal validation message is shown.
 */
final class ConstraintParser
{
    private const NUMBER = '(-?\d+(?:\.\d+)?)';

    /**
     * @param  list<string>  $units  names of the field's own unit that may follow the number ("months")
     * @return array{op: string, min?: string, max?: string}|null
     */
    public static function parse(string $text, array $units = []): ?array
    {
        $t = IntentFieldLibrary::normalize($text);
        $t = str_replace([',', '٬', '،'], '', $t);
        $names = array_values(array_filter(array_map(fn (string $unit): string => preg_quote(IntentFieldLibrary::normalize($unit), '/'), $units)));
        if ($names !== []) {
            // Only the field's own unit may trail a number; any other word makes the text unreadable.
            $t = preg_replace('/(?<=\d)\s*(?:'.implode('|', $names).')\.?$/u', '', $t) ?? $t;
            $t = preg_replace('/(?<=\d)\s*(?:'.implode('|', $names).')\.?(?=\s*(?:and|to|-|–|—|تا|و|الى|إلى|到|至)\s)/u', '', $t) ?? $t;
        }
        $n = self::NUMBER;
        $rules = [
            ['between', "/^(?:between\s+)?$n\s*(?:and|to|-|–|—|تا|و|الى|إلى|到|至)\s*$n\$/u"],
            ['between', "/^(?:from|از|من)\s*$n\s*(?:to|تا|الى|إلى|till)\s*$n\$/u"],
            ['between', "/^(?:بین|بين)\s*$n\s*(?:و|تا|and)\s*$n\$/u"],
            ['between', "/^$n\s*(?:到|至)\s*$n\$/u"],
            ['min', "/^(?:at least|minimum(?: of)?|min\.?|no less than|>=|≥|حداقل|دست ?کم|على الأقل|على الاقل|الحد الأدنى|الحد الادنى|至少)\s*$n\$/u"],
            ['min', "/^$n\s*(?:\+|or more|and (?:above|more|up)|plus|به بالا|و بیشتر|فأكثر|فاكثر|以上)\$/u"],
            ['max', "/^(?:at most|up to|maximum(?: of)?|max\.?|no more than|<=|≤|حداکثر|نهایتا|نهایتاً|حتى|على الأكثر|على الاكثر|الحد الأقصى|الحد الاقصى|至多|最多)\s*$n\$/u"],
            ['max', "/^$n\s*(?:or less|and (?:below|less|under)|و کمتر|به پایین|فأقل|فاقل|以下)\$/u"],
        ];
        foreach ($rules as [$op, $pattern]) {
            if (preg_match($pattern, $t, $m) !== 1) {
                continue;
            }
            if ($op === 'between') {
                [$low, $high] = [(float) $m[1], (float) $m[2]];
                if ($low > $high) {
                    return null;
                }

                return ['op' => 'between', 'min' => $m[1], 'max' => $m[2]];
            }

            return $op === 'min' ? ['op' => 'min', 'min' => $m[1]] : ['op' => 'max', 'max' => $m[1]];
        }

        return null;
    }
}
