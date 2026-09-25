<?php

namespace App;

enum FulfillmentStatus: string
{
    case Submitted = 'submitted';
    case ClarificationRequested = 'clarification_requested';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Corrected = 'corrected';
    case Disputed = 'disputed';
}
