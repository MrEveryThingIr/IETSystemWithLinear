<?php

namespace App\Support\Market;

/** Canonical keys and filter columns are code-owned, never derived from submitted labels. */
final class IntentDetailCatalog
{
    /** @return array<string, array{type: string, filter: string, options?: list<string>, unit?: string}> */
    public static function all(): array
    {
        return [
            'service_area' => ['type' => 'text', 'filter' => 'value_text'],
            'experience_years' => ['type' => 'number', 'filter' => 'value_number'],
            'condition' => ['type' => 'choice', 'filter' => 'value_text', 'options' => ['new', 'used', 'refurbished']],
            'brand' => ['type' => 'text', 'filter' => 'value_text'],
            'floor_area' => ['type' => 'number', 'filter' => 'value_number', 'unit' => 'm²'],
            'rooms' => ['type' => 'number', 'filter' => 'value_number'],
            'floor' => ['type' => 'number', 'filter' => 'value_number'],
            'deposit' => ['type' => 'amount', 'filter' => 'value_number', 'unit' => 'IRT'],
            'monthly_rent' => ['type' => 'amount', 'filter' => 'value_number', 'unit' => 'IRT'],
            'session_minutes' => ['type' => 'number', 'filter' => 'value_number', 'unit' => 'minute'],
            'delivery_mode' => ['type' => 'choice', 'filter' => 'value_text', 'options' => ['online', 'in_person', 'either']],
            'availability' => ['type' => 'text', 'filter' => 'value_text'],
        ];
    }

    /** @return list<string> */
    public static function suggestions(string $subject): array
    {
        return match ($subject) {
            'property' => ['floor_area', 'rooms', 'floor', 'deposit', 'monthly_rent'],
            'service' => ['service_area', 'experience_years', 'availability', 'delivery_mode', 'session_minutes'],
            default => ['condition', 'brand', 'delivery_mode'],
        };
    }
}
