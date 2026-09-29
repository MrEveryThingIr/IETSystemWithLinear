<?php

namespace App;

enum PlanTimingMode: string
{
    case Fixed = 'fixed';
    case FlexibleDay = 'flexible_day';
}
