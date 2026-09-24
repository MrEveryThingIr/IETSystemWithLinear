<?php

namespace App;

enum RelationshipStatus: string
{
    case Proposed = 'proposed';
    case Active = 'active';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
}
