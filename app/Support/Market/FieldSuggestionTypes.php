<?php

namespace App\Support\Market;

use App\Models\IntentFieldProposal;

/**
 * Every kind of improvement a person can suggest, grouped in plain words. Ordinary people pick a
 * group ("The name is wrong or unclear"); the specific change type appears only when it is needed.
 * Advisory types are recorded and acknowledged but never change a definition by themselves.
 */
final class FieldSuggestionTypes
{
    /** plain-language group => change types it covers */
    public const GROUPS = [
        'words' => ['label', 'description', 'aliases', 'example'],
        'kind' => ['unit', 'data_type'],
        'choices' => ['options'],
        'place' => ['group'],
        'fit' => ['applicability'],
        'duplicate' => ['duplicate'],
        'display' => ['validation', 'input_control'],
        'collection' => ['template'],
    ];

    /** Groups that need no existing field. */
    public const WITHOUT_FIELD = ['place', 'collection'];

    /** Types that never change a definition automatically. */
    public const ADVISORY = ['example', 'input_control', 'validation'];

    /** Types that replace a field by a successor with a different meaning. */
    public const SUCCESSOR = ['data_type', 'unit'];

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    public static function groupOf(string $type): ?string
    {
        foreach (self::GROUPS as $group => $types) {
            if (in_array($type, $types, true)) {
                return $group;
            }
        }

        return null;
    }

    public static function advisory(string $type): bool
    {
        return in_array($type, self::ADVISORY, true);
    }

    /** A short sentence describing a proposal for lists, in the viewer's language. */
    public static function summary(IntentFieldProposal $proposal): string
    {
        $payload = $proposal->payload;
        $type = (string) ($proposal->change_type ?? 'new');
        $field = $proposal->target_key !== null ? IntentDetailCatalog::label($proposal->target_key) : (string) collect($payload['labels'] ?? [])->first();
        $what = (string) __('composer.suggest.types.'.$type);
        $detail = match ($type) {
            'group' => (string) ($payload['group_label'] ?? ($payload['group_key'] ?? '')),
            'template' => (string) ($payload['name'] ?? ''),
            default => '',
        };

        return trim($what.($field !== '' ? ': '.$field : '').($detail !== '' ? ' ('.$detail.')' : ''));
    }
}
