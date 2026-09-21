<?php

namespace App;

enum ConceptLabelKind: string
{
    case Preferred = 'preferred';
    case Synonym = 'synonym';
    case Abbreviation = 'abbreviation';
}
