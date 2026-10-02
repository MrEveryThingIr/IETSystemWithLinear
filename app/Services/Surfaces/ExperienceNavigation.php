<?php

namespace App\Services\Surfaces;

use App\Models\User;
use App\Support\PlatformAdmin;

class ExperienceNavigation
{
    public function __construct(
        private readonly FeatureSurfaceRegistry $registry,
        private readonly FeatureSurfaceAccess $access,
    ) {}

    /**
     * Experience composition only.
     *
     * Publication grants and record authorization remain authoritative in
     * FeatureSurfaceRegistry / FeatureSurfaceAccess and domain policies.
     */
    public function for(?User $user): array
    {
        if (! $user) {
            return [
                'primary' => [],
                'account' => [],
                'help' => [],
                'labs' => [],
                'admin' => [],
            ];
        }

        $root = PlatformAdmin::check($user);
        $granted = array_flip($this->access->grantedKeys($user));

        $visible = function (string $key) use ($root, $granted): bool {
            $definition = $this->registry->get($key);

            return ($definition['sidebar'] ?? false)
                && $this->registry->available($key)
                && ($root || isset($granted[$key]));
        };

        $item = function (string $key) use ($visible): ?array {
            if (! $visible($key)) {
                return null;
            }

            $surface = $this->registry->get($key);

            return [
                'key' => $key,
                'label' => app()->getLocale() === 'fa'
                    ? ($surface['label_fa'] ?? $surface['label'])
                    : $surface['label'],
                'icon' => $surface['icon'],
                'route' => $this->registry->entryRoute($key),
                'patterns' => $surface['route_patterns'],
            ];
        };

        $destination = function (
            string $key,
            string $labelKey,
            string $icon,
            array $surfaceKeys,
        ) use ($item): ?array {
            $items = array_values(array_filter(array_map($item, $surfaceKeys)));

            if ($items === []) {
                return null;
            }

            return [
                'key' => $key,
                'label' => __($labelKey),
                'icon' => $icon,
                'route' => $items[0]['route'],
                'patterns' => array_values(array_unique(array_merge(
                    ...array_map(fn (array $candidate): array => $candidate['patterns'], $items)
                ))),
                'items' => $items,
            ];
        };

        $primary = array_values(array_filter([
            $destination('needs-offers', 'experience.navigation.needs_offers', 'magnifying-glass', ['market']),
            $destination('work', 'experience.navigation.work', 'briefcase', ['deals', 'planner']),
            $destination('organizations', 'experience.navigation.organizations', 'building-office-2', ['business', 'groups']),
            $destination('money', 'experience.navigation.money', 'wallet', ['money', 'accounting', 'exchange']),
            $destination('content', 'experience.navigation.content', 'rectangle-stack', ['content']),
        ]));

        $account = array_values(array_filter([
            $item('profile'),
            $item('vault'),
        ]));

        $help = array_values(array_filter([
            $item('manual'),
            $item('system-map'),
        ]));

        $labs = array_filter([
            $item('ai'),
        ]);

        $adminKeys = ['access-invitations', 'development-origins', 'actors'];
        $admin = array_values(array_filter(array_map($item, $adminKeys)));

        if ($root && $this->registry->available('publication-control')) {
            $surface = $this->registry->get('publication-control');
            $admin[] = [
                'key' => 'publication-control',
                'label' => app()->getLocale() === 'fa'
                    ? ($surface['label_fa'] ?? $surface['label'])
                    : $surface['label'],
                'icon' => $surface['icon'],
                'route' => $this->registry->entryRoute('publication-control'),
                'patterns' => $surface['route_patterns'],
            ];
        }

        return compact('primary', 'account', 'help', 'labs', 'admin');
    }
}
