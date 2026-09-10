<?php

namespace App\Models;

final class Responsibility
{
    public const Author = 'author';

    public const Editor = 'editor';

    public const Contributor = 'contributor';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::Author, self::Editor, self::Contributor];
    }
}
