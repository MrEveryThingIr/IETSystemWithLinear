<?php

namespace App;

enum ProfileVisibility: string
{
    case Private = 'private';
    case Authenticated = 'authenticated';
    case Public = 'public';
}
