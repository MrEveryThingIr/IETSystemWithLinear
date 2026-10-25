<?php

namespace App\Support\Market;

/**
 * Canonical terms of a post, kept apart from descriptive fields. Each block owns exactly one meaning
 * and the core columns that carry it, so nothing is asked twice:
 *   price    what is asked or offered in money (or free / exchange), with basis and negotiability
 *   place    where it happens or is located (one place, never a second "address")
 *   route    from where to where (journeys only)
 *   when     the schedule: dates, repeat pattern, time window
 *   quantity how many, with a unit
 *   category the shared topic used for search and matching
 *   media    photos, video and documents
 *   importance how strongly this matters when matching
 * Blocks are chosen by the person; profiles only recommend them.
 */
final class IntentTerms
{
    public const BLOCKS = ['price', 'place', 'when', 'route', 'quantity', 'category', 'media', 'importance'];

    /** Core columns cleared when a block is removed. */
    private const OWNS = [
        'place' => ['location_text'],
        'when' => ['starts_on', 'ends_on', 'time_window_start', 'time_window_end', 'recurrence_weekdays', 'recurrence_day_of_month'],
        'route' => ['origin_text', 'destination_text', 'round_trip', 'return_after_days'],
        'quantity' => ['quantity', 'unit'],
        'importance' => ['importance_percent'],
        'price' => ['exchange_notes', 'negotiable'],
    ];

    /** @return list<string> */
    public static function owned(string $block): array
    {
        return self::OWNS[$block] ?? [];
    }

    /**
     * Which blocks already carry information, so an existing post opens with them selected.
     *
     * @param  array<string, mixed>  $core
     * @return list<string>
     */
    public static function populated(array $core, bool $hasPrice, bool $hasCategory, bool $hasMedia): array
    {
        $filled = fn (string ...$fields): bool => collect($fields)->contains(fn (string $field): bool => ! in_array($core[$field] ?? null, [null, '', [], false], true));
        $blocks = [];
        $hasPrice || $filled('exchange_notes', 'negotiable') ? $blocks[] = 'price' : null;
        $filled('location_text') ? $blocks[] = 'place' : null;
        $filled('starts_on', 'ends_on', 'time_window_start', 'time_window_end') || ! in_array($core['schedule_kind'] ?? 'ongoing', ['ongoing', 'once', ''], true) ? $blocks[] = 'when' : null;
        $filled('origin_text', 'destination_text') ? $blocks[] = 'route' : null;
        $filled('quantity') ? $blocks[] = 'quantity' : null;
        $hasCategory ? $blocks[] = 'category' : null;
        $hasMedia ? $blocks[] = 'media' : null;
        $filled('importance_percent') ? $blocks[] = 'importance' : null;

        return $blocks;
    }
}
