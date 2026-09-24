<?php

namespace App;

enum PlanScheduleRuleStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
