<?php

namespace App;

enum CommitmentEventType: string
{
    case Created = 'created';
    case PlannerBound = 'planner_bound';
    case FulfillmentSubmitted = 'fulfillment_submitted';
    case FulfillmentReviewed = 'fulfillment_reviewed';
    case FulfillmentCorrected = 'fulfillment_corrected';
    case FulfillmentDisputed = 'fulfillment_disputed';
    case DisputeResolved = 'dispute_resolved';
}
