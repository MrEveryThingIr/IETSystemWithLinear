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
                'spaces' => [],
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
                'label' => __("experience.surfaces.{$key}"),
                'icon' => $surface['icon'],
                'route' => $this->registry->entryRoute($key),
                'patterns' => $surface['route_patterns'],
            ];
        };

        $destination = function (
            string $key,
            string $labelKey,
            string $icon,
            string $route,
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
                'route' => $route,
                'patterns' => array_values(array_unique([
                    $route,
                    ...array_merge(...array_map(fn (array $candidate): array => $candidate['patterns'], $items)),
                ])),
                'items' => $items,
            ];
        };

        // The daily destinations, one kind of task each. Planner stands on its own:
        // a personal plan never sits under deals or work.
        $primary = array_values(array_filter([
            $destination('messages', 'experience.navigation.messages', 'chat-bubble-left-right', 'messages.index', ['messages']),
            $destination('market', 'experience.navigation.market', 'magnifying-glass', 'experience.needs-offers', ['market']),
            $destination('deals', 'experience.navigation.deals', 'arrows-right-left', 'deals.index', ['deals']),
            $destination('planner', 'experience.navigation.planner', 'calendar-days', 'planner.index', ['planner']),
            $destination('money', 'experience.navigation.money', 'wallet', 'bookkeeping.index', ['money']),
        ]));

        // Places people go into, less often.
        $spaces = array_values(array_filter([
            $destination('organizations', 'experience.navigation.organizations', 'building-office-2', 'experience.organizations', ['business', 'groups']),
            $destination('content', 'experience.navigation.content', 'rectangle-stack', 'experience.content', ['content']),
        ]));

        $profile = $item('profile');
        $account = array_values(array_filter([
            $profile === null ? null : [
                'key' => 'items',
                'label' => __('experience.navigation.my_items'),
                'icon' => 'squares-2x2',
                'route' => 'items.index',
                'patterns' => ['items.*', 'profile.professions.*'],
            ],
            $profile === null ? null : [...$profile, 'patterns' => array_values(array_diff($profile['patterns'], ['items.*', 'profile.professions.*']))],
            $item('vault'),
        ]));

        $helpItems = array_values(array_filter([
            $item('manual'),
            $item('system-map'),
        ]));
        $help = $helpItems === [] ? [] : [[
            'key' => 'help',
            'label' => __('experience.hubs.help.title'),
            'icon' => 'question-mark-circle',
            'route' => 'experience.help',
            'patterns' => array_values(array_unique([
                'experience.help',
                ...array_merge(...array_map(fn (array $candidate): array => $candidate['patterns'], $helpItems)),
            ])),
            'items' => $helpItems,
        ]];

        $labs = array_filter([
            $item('ai'),
        ]);

        $adminKeys = ['access-invitations', 'development-origins', 'actors', 'category-review', 'field-review', 'message-reports'];
        $admin = array_values(array_filter(array_map($item, $adminKeys)));

        foreach (['day-texts', 'publication-control'] as $rootKey) {
            if ($root && $this->registry->available($rootKey)) {
                $surface = $this->registry->get($rootKey);
                $admin[] = [
                    'key' => $rootKey,
                    'label' => __('experience.surfaces.'.$rootKey),
                    'icon' => $surface['icon'],
                    'route' => $this->registry->entryRoute($rootKey),
                    'patterns' => $surface['route_patterns'],
                ];
            }
        }

        return compact('primary', 'spaces', 'account', 'help', 'labs', 'admin');
    }
}
