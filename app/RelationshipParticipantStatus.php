<?php

namespace App;

enum RelationshipParticipantStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Declined = 'declined';
    case Left = 'left';
}
