<?php

namespace App;

enum PlatformRole: string
{
    case Superadmin = 'superadmin';
    case GroupCreator = 'group_creator';

    /** @return list<PlatformCapability> */
    public function capabilities(): array
    {
        return match ($this) {
            self::Superadmin => PlatformCapability::cases(),
            self::GroupCreator => [PlatformCapability::CreateGroups],
        };
    }

    public function grants(PlatformCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function isRequestable(): bool
    {
        return $this !== self::Superadmin;
    }

    /** @return list<self> */
    public static function requestable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->isRequestable()));
    }
}
