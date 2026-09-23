<?php

namespace App;

enum ProfileIntentSubjectKind: string
{
    case Property = 'property';
    case Good = 'good';
    case Service = 'service';
    case Capital = 'capital';
    case Collaboration = 'collaboration';
    case Other = 'other';
}
