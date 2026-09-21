<?php

namespace App;

enum ProfileRequirementKind: string
{
    case Field = 'field';
    case ConceptPredicate = 'concept_predicate';
    case ActiveIntent = 'active_intent';
}
