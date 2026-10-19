<?php

namespace App\Enums\Bookkeeping;

enum DisputeStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
