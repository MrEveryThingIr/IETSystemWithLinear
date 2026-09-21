<?php

namespace App;

enum ConceptAssertionPredicate: string
{
    case HasSkill = 'has_skill';
    case WantsToLearn = 'wants_to_learn';
    case InterestedIn = 'interested_in';
    case Needs = 'needs';
    case Offers = 'offers';
    case About = 'about';
    case Teaches = 'teaches';
    case FocusesOn = 'focuses_on';
}
