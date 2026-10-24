<?php

namespace App\Support\Categories;

/** What a category can describe. A category may have several kinds. */
enum CategoryKind: string
{
    case Skill = 'skill';
    case Service = 'service';
    case Product = 'product';
    case Property = 'property';
    case Activity = 'activity';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
