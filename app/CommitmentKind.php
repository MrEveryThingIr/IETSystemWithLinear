<?php

namespace App;

enum CommitmentKind: string
{
    case Work = 'work';
    case Deliverable = 'deliverable';
    case Attendance = 'attendance';
    case Service = 'service';
    case Payment = 'payment';
    case Resource = 'resource';
    case Other = 'other';
}
