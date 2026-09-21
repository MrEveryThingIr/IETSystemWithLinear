<?php

namespace App;

enum ProfileItemVisibility: string
{
    case Inherited = 'inherited';
    case Private = 'private';
    case Authenticated = 'authenticated';
    case Public = 'public';
}
