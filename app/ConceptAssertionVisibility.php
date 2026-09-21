<?php

namespace App;

enum ConceptAssertionVisibility: string
{
    case Inherited = 'inherited';
    case Private = 'private';
    case Public = 'public';
}
