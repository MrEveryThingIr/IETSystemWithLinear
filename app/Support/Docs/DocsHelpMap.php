<?php

namespace App\Support\Docs;

/** Which documentation chapter the Help button opens for the page the person is on. */
class DocsHelpMap
{
    /** @var array<string, string> route-name prefix => chapter slug */
    private const MAP = [
        'intents.' => 'needs-offers',
        'experience.needs-offers' => 'needs-offers',
        'deals.' => 'deals',
        'relationships.' => 'deals',
        'proposals.' => 'deals',
        'contracts.' => 'deals',
        'commitments.' => 'deals',
        'experience.work' => 'deals',
        'bookkeeping.' => 'money',
        'planner.' => 'planner',
        'contexts.contents' => 'content',
        'content.' => 'content',
        'experience.content' => 'content',
        'groups.' => 'people',
        'businesses.' => 'people',
        'contacts.' => 'people',
        'messages.' => 'people',
        'experience.organizations' => 'people',
        'dashboard' => 'start',
        'profile.' => 'start',
    ];

    public function slugForRoute(?string $routeName): string
    {
        $routeName = (string) $routeName;

        foreach (self::MAP as $prefix => $slug) {
            if (str_starts_with($routeName, $prefix)) {
                return $slug;
            }
        }

        return 'welcome';
    }
}
