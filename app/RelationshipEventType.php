<?php

namespace App;

enum RelationshipEventType: string
{
    case Proposed = 'proposed';
    case ParticipantAccepted = 'participant_accepted';
    case ParticipantDeclined = 'participant_declined';
    case Activated = 'activated';
    case Cancelled = 'cancelled';
    case Ended = 'ended';
}
