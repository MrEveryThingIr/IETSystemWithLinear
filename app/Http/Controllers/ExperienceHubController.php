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
use App\Support\MoneyAmount;
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
            'meta' => __('experience.hubs.needs_offers.match_item_meta'),
            'href' => route('intents.matches', $intent),
        ])->all();

        return $this->renderHub(
            'needs-offers',
            [
                [
                    'title' => __('experience.hubs.needs_offers.mine'),
                    'help' => __('experience.hubs.needs_offers.mine_help'),
                    'count' => $mineCount,
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

    public function money(Request $request): View
    {
        $user = $this->user($request);
        $this->requireAny($user, ['money', 'accounting', 'exchange']);

        $projection = $this->today()->build($user);
        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $binding = PersonalContext::query()
            ->with(['context.ledgers.monetaryUnit'])
            ->where('actor_id', $actor->id)
            ->first();

        $ledgers = $binding?->context?->ledgers ?? collect();
        $obligations = collect($projection['obligations']);

        $summaryItems = $obligations->map(fn (array $bucket): array => [
            'title' => $bucket['code'],
            'meta' => __('experience.hubs.money.outstanding_line', [
                'receive' => MoneyAmount::format(
                    (int) $bucket['receivable_outstanding_minor'],
                    (int) $bucket['exponent'],
                ),
                'pay' => MoneyAmount::format(
                    (int) $bucket['payable_outstanding_minor'],
                    (int) $bucket['exponent'],
                ),
                'code' => $bucket['code'],
            ]),
            'href' => route('money.accounts'),
        ])->values()->all();

        return $this->renderHub('money', [
            [
                'title' => __('experience.hubs.money.overview'),
                'help' => __('experience.hubs.money.overview_help'),
                'count' => $obligations->count(),
                'primary' => ['label' => __('experience.hubs.money.open_accounts'), 'href' => route('money.accounts')],
                'items' => $summaryItems,
                'empty' => __('experience.hubs.money.outstanding_empty'),
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
            ...($this->access()->allows($user, 'accounting') || $this->access()->allows($user, 'exchange') ? [[
                'title' => __('experience.hubs.money.advanced'),
                'help' => __('experience.hubs.money.advanced_help'),
                'items' => array_values(array_filter([
                    $this->access()->allows($user, 'accounting') ? [
                        'title' => __('experience.hubs.money.accounting'),
                        'meta' => __('experience.hubs.money.accounting_help'),
                        'href' => route('accounting.index'),
                    ] : null,
                    $this->access()->allows($user, 'exchange') ? [
                        'title' => __('experience.hubs.money.exchange'),
                        'meta' => __('experience.hubs.money.exchange_help'),
                        'href' => route('exchange.index'),
                    ] : null,
                ])),
            ]] : []),
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

        $allContents = $binding?->context?->contents ?? collect();
        $contentCount = $allContents->count();
        $contents = $allContents
            ->sortByDesc('updated_at')
            ->take(6)
            ->values();

        return $this->renderHub('content', [
            [
                'title' => __('experience.hubs.content.mine'),
                'help' => __('experience.hubs.content.mine_help'),
                'count' => $contentCount,
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
     * @param list<string> $surfaceKeys
     */
    private function requireAny(User $user, array $surfaceKeys): void
    {
        abort_unless(
            collect($surfaceKeys)->contains(fn (string $key): bool => $this->access()->allows($user, $key)),
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
}
