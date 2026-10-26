<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\BusinessMembership;
use App\Models\GroupMembership;
use App\Models\User;
use App\ProfileIntentStatus;
use App\Services\Surfaces\FeatureSurfaceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExperienceHubController extends Controller
{
    /** Retired landing page: Market (discover) lives at the directory, My Items (manage) and Deals (negotiate) have their own homes. */
    public function needsOffers(Request $request): RedirectResponse
    {
        $this->requireAny($this->user($request), ['market']);

        return redirect()->route('intents.index');
    }

    public function organizations(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['business', 'groups']);

        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $canBusiness = $this->access()->allows($user, 'business');
        $canGroups = $this->access()->allows($user, 'groups');

        $businesses = $canBusiness
            ? BusinessMembership::query()
                ->with('business')
                ->where('actor_id', $actor->id)
                ->where('status', 'active')
                ->latest('id')
                ->get()
            : collect();

        $groups = $canGroups
            ? GroupMembership::query()
                ->with('group')
                ->where('actor_id', $actor->id)
                ->where('status', 'active')
                ->latest('id')
                ->get()
            : collect();

        $sections = [];

        if ($canBusiness) {
            $sections[] = [
                'title' => __('experience.hubs.organizations.businesses'),
                'help' => __('experience.hubs.organizations.businesses_help'),
                'count' => $businesses->count(),
                'primary' => ['label' => __('experience.hubs.organizations.open_businesses'), 'href' => route('businesses.index')],
                'secondary' => ['label' => __('experience.hubs.organizations.create_business'), 'href' => route('businesses.create')],
                'items' => $businesses->take(8)->map(fn (BusinessMembership $membership): array => [
                    'title' => $membership->business->name,
                    'meta' => $membership->job_title ?: $membership->role,
                    'href' => route('businesses.show', $membership->business),
                ])->all(),
                'empty' => __('experience.hubs.organizations.businesses_empty'),
            ];
        }

        if ($canGroups) {
            $sections[] = [
                'title' => __('experience.hubs.organizations.groups'),
                'help' => __('experience.hubs.organizations.groups_help'),
                'count' => $groups->count(),
                'primary' => ['label' => __('experience.hubs.organizations.open_groups'), 'href' => route('groups.index')],
                'items' => $groups->take(8)->map(fn (GroupMembership $membership): array => [
                    'title' => $membership->group->name,
                    'meta' => __('experience.hubs.organizations.group_member'),
                    'href' => route('groups.show', $membership->group),
                ])->all(),
                'empty' => __('experience.hubs.organizations.groups_empty'),
            ];
        }

        return $this->renderHub('organizations', $sections);
    }

    public function help(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['manual', 'system-map']);

        $sections = [];

        if ($this->access()->allows($user, 'manual')) {
            $sections[] = [
                'title' => __('experience.hubs.help.learn'),
                'help' => __('experience.hubs.help.learn_help'),
                'primary' => ['label' => __('experience.hubs.help.open_manual'), 'href' => route('manual')],
                'items' => [],
            ];
        }

        if ($this->access()->allows($user, 'system-map')) {
            $sections[] = [
                'title' => __('experience.hubs.help.map'),
                'help' => __('experience.hubs.help.map_help'),
                'primary' => ['label' => __('experience.hubs.help.open_map'), 'href' => route('system-map')],
                'items' => [],
            ];
        }

        return $this->renderHub('help', $sections);
    }

    /**
     * @param  list<string>  $surfaceKeys
     */
    private function requireAny(User $user, array $surfaceKeys): void
    {
        abort_unless(
            collect($surfaceKeys)->contains(fn (string $key): bool => $this->access()->allows($user, $key)),
            403,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function renderHub(string $key, array $sections): View
    {
        return view('experience.hub', [
            'hubKey' => $key,
            'title' => __('experience.hubs.'.$key.'.title'),
            'description' => __('experience.hubs.'.$key.'.description'),
            'sections' => $sections,
        ]);
    }

    private function access(): FeatureSurfaceAccess
    {
        return app(FeatureSurfaceAccess::class);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
