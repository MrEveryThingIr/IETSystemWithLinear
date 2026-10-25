<?php

namespace App\Support\Market;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approved applicability rules layered over the code profiles: where a field is hidden, belongs to
 * Needs or Offers only, or is shown only when another answer says so. Rules narrow what is
 * recommended and published; they never rewrite what a published post said.
 */
final class IntentFieldRules
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        if (! Schema::hasTable('intent_field_rules')) {
            return [];
        }

        return Cache::remember('intent-fields:rules:'.IntentDetailCatalog::version(), 3600, fn (): array => DB::table('intent_field_rules')
            ->whereNull('retired_at')->orderBy('id')->get()
            ->map(fn ($row): array => ['field_key' => $row->field_key, 'topic' => $row->topic, 'intention' => $row->intention, 'arrangement' => $row->arrangement,
                'effect' => $row->effect, 'condition' => json_decode((string) $row->condition, true) ?: []])->all());
    }

    /** @param array<string, mixed> $answers */
    public static function hides(string $key, string $topic, bool $need, string $arrangement, array $answers): bool
    {
        foreach (self::all() as $rule) {
            if ($rule['field_key'] !== $key
                || ($rule['topic'] !== null && $rule['topic'] !== $topic)
                || ($rule['intention'] !== null && $rule['intention'] !== ($need ? 'need' : 'offer'))
                || ($rule['arrangement'] !== null && $rule['arrangement'] !== $arrangement)) {
                continue;
            }
            $hidden = match ($rule['effect']) {
                'hide' => true,
                'only_need' => ! $need,
                'only_offer' => $need,
                'show_when' => ! IntentProfiles::holds($rule['condition'], $answers),
                default => false,
            };
            if ($hidden) {
                return true;
            }
        }

        return false;
    }
}
