<?php

namespace App;

enum ConceptStatus: string
{
    case Active = 'active';
    case Deprecated = 'deprecated';
    case Merged = 'merged';
}
