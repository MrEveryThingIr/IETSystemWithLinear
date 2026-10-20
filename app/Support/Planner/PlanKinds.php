<?php

namespace App\Support\Planner;

/**
 * What a plan is about, chosen in the first step of the plan wizard. A kind
 * gives sensible defaults (duration, repeat, reminder) and a default colour;
 * people can still pick their own label and colour. Stored as metadata.kind.
 */
final class PlanKinds
{
    /**
     * @var array<string, array{icon: string, color: string, duration: int, frequency: string, reminder: int}>
     */
    public const KINDS = [
        'event' => ['icon' => 'calendar-days', 'color' => '#3b82f6', 'duration' => 60, 'frequency' => 'once', 'reminder' => 30],
        'task' => ['icon' => 'check-circle', 'color' => '#f59e0b', 'duration' => 30, 'frequency' => 'once', 'reminder' => 15],
        'routine' => ['icon' => 'arrow-path', 'color' => '#10b981', 'duration' => 30, 'frequency' => 'daily', 'reminder' => 10],
        'meeting' => ['icon' => 'user-group', 'color' => '#8b5cf6', 'duration' => 45, 'frequency' => 'once', 'reminder' => 30],
        'focus' => ['icon' => 'bolt', 'color' => '#ef4444', 'duration' => 90, 'frequency' => 'once', 'reminder' => 5],
        'reminder' => ['icon' => 'bell', 'color' => '#ec4899', 'duration' => 5, 'frequency' => 'once', 'reminder' => 0],
    ];

    /** Colour choices offered next to custom colours. */
    public const PALETTE = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#64748b'];

    public const DEFAULT_COLOR = '#64748b';

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::KINDS);
    }

    public static function valid(?string $kind): bool
    {
        return $kind !== null && isset(self::KINDS[$kind]);
    }

    public static function color(?string $kind): string
    {
        return self::valid($kind) ? self::KINDS[$kind]['color'] : self::DEFAULT_COLOR;
    }

    public static function icon(?string $kind): string
    {
        return self::valid($kind) ? self::KINDS[$kind]['icon'] : 'calendar';
    }

    public static function normalizeColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
    }
}
