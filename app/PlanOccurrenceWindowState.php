<?php

namespace App;

enum PlanOccurrenceWindowState: string
{
    case Upcoming = 'upcoming';
    case Ready = 'ready';
    case Late = 'late';
    case Missed = 'missed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
