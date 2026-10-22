<?php

namespace App\Services\Surfaces;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FeatureSurfaceRegistry
{
    public function definitions(): array
    {
        return [
            'notifications' => $this->surface('Notifications', 'اعلان‌ها', 'bell', 'core', [], ['notifications.index'], ['notifications.*']),
            'profile' => $this->surface(
                'Profile', 'پروفایل', 'user-circle', 'identity', [],
                ['profile.edit'],
                ['profile.edit', 'profile.contacts.*', 'profile.professions.*', 'profiles.show', 'profiles.images.*', 'profiles.shares.*']
            ),
            'market' => $this->surface(
                'Needs, offers & services', 'نیازها، ارائه‌ها و خدمات', 'magnifying-glass', 'market', ['profile'],
                ['intents.index'], ['intents.*', 'market.*', 'needs.*', 'offers.*']
            ),
            // Kept as "money" so existing publication grants carry over to Bookkeeping.
            'money' => $this->surface(
                'Bookkeeping', 'دفتر حساب', 'wallet', 'finance', ['profile'],
                ['bookkeeping.index'], ['bookkeeping.*']
            ),
            'deals' => $this->surface(
                'Deals', 'معامله‌ها', 'arrows-right-left', 'market', ['market', 'money'],
                ['deals.index'], ['deals.*', 'relationships.*', 'proposals.*', 'contracts.*', 'commitments.*']
            ),
            'business' => $this->surface(
                'Businesses', 'کسب‌وکارها', 'building-storefront', 'business', ['profile'],
                ['businesses.index'], ['businesses.*', 'workspace.real-estate.*', 'office.real-estate.*']
            ),
            // Compatibility key for historical grants/routes only. Real Estate
            // is a specialized Business vertical, not a separately published
            // top-level facility.
            'real-estate' => [
                ...$this->surface(
                    'Real Estate compatibility', 'سازگاری املاک', 'home-modern', 'business', ['business'],
                    ['workspace.real-estate.index'], []
                ),
                'sidebar' => false,
                'grantable' => false,
            ],
            'planner' => $this->surface(
                'Planner', 'برنامه‌ریز', 'calendar-days', 'execution', ['profile'],
                ['planner.index'], ['planner.*', 'journeys.*']
            ),
            'vault' => $this->surface(
                'Private vault', 'خزانه خصوصی', 'lock-closed', 'tools', ['profile'],
                ['vault.index'], ['vault.*']
            ),
            'content' => $this->surface(
                'Content Library', 'کتابخانه محتوا', 'rectangle-stack', 'content', ['profile'],
                ['content.library'],
                ['content.library', 'contents.*', 'content.*', 'contexts.*', 'context.*', 'content-evidence.*', 'admissions.*']
            ),
            'groups' => $this->surface(
                'Groups', 'گروه‌ها', 'users', 'collaboration', ['profile'],
                ['groups.index'], ['groups.*', 'group.*']
            ),
            'ai' => $this->surface(
                'AI Chat Lab', 'آزمایشگاه گفت‌وگوی هوش مصنوعی', 'sparkles', 'tools', ['profile'],
                ['ai.chat'], ['ai.*']
            ),
            'manual' => $this->surface('System Manual', 'راهنمای سیستم', 'book-open', 'help', [], ['manual'], ['manual']),
            'system-map' => $this->surface('System Map', 'نقشه سیستم', 'map', 'help', [], ['system-map'], ['system-map']),
            'access-invitations' => $this->surface(
                'Access invitations', 'دعوت و دسترسی', 'user-plus', 'platform', [],
                ['platform.access-invitations'], ['platform.access-invitations']
            ),
            'development-origins' => $this->surface(
                'Development Origins', 'مبدأهای توسعه', 'clock', 'platform', [],
                ['platform.development-origins'], ['platform.development-origins']
            ),
            'category-review' => $this->surface(
                'Category suggestions', 'پیشنهاد دسته‌بندی', 'tag', 'platform', [],
                ['platform.categories'], ['platform.categories']
            ),
            'actors' => $this->surface('Actors', 'کنشگران', 'users', 'platform', [], ['actors.index'], ['actors.*']),

            'publication-control' => [
                ...$this->surface(
                    'Publication control', 'انتشار قابلیت‌ها', 'adjustments-horizontal', 'platform', [],
                    ['platform.publication.index'], ['platform.publication.*']
                ),
                'sidebar' => false,
                'grantable' => false,
            ],
        ];
    }

    private function surface(
        string $label,
        string $labelFa,
        string $icon,
        string $group,
        array $dependencies,
        array $entryRoutes,
        array $routePatterns,
    ): array {
        return [
            'label' => $label,
            'label_fa' => $labelFa,
            'icon' => $icon,
            'group' => $group,
            'sidebar' => true,
            'grantable' => true,
            'dependencies' => $dependencies,
            'entry_routes' => $entryRoutes,
            'route_patterns' => $routePatterns,
        ];
    }

    public function keys(): array
    {
        return array_keys($this->definitions());
    }

    public function grantableKeys(): array
    {
        return array_keys(array_filter(
            $this->definitions(),
            fn (array $definition): bool => $definition['grantable'] ?? true,
        ));
    }

    public function get(string $key): array
    {
        $definition = $this->definitions()[$key] ?? null;

        if (! $definition) {
            throw new InvalidArgumentException("Unknown feature surface [{$key}].");
        }

        return ['key' => $key, ...$definition];
    }

    public function dependencyClosure(array $keys): array
    {
        $definitions = $this->definitions();
        $resolved = [];
        $visiting = [];

        $visit = function (string $key) use (&$visit, &$resolved, &$visiting, $definitions): void {
            if (isset($resolved[$key])) {
                return;
            }

            if (! isset($definitions[$key])) {
                throw new InvalidArgumentException("Unknown feature surface [{$key}].");
            }

            if (isset($visiting[$key])) {
                throw new InvalidArgumentException("Circular feature dependency involving [{$key}].");
            }

            $visiting[$key] = true;

            foreach ($definitions[$key]['dependencies'] as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$key]);
            $resolved[$key] = true;
        };

        foreach (array_values(array_unique($keys)) as $key) {
            $visit($key);
        }

        return array_keys($resolved);
    }

    public function entryRoute(string $key): ?string
    {
        foreach ($this->get($key)['entry_routes'] as $route) {
            if (Route::has($route)) {
                return $route;
            }
        }

        return null;
    }

    public function available(string $key): bool
    {
        return $this->entryRoute($key) !== null;
    }

    public function routeSurface(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        // Explicit/share-scoped resources are governed by their domain
        // authorization, visibility, membership, or share-token rules.
        // Opening one must not reveal or require its whole parent facility.
        if ($this->isDomainAuthorizedRoute($routeName)) {
            return null;
        }

        foreach ($this->definitions() as $key => $definition) {
            foreach ($definition['route_patterns'] as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return $key;
                }
            }
        }

        return null;
    }

    public function isDomainAuthorizedRoute(?string $routeName): bool
    {
        if (! $routeName) {
            return false;
        }

        foreach ($this->domainAuthorizedRoutePatterns() as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Routes that are destinations/resources rather than discoverable
     * facility entry points.
     *
     * Their own domain authorization remains authoritative.
     *
     * @return list<string>
     */
    private function domainAuthorizedRoutePatterns(): array
    {
        return [
            // Public / selectively shared identity presentation.
            'actors.avatar',
            'actors.profile.reference',
            'profiles.show',
            'profiles.images.*',
            // A Need/Offer's photos follow the Need/Offer's own visibility.
            'intents.media.*',
            'profiles.shares.*',

            // Context-specific content resources are shared infrastructure.
            // The discoverable Content Library remains publication-gated,
            // while access to a known context/content stays domain-authorized.
            'content-evidence.*',
            'contexts.contents.*',

            // Legacy group-content URLs are compatibility bridges into the
            // canonical context/content kernel, not separate discoverable
            // facilities.
            'groups.spaces.contents.*',

            // Cross-domain context infrastructure used by deals, groups,
            // admissions and other workflows.
            'contexts.conversation',
            'contexts.timeline',

            // Exact submission resources remain protected by submission
            // authorization; the reviewer/index workbench remains surfaced.
            'contexts.submissions.show',
            'contexts.submissions.assets.*',

            // Admission routes belong to the invitation/admission journey,
            // not to publication of the general Content Library.
            'admissions.*',

            // Access/request workflow is itself reachable; its own platform
            // authorization decides what the user may request or manage.
            'platform.access',

            // Exact deal/workflow resources are governed by participant and
            // record policies. Their directory/create surfaces remain gated.
            'relationships.show',
            'proposals.show',
            'contracts.show',
            'commitments.show',

            // A known plan remains governed by plan/context authorization;
            // opening it does not reveal the general Planner facility.
            'planner.show',
            // Evidence attached to a known plan follows the same rule: the
            // occurrence/asset chain and plan authorization decide access.
            'planner.occurrences.assets.*',
            'planner.occurrences.evidence.*',
            // A meeting is opened by its organizer and invitees; being asked
            // decides access, not the Planner facility.
            'planner.meetings.*',

            // A shared or project book is opened by its parties, who may not
            // have the Bookkeeping facility published; book membership decides.
            'bookkeeping.books.*',

            // Known group/community/workspace resources are governed by
            // membership and group authorization. The Groups directory
            // itself remains a revealed facility.
            'groups.show',
            'groups.community',
            'groups.spaces.show',

        ];
    }

    public function groupedAvailable(bool $grantableOnly = false): array
    {
        $groups = [];

        foreach ($this->definitions() as $key => $definition) {
            if (! $this->available($key)) {
                continue;
            }

            if ($grantableOnly && ! ($definition['grantable'] ?? true)) {
                continue;
            }

            $groups[$definition['group']][] = [
                'key' => $key,
                ...$definition,
                'entry_route' => $this->entryRoute($key),
            ];
        }

        return $groups;
    }
}
