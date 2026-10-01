<?php

namespace App\Services\Surfaces;

use App\Models\User;
use App\Support\PlatformAdmin;

class UnifiedNavigation
{
    public function __construct(
        private readonly FeatureSurfaceRegistry $registry,
        private readonly FeatureSurfaceAccess $access,
    ) {}

    public function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $root = PlatformAdmin::check($user);
        $granted = array_flip($this->access->grantedKeys($user));
        $items = [];

        foreach ($this->registry->definitions() as $key => $surface) {
            if (! ($surface['sidebar'] ?? false)) {
                continue;
            }

            $route = $this->registry->entryRoute($key);

            if (! $route) {
                continue;
            }

            if (! $root && ! isset($granted[$key])) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => app()->getLocale() === 'fa'
                    ? ($surface['label_fa'] ?? $surface['label'])
                    : $surface['label'],
                'icon' => $surface['icon'],
                'route' => $route,
                'patterns' => $surface['route_patterns'],
            ];
        }

        return $items;
    }
}
