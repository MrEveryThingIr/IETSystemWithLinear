<?php

namespace App\Http\Controllers;

use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\BusinessMembership;
use App\Models\Commitment;
use App\Models\GroupMembership;
use App\Models\User;
use App\ProfileIntentStatus;
use App\Services\Surfaces\FeatureSurfaceAccess;
use App\Support\HomeTodayProjection;
use App\Support\QuantityAmount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ExperienceHubController extends Controller
{
    public function needsOffers(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['market']);

        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $mineQuery = ActorProfileIntent::query()
            ->whereHas('profile', fn ($query) => $query->where('actor_id', $actor->id))
            ->where('status', ProfileIntentStatus::Active->value);

        $mineCount = (clone $mineQuery)->count();
        $mine = $mineQuery
            ->with(['concept.labels'])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        $matchItems = $mine->map(fn (ActorProfileIntent $intent): array => [
            'title' => $intent->title ?: $intent->concept->displayLabel(),
            'meta' => __('experience.hubs.needs-offers.match_item_meta'),
            'href' => route('intents.matches', $intent),
        ])->all();

        return $this->renderHub(
            'needs-offers',
            [
                [
                    'title' => __('experience.hubs.needs-offers.mine'),
                    'help' => __('experience.hubs.needs-offers.mine_help'),
                    'count' => $mineCount,
                    'primary' => ['label' => __('experience.hubs.needs-offers.create'), 'href' => route('intents.create')],
                    'secondary' => ['label' => __('experience.hubs.needs-offers.open_all'), 'href' => route('intents.index')],
                    'items' => $mine->map(fn (ActorProfileIntent $intent): array => [
                        'title' => $intent->title ?: $intent->concept->displayLabel(),
                        'meta' => __('experience.hubs.needs-offers.kind_'.$intent->kind->value),
                        'href' => route('intents.matches', $intent),
                    ])->all(),
                    'empty' => __('experience.hubs.needs-offers.mine_empty'),
                ],
                [
                    'title' => __('experience.hubs.needs-offers.discover'),
                    'help' => __('experience.hubs.needs-offers.discover_help'),
                    'primary' => ['label' => __('experience.hubs.needs-offers.discover_action'), 'href' => route('intents.index')],
                    'items' => [],
                ],
                [
                    'title' => __('experience.hubs.needs-offers.matches'),
                    'help' => __('experience.hubs.needs-offers.matches_help'),
                    'count' => count($matchItems),
                    'items' => $matchItems,
                    'empty' => __('experience.hubs.needs-offers.matches_empty'),
                ],
            ],
        );
    }

    public function work(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['deals', 'planner']);

        $projection = $this->today()->build($user);
        $canDeals = $this->access()->allows($user, 'deals');
        $canPlanner = $this->access()->allows($user, 'planner');

        $attention = $projection['waitingOnMe']
            ->filter(fn ($item): bool => $canDeals && in_array($item->kind, [
                'relationship',
                'proposal',
                'contract',
                'fulfillment',
                'settlement',
            ], true))
            ->take(5)
            ->map(fn ($item): array => [
                'title' => $item->title,
                'meta' => $item->summary,
                'href' => $item->url,
            ])
            ->all();

        $activeWork = $canDeals
            ? $projection['activeRelationships']->take(6)->map(fn ($relationship): array => [
                'title' => $relationship->title ?: $relationship->purposeConcept->displayLabel(),
                'meta' => __('experience.hubs.work.deal_meta'),
                'href' => route('relationships.show', $relationship),
            ])->all()
            : [];

        $schedule = $canPlanner
            ? $projection['todayOccurrences']->take(6)->map(fn ($occurrence): array => [
                'title' => $occurrence->plan->title,
                'meta' => __('experience.hubs.work.plan_meta', ['status' => $occurrence->status->value]),
                'href' => route('planner.show', $occurrence->plan).'#occurrence-'.$occurrence->uuid,
            ])->all()
            : [];

        $sections = [[
            'title' => __('experience.hubs.work.attention'),
            'help' => __('experience.hubs.work.attention_help'),
            'count' => count($attention),
            'items' => $attention,
            'empty' => __('experience.hubs.work.attention_empty'),
        ]];

        if ($canDeals) {
            $sections[] = [
                'title' => __('experience.hubs.work.active'),
                'help' => __('experience.hubs.work.active_help'),
                'count' => count($activeWork),
                'primary' => ['label' => __('experience.hubs.work.open_deals'), 'href' => route('deals.index')],
                'items' => $activeWork,
                'empty' => __('experience.hubs.work.active_empty'),
            ];
        }

        if ($canDeals && $user->actor !== null) {
            $actorId = (int) $user->actor->id;
            $commitments = Commitment::query()
                ->where('status', 'active')
                ->where(fn ($query) => $query->where('obligor_actor_id', $actorId)->orWhere('beneficiary_actor_id', $actorId))
                ->with(['planBinding', 'fulfillments:id,commitment_id,quantity,status'])
                ->latest('updated_at')
                ->limit(6)
                ->get();
            $sections[] = [
                'title' => __('work.hub.commitments'),
                'help' => __('work.hub.commitments_help'),
                'count' => $commitments->count(),
                'items' => $commitments->map(function (Commitment $commitment) use ($actorId): array {
                    $accepted = $commitment->fulfillments->where('status', FulfillmentStatus::Accepted)
                        ->reduce(fn (string $sum, $f): string => QuantityAmount::add($sum, (string) $f->quantity), QuantityAmount::normalize('0'));
                    $waiting = $commitment->fulfillments->whereIn('status', [FulfillmentStatus::Submitted, FulfillmentStatus::ClarificationRequested])->count();

                    return [
                        'title' => $commitment->title,
                        'meta' => implode(' · ', array_filter([
                            (int) $commitment->obligor_actor_id === $actorId ? __('work.hub.i_do') : __('work.hub.for_me'),
                            __('work.hub.progress', ['done' => self::quantity($accepted), 'total' => self::quantity((string) $commitment->quantity), 'unit' => (string) $commitment->unit]),
                            $commitment->planBinding ? __('work.hub.planned') : __('work.hub.not_planned'),
                            $waiting > 0 ? trans_choice('work.hub.waiting_review', $waiting, ['count' => $waiting]) : null,
                        ])),
                        'href' => route('commitments.show', $commitment),
                    ];
                })->all(),
                'empty' => __('work.hub.commitments_empty'),
            ];
        }

        if ($canPlanner) {
            $sections[] = [
                'title' => __('experience.hubs.work.schedule'),
                'help' => __('experience.hubs.work.schedule_help'),
                'count' => count($schedule),
                'primary' => ['label' => __('experience.hubs.work.open_planner'), 'href' => route('planner.index')],
                'secondary' => ['label' => __('experience.hubs.work.plan_something'), 'href' => route('planner.create')],
                'items' => $schedule,
                'empty' => __('experience.hubs.work.schedule_empty'),
            ];
        }

        return $this->renderHub('work', $sections);
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

    private function today(): HomeTodayProjection
    {
        return app(HomeTodayProjection::class);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private static function quantity(string $value): string
    {
        $normalized = QuantityAmount::normalize($value);

        return str_contains($normalized, '.') ? rtrim(rtrim($normalized, '0'), '.') : $normalized;
    }
}
