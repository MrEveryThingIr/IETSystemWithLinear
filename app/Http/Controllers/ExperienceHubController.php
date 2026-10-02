<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\BusinessMembership;
use App\Models\GroupMembership;
use App\Models\PersonalContext;
use App\Models\User;
use App\ProfileIntentStatus;
use App\Services\Surfaces\FeatureSurfaceAccess;
use App\Support\HomeTodayProjection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ExperienceHubController extends Controller
{
    public function __construct(
        private readonly FeatureSurfaceAccess $access,
        private readonly HomeTodayProjection $today,
    ) {}

    public function needsOffers(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['market']);

        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $mine = ActorProfileIntent::query()
            ->whereHas('profile', fn ($query) => $query->where('actor_id', $actor->id))
            ->where('status', ProfileIntentStatus::Active->value)
            ->with(['concept.labels'])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        $matchItems = $mine->map(fn (ActorProfileIntent $intent): array => [
            'title' => $intent->title ?: $intent->concept->displayLabel(),
            'meta' => __('experience.hubs.needs_offers.match_item_meta'),
            'href' => route('intents.matches', $intent),
        ])->all();

        return $this->renderHub(
            'needs-offers',
            [
                [
                    'title' => __('experience.hubs.needs_offers.mine'),
                    'help' => __('experience.hubs.needs_offers.mine_help'),
                    'count' => $mine->count(),
                    'primary' => ['label' => __('experience.hubs.needs_offers.create'), 'href' => route('intents.create')],
                    'secondary' => ['label' => __('experience.hubs.needs_offers.open_all'), 'href' => route('intents.index')],
                    'items' => $mine->map(fn (ActorProfileIntent $intent): array => [
                        'title' => $intent->title ?: $intent->concept->displayLabel(),
                        'meta' => __('experience.hubs.needs_offers.kind_'.$intent->kind->value),
                        'href' => route('intents.matches', $intent),
                    ])->all(),
                    'empty' => __('experience.hubs.needs_offers.mine_empty'),
                ],
                [
                    'title' => __('experience.hubs.needs_offers.discover'),
                    'help' => __('experience.hubs.needs_offers.discover_help'),
                    'primary' => ['label' => __('experience.hubs.needs_offers.discover_action'), 'href' => route('intents.index')],
                    'items' => [],
                ],
                [
                    'title' => __('experience.hubs.needs_offers.matches'),
                    'help' => __('experience.hubs.needs_offers.matches_help'),
                    'count' => count($matchItems),
                    'items' => $matchItems,
                    'empty' => __('experience.hubs.needs_offers.matches_empty'),
                ],
            ],
        );
    }

    public function work(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['deals', 'planner']);

        $projection = $this->today->build($user);
        $canDeals = $this->access->allows($user, 'deals');
        $canPlanner = $this->access->allows($user, 'planner');

        $attention = $projection['waitingOnMe']->take(5)->map(fn ($item): array => [
            'title' => $item->title,
            'meta' => $item->summary,
            'href' => $item->url,
        ])->all();

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

        return $this->renderHub('work', [
            [
                'title' => __('experience.hubs.work.attention'),
                'help' => __('experience.hubs.work.attention_help'),
                'count' => count($attention),
                'items' => $attention,
                'empty' => __('experience.hubs.work.attention_empty'),
            ],
            [
                'title' => __('experience.hubs.work.active'),
                'help' => __('experience.hubs.work.active_help'),
                'count' => count($activeWork),
                'primary' => $canDeals ? ['label' => __('experience.hubs.work.open_deals'), 'href' => route('deals.index')] : null,
                'items' => $activeWork,
                'empty' => __('experience.hubs.work.active_empty'),
            ],
            [
                'title' => __('experience.hubs.work.schedule'),
                'help' => __('experience.hubs.work.schedule_help'),
                'count' => count($schedule),
                'primary' => $canPlanner ? ['label' => __('experience.hubs.work.open_planner'), 'href' => route('planner.index')] : null,
                'secondary' => $canPlanner ? ['label' => __('experience.hubs.work.plan_something'), 'href' => route('planner.create')] : null,
                'items' => $schedule,
                'empty' => __('experience.hubs.work.schedule_empty'),
            ],
        ]);
    }

    public function organizations(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['business', 'groups']);

        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $canBusiness = $this->access->allows($user, 'business');
        $canGroups = $this->access->allows($user, 'groups');

        $businesses = $canBusiness
            ? BusinessMembership::query()
                ->with('business')
                ->where('actor_id', $actor->id)
                ->where('status', 'active')
                ->latest('id')
                ->limit(8)
                ->get()
            : collect();

        $groups = $canGroups
            ? GroupMembership::query()
                ->with('group')
                ->where('actor_id', $actor->id)
                ->where('status', 'active')
                ->latest('id')
                ->limit(8)
                ->get()
            : collect();

        return $this->renderHub('organizations', [
            [
                'title' => __('experience.hubs.organizations.businesses'),
                'help' => __('experience.hubs.organizations.businesses_help'),
                'count' => $businesses->count(),
                'primary' => $canBusiness ? ['label' => __('experience.hubs.organizations.open_businesses'), 'href' => route('businesses.index')] : null,
                'secondary' => $canBusiness ? ['label' => __('experience.hubs.organizations.create_business'), 'href' => route('businesses.create')] : null,
                'items' => $businesses->map(fn (BusinessMembership $membership): array => [
                    'title' => $membership->business->name,
                    'meta' => $membership->job_title ?: $membership->role,
                    'href' => route('businesses.show', $membership->business),
                ])->all(),
                'empty' => __('experience.hubs.organizations.businesses_empty'),
            ],
            [
                'title' => __('experience.hubs.organizations.groups'),
                'help' => __('experience.hubs.organizations.groups_help'),
                'count' => $groups->count(),
                'primary' => $canGroups ? ['label' => __('experience.hubs.organizations.open_groups'), 'href' => route('groups.index')] : null,
                'items' => $groups->map(fn (GroupMembership $membership): array => [
                    'title' => $membership->group->name,
                    'meta' => __('experience.hubs.organizations.group_member'),
                    'href' => route('groups.show', $membership->group),
                ])->all(),
                'empty' => __('experience.hubs.organizations.groups_empty'),
            ],
        ]);
    }

    public function money(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['money', 'accounting', 'exchange']);

        $projection = $this->today->build($user);
        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $binding = PersonalContext::query()
            ->with(['context.ledgers.monetaryUnit'])
            ->where('actor_id', $actor->id)
            ->first();

        $ledgers = $binding?->context?->ledgers ?? collect();
        $obligations = collect($projection['obligations']);
        $receivable = (int) $obligations->sum('receivable_outstanding_minor');
        $payable = (int) $obligations->sum('payable_outstanding_minor');

        $summaryItems = [
            [
                'title' => __('experience.hubs.money.receivable'),
                'meta' => __('experience.hubs.money.minor_units', ['amount' => number_format($receivable)]),
                'href' => route('money.accounts'),
            ],
            [
                'title' => __('experience.hubs.money.payable'),
                'meta' => __('experience.hubs.money.minor_units', ['amount' => number_format($payable)]),
                'href' => route('money.accounts'),
            ],
        ];

        return $this->renderHub('money', [
            [
                'title' => __('experience.hubs.money.overview'),
                'help' => __('experience.hubs.money.overview_help'),
                'count' => $ledgers->count(),
                'primary' => ['label' => __('experience.hubs.money.open_accounts'), 'href' => route('money.accounts')],
                'items' => $summaryItems,
            ],
            [
                'title' => __('experience.hubs.money.activity'),
                'help' => __('experience.hubs.money.activity_help'),
                'primary' => ['label' => __('experience.hubs.money.record_activity'), 'href' => route('money.accounts')],
                'items' => $ledgers->take(5)->map(fn ($ledger): array => [
                    'title' => $ledger->name,
                    'meta' => $ledger->monetaryUnit?->code ?? '',
                    'href' => route('money.accounts', ['ledger' => $ledger->uuid]),
                ])->all(),
                'empty' => __('experience.hubs.money.activity_empty'),
            ],
            [
                'title' => __('experience.hubs.money.advanced'),
                'help' => __('experience.hubs.money.advanced_help'),
                'items' => array_values(array_filter([
                    $this->access->allows($user, 'accounting') ? [
                        'title' => __('experience.hubs.money.accounting'),
                        'meta' => __('experience.hubs.money.accounting_help'),
                        'href' => route('accounting.index'),
                    ] : null,
                    $this->access->allows($user, 'exchange') ? [
                        'title' => __('experience.hubs.money.exchange'),
                        'meta' => __('experience.hubs.money.exchange_help'),
                        'href' => route('exchange.index'),
                    ] : null,
                ])),
            ],
        ]);
    }

    public function content(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['content']);

        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $binding = PersonalContext::query()
            ->with(['context.contents.activeRevision'])
            ->where('actor_id', $actor->id)
            ->first();

        $contents = ($binding?->context?->contents ?? collect())
            ->sortByDesc('updated_at')
            ->take(6)
            ->values();

        return $this->renderHub('content', [
            [
                'title' => __('experience.hubs.content.mine'),
                'help' => __('experience.hubs.content.mine_help'),
                'count' => $contents->count(),
                'primary' => ['label' => __('experience.hubs.content.open_mine'), 'href' => route('contexts.personal')],
                'items' => $contents->map(fn ($content): array => [
                    'title' => $content->activeRevision?->title ?: __('ui.content.untitled'),
                    'meta' => __('experience.hubs.content.status_'.$content->status),
                    'href' => route('contexts.contents.show', [$content->context, $content]),
                ])->all(),
                'empty' => __('experience.hubs.content.mine_empty'),
            ],
            [
                'title' => __('experience.hubs.content.explore'),
                'help' => __('experience.hubs.content.explore_help'),
                'primary' => ['label' => __('experience.hubs.content.open_library'), 'href' => route('content.library')],
                'items' => [],
            ],
            [
                'title' => __('experience.hubs.content.create'),
                'help' => __('experience.hubs.content.create_help'),
                'primary' => ['label' => __('experience.hubs.content.create_action'), 'href' => route('contexts.personal')],
                'items' => [],
            ],
        ]);
    }

    public function help(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['manual', 'system-map']);

        return $this->renderHub('help', [
            [
                'title' => __('experience.hubs.help.learn'),
                'help' => __('experience.hubs.help.learn_help'),
                'primary' => $this->access->allows($user, 'manual')
                    ? ['label' => __('experience.hubs.help.open_manual'), 'href' => route('manual')]
                    : null,
                'items' => [],
            ],
            [
                'title' => __('experience.hubs.help.map'),
                'help' => __('experience.hubs.help.map_help'),
                'primary' => $this->access->allows($user, 'system-map')
                    ? ['label' => __('experience.hubs.help.open_map'), 'href' => route('system-map')]
                    : null,
                'items' => [],
            ],
        ]);
    }

    /**
     * @param list<string> $surfaceKeys
     */
    private function requireAny(User $user, array $surfaceKeys): void
    {
        abort_unless(
            collect($surfaceKeys)->contains(fn (string $key): bool => $this->access->allows($user, $key)),
            403,
        );
    }

    /**
     * @param list<array<string, mixed>> $sections
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

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
