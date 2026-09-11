<?php

namespace App;

enum PlatformRole: string
{
    case Superadmin = 'superadmin';

    /** @return list<PlatformCapability> */
    public function capabilities(): array
    {
        return match ($this) {
            self::Superadmin => PlatformCapability::cases(),
        };
    }

    public function grants(PlatformCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
