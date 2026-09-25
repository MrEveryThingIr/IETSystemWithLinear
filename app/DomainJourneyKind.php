<?php

namespace App;

enum DomainJourneyKind: string
{
    case Relationship = 'relationship';
    case PersonalActivity = 'personal_activity';
}
