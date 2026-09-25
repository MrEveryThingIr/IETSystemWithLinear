<?php

namespace App;

enum FulfillmentReviewDecision: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ClarificationRequested = 'clarification_requested';
}
