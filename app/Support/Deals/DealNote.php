<?php

namespace App\Support\Deals;

use App\Actions\Conversations\PostContextMessage;
use App\Models\Context;
use App\Models\User;

/** A line in the Deal conversation that records a structured step. Persisting it never decides the step and never blocks it. */
final class DealNote
{
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
                (string) __('deals.timeline.'.$type, ['version' => $meta['version'] ?? '']),
                kind: 'deal',
                meta: ['type' => $type] + $meta,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
