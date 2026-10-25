<?php

namespace App\Support;

use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceAccess;
use Illuminate\Support\Facades\Route;

/**
 * The + launcher: a few plain sentences, each opening the one right form.
 * Only choices the person can use are offered, and the ones that fit the
 * page they are on come first. It is not a second menu.
 */
final class IntentionLauncher
{
    /** Each choice: the surface it needs, and where it opens. */
    private const CHOICES = [
        'need' => ['surface' => 'market', 'icon' => 'magnifying-glass', 'route' => 'intents.compose', 'params' => ['side' => 'need']],
        'offer' => ['surface' => 'market', 'icon' => 'megaphone', 'route' => 'intents.compose', 'params' => ['side' => 'offer']],
        'ability' => ['surface' => 'profile', 'icon' => 'wrench-screwdriver', 'route' => 'items.index', 'params' => ['add' => 'ability']],
        'interest' => ['surface' => 'profile', 'icon' => 'heart', 'route' => 'items.index', 'params' => ['add' => 'interest']],
        'plan' => ['surface' => 'planner', 'icon' => 'calendar-days', 'route' => 'planner.create', 'params' => []],
    ];

    /** Which choices lead on each kind of page. */
    private const CONTEXTS = [
        'planner.*' => ['plan'],
        'intents.*' => ['need', 'offer'],
        'experience.needs-offers' => ['need', 'offer'],
        'items.*' => ['interest', 'ability'],
        'profile.*' => ['interest', 'ability'],
    ];

    public function __construct(private readonly FeatureSurfaceAccess $access) {}

    /** @return list<array{key: string, label: string, hint: string, icon: string, href: string}> */
    public function choicesFor(?User $user, ?string $routeName = null): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $order = array_keys(self::CHOICES);
        foreach (self::CONTEXTS as $pattern => $first) {
            if ($routeName !== null && str($routeName)->is($pattern)) {
                $order = array_values(array_unique([...$first, ...$order]));

                break;
            }
        }

        $choices = [];
        foreach ($order as $key) {
            $choice = self::CHOICES[$key];
            if (! Route::has($choice['route']) || ! $this->access->allows($user, $choice['surface'])) {
                continue;
            }
            $choices[] = [
                'key' => $key,
                'label' => (string) __('launcher.choices.'.$key.'.label'),
                'hint' => (string) __('launcher.choices.'.$key.'.hint'),
                'icon' => $choice['icon'],
                'href' => route($choice['route'], $choice['params']),
            ];
        }

        return $choices;
    }
}
