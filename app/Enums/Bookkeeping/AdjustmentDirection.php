<?php

namespace App\Enums\Bookkeeping;

enum AdjustmentDirection: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';
}
