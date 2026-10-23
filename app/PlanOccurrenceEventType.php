<?php

namespace App;

enum PlanOccurrenceEventType: string
{
    case Acknowledged = 'acknowledged';
    case AttendanceRecorded = 'attendance_recorded';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case WindowClosed = 'window_closed';
    case OutcomeReported = 'outcome_reported';
    case OutcomeCorrected = 'outcome_corrected';
    case OutcomeCorroborated = 'outcome_corroborated';
    case Started = 'started';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
    case EvidenceAttached = 'evidence_attached';
    case PrerequisiteUpdated = 'prerequisite_updated';
    case ExpenseRecorded = 'expense_recorded';
}
