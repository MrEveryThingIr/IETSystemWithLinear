<?php

namespace App\Enums\Bookkeeping;

enum LedgerKind: string
{
    /** One person's own money: wallets, income, expenses. */
    case Personal = 'personal';

    /** Between two or more people (for example an employer and a worker). */
    case Shared = 'shared';

    /** A project or job with several people (for example a building site). */
    case Project = 'project';
}
