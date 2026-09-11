<?php

namespace App;

enum GroupRoleKey: string
{
    case Member = 'member';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Owner => 'Owner',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }
}
