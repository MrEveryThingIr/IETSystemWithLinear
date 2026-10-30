<?php

namespace App\Support\Deals;

use App\Actions\Conversations\PostContextMessage;
use App\Models\Context;
use App\Models\Relationship;
use App\Models\User;
use App\Support\Bookkeeping\Toman;
use App\Support\TemporalCalendar;
use Carbon\CarbonImmutable;

/** A line in the Deal conversation that records a structured step. Persisting it never decides the step and never blocks it. */
final class DealNote
{
    /** The part of the Deal a step belongs to, so its line in the one conversation can be told apart and filtered. */
    public static function area(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'work_') || str_starts_with($type, 'inspection_') => 'work',
            str_starts_with($type, 'money_') => 'money',
            default => 'agreement',
        };
    }

    /** A step in the Deal's one conversation, labelled with its area and linking to that tab. @param array<string, mixed> $meta */
    public static function forDeal(Relationship $deal, User $user, string $type, array $meta = []): void
    {
        self::post($deal->contextBinding?->context, $user, $type, $meta + ['url' => DealLinks::workspace($deal, self::area($type))]);
    }

    /** @param array<string, mixed> $meta */
    public static function post(?Context $context, User $user, string $type, array $meta = []): void
    {
        if (! $context instanceof Context) {
            return;
        }

        try {
            app(PostContextMessage::class)->execute(
                $context,
                $user,
                (string) __('deals.timeline.'.$type, self::params($meta)),
                kind: 'deal',
                meta: ['type' => $type, 'area' => self::area($type)] + $meta,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Placeholders for a step's sentence, filled from what the step recorded (never from live data, so history reads the same later). @param array<string, mixed> $meta @return array<string, string> */
    public static function params(array $meta): array
    {
        return [
            'version' => (string) ($meta['version'] ?? ''),
            'title' => (string) ($meta['title'] ?? ''),
            'qty' => (string) ($meta['qty'] ?? ''),
            'unit' => (string) ($meta['unit'] ?? ''),
            'amount' => isset($meta['amount']) ? Toman::format((int) $meta['amount']).' '.__('composer.toman') : '',
            'date' => isset($meta['date']) ? TemporalCalendar::dateLabel(CarbonImmutable::parse($meta['date']), auth()->user()) : '',
        ];
    }
}
