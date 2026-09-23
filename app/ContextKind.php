<?php

namespace App;

enum ContextKind: string
{
    case Personal = 'personal';
    case GroupSpace = 'group_space';
    case Admission = 'admission';
    case Reference = 'reference';
}
