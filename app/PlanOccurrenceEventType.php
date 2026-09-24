<?php

namespace App;

enum PlanOccurrenceEventType: string
{
    case Started = 'started';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
    case EvidenceAttached = 'evidence_attached';
}
