<?php

namespace App;

enum ContextKind: string
{
    case Personal = 'personal';
    case Business = 'business';
    case GroupSpace = 'group_space';
    case Admission = 'admission';
    case Relationship = 'relationship';
    case Negotiation = 'negotiation';
    case Contract = 'contract';
    case Reference = 'reference';
    case System = 'system';
}
