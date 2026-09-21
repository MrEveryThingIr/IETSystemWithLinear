<?php

namespace App;

enum ConceptCatalogStatus: string
{
    case Active = 'active';
    case Deprecated = 'deprecated';
}
