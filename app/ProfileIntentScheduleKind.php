<?php

namespace App;

enum ProfileIntentScheduleKind: string
{
    case Once = 'once';
    case Ongoing = 'ongoing';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function isRecurring(): bool
    {
        return in_array($this, [self::Daily, self::Weekly, self::Monthly], true);
    }
}
