<?php

namespace App\Enums\Bookkeeping;

enum PartyRole: string
{
    /** Created the book; manages people and can record anything. */
    case Owner = 'owner';

    /** Manages the book with the owner (a business partner, a site lead). */
    case Partner = 'partner';

    /** Records and confirms entries they take part in. */
    case Member = 'member';

    /** Can only read. */
    case Viewer = 'viewer';

    public function canManage(): bool
    {
        return in_array($this, [self::Owner, self::Partner], true);
    }

    public function canRecord(): bool
    {
        return $this !== self::Viewer;
    }
}
