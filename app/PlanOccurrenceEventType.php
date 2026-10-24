<?php

namespace App;

enum PlanOccurrenceEventType: string
{
    case Started = 'started';
    case Reported = 'reported';
    case Corrected = 'corrected';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
    case EvidenceAttached = 'evidence_attached';
    case PrerequisiteUpdated = 'prerequisite_updated';
    case ExpenseRecorded = 'expense_recorded';
}
