<?php

namespace App\Enums\Bookkeeping;

enum LedgerStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
