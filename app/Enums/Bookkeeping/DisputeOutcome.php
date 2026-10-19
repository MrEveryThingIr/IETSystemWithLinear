<?php

namespace App\Enums\Bookkeeping;

enum DisputeOutcome: string
{
    /** The entry stands as recorded. */
    case Upheld = 'upheld';

    /** The entry was wrong and no longer counts. */
    case Voided = 'voided';
}
