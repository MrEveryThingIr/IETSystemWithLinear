<?php

namespace App\Enums\Bookkeeping;

/**
 * Lifecycle of every bookkeeping entry, enforced in one place.
 *
 *   pending ─┬─► confirmed ─► disputed ─┬─► confirmed (dispute dropped)
 *            ├─► rejected               └─► void      (entry was wrong)
 *            └─► void (withdrawn by the one who recorded it)
 *
 * Confirmed entries are never edited or deleted; corrections are new entries
 * (adjustment, refund, write-off).
 */
enum TransactionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Disputed = 'disputed';
    case Void = 'void';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Rejected, self::Void],
            self::Confirmed => [self::Disputed],
            self::Disputed => [self::Confirmed, self::Void],
            self::Rejected, self::Void => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }
}
