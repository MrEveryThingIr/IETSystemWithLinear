<?php

namespace App;

enum PlanScheduleFrequency: string
{
    case Once = 'once';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case SelectedDates = 'selected_dates';
}
