<section class="mx-auto max-w-7xl space-y-6">
    @php
        $homeUser = request()->user();
        $homeName = $homeUser?->actor?->profile?->display_name ?: $homeUser?->username;
        $hasActor = $homeUser?->actor !== null;
        $officeAlpha = config('release.profile') === 'office_alpha';
    @endphp

    <x-app.page-header
        :title="__('home.title')"
        :description="__('home.welcome', ['name' => $homeName, 'date' => $todayDisplay, 'timezone' => $timezone])"
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                @if ($hasActor && ! $officeAlpha)
                    <flux:button :href="route('planner.create')" variant="primary" icon="plus">
                        {{ __('home.new_activity') }}
                    </flux:button>
                @endif
                @if ($hasActor)
                    <flux:button :href="route('intents.create')" :variant="$officeAlpha ? 'primary' : 'ghost'">
                        {{ __('home.new_intent') }}
                    </flux:button>
                @endif
                <flux:button :href="route('profile.edit')" variant="ghost">
                    {{ __('ui.navigation.profile') }}
                </flux:button>
            </div>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout variant="success">{{ __('ui.dashboard.verified') }}</flux:callout>

    @if (! $hasActor)
        <flux:callout>{{ __('home.actor_setup_pending') }}</flux:callout>
    @endif

    @unless ($officeAlpha)
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('home.quick_links') }}</flux:heading>
                    <flux:text>{{ __('home.quick_links_help') }}</flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button :href="route('contexts.personal')" size="sm" variant="ghost">{{ __('ui.context_content.my_content') }}</flux:button>
                    <flux:button :href="route('manual')" size="sm" variant="ghost">{{ __('ui.navigation.manual') }}</flux:button>
                    <flux:button :href="route('groups.index')" size="sm" variant="ghost">{{ __('ui.navigation.groups') }}</flux:button>
                    @can('viewAny', App\Models\Actor::class)
                        <flux:button :href="route('actors.index')" size="sm" variant="ghost">{{ __('ui.navigation.actors') }}</flux:button>
                    @endcan
                </div>
            </div>
        </flux:card>
    @endunless

    @unless ($officeAlpha)
    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('home.today_actions') }}</flux:heading>
                <flux:text>{{ __('home.today_actions_help') }}</flux:text>
            </div>

            <div class="space-y-3">
                @forelse ($todayOccurrences as $occurrence)
                    <a
                        href="{{ route('planner.show', $occurrence->plan).'#occurrence-'.$occurrence->uuid }}"
                        class="block rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold" dir="auto">{{ $occurrence->plan->title }}</div>
                                @if ($occurrence->plan->description)
                                    <div class="mt-1 line-clamp-2 text-sm text-zinc-500" dir="auto">{{ $occurrence->plan->description }}</div>
                                @endif
                            </div>
                            <flux:badge>{{ __('planner.occurrence_status.'.$occurrence->status->value) }}</flux:badge>
                        </div>
                        <div class="mt-2 text-sm text-zinc-500">
                            <x-app.local-time :value="$occurrence->scheduled_start_at" />
                            →
                            <x-app.local-time :value="$occurrence->scheduled_end_at" />
                        </div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('home.no_today_actions')" :description="__('home.no_today_actions_help')" />
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('home.waiting_on_me') }}</flux:heading>
                <flux:text>{{ __('home.waiting_on_me_help') }}</flux:text>
            </div>

            <div class="space-y-3">
                @forelse ($waitingOnMe as $item)
                    <a
                        href="{{ $item->url }}"
                        class="block rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge>{{ __('home.action_kind.'.$item->kind) }}</flux:badge>
                            <span class="font-semibold" dir="auto">{{ $item->title }}</span>
                        </div>
                        @if ($item->summary)
                            <div class="mt-2 text-sm text-zinc-500">{{ $item->summary }}</div>
                        @endif
                    </a>
                @empty
                    <x-app.empty-state :title="__('home.nothing_waiting_on_me')" />
                @endforelse
            </div>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('home.waiting_on_others') }}</flux:heading>
            <flux:text>{{ __('home.waiting_on_others_help') }}</flux:text>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            @forelse ($waitingOnOthers as $item)
                <a
                    href="{{ $item->url }}"
                    class="rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge color="zinc">{{ __('home.action_kind.'.$item->kind) }}</flux:badge>
                        <span class="font-semibold" dir="auto">{{ $item->title }}</span>
                    </div>
                    @if ($item->summary)
                        <div class="mt-2 text-sm text-zinc-500">{{ $item->summary }}</div>
                    @endif
                </a>
            @empty
                <div class="md:col-span-2">
                    <x-app.empty-state :title="__('home.nothing_waiting_on_others')" />
                </div>
            @endforelse
        </div>
    </flux:card>

    @endunless

    <div class="{{ $officeAlpha ? 'grid gap-6' : 'grid gap-6 xl:grid-cols-3' }}">
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('home.needs_offers') }}</flux:heading>
                <flux:button :href="route('intents.index')" size="sm" variant="ghost">{{ __('home.open_all') }}</flux:button>
            </div>
            <div class="space-y-3">
                @forelse ($activeIntents as $intent)
                    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge :color="$intent->kind->value === 'need' ? 'amber' : 'green'">
                                {{ __('intents.kinds.'.$intent->kind->value) }}
                            </flux:badge>
                            <span class="font-medium" dir="auto">{{ $intent->title ?: $intent->concept->displayLabel() }}</span>
                        </div>
                    </div>
                @empty
                    <x-app.empty-state :title="__('home.no_active_intents')" />
                @endforelse
            </div>
        </flux:card>

        @unless ($officeAlpha)
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('home.relationships') }}</flux:heading>
                <flux:button :href="route('relationships.index')" size="sm" variant="ghost">{{ __('home.open_all') }}</flux:button>
            </div>
            <div class="space-y-3">
                @forelse ($activeRelationships as $relationship)
                    <a href="{{ route('relationships.show', $relationship) }}" class="block rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                        <div class="font-medium" dir="auto">{{ $relationship->title ?: $relationship->purposeConcept->displayLabel() }}</div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('home.no_active_relationships')" />
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('home.groups') }}</flux:heading>
                <flux:button :href="route('groups.index')" size="sm" variant="ghost">{{ __('home.open_all') }}</flux:button>
            </div>
            <div class="space-y-3">
                @forelse ($groupMemberships as $membership)
                    <a href="{{ route('groups.community', $membership->group) }}" class="block rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                        <div class="font-medium">{{ $membership->group->name }}</div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('home.no_groups')" />
                @endforelse
            </div>
        </flux:card>
        @endunless
    </div>

    @unless ($officeAlpha)
    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('home.accounting_today') }}</flux:heading>
                    <flux:text>{{ __('home.accounting_today_help') }}</flux:text>
                </div>
                <flux:button :href="route('accounting.index')" size="sm" variant="ghost">{{ __('home.open_accounting') }}</flux:button>
            </div>

            <div class="space-y-3">
                @forelse ($accountingToday as $summary)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="mb-3 font-semibold">{{ $summary['code'] }}</div>
                        <div class="grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <div class="text-zinc-500">{{ __('home.income') }}</div>
                                <div class="font-semibold">{{ \App\Support\MoneyAmount::format($summary['income_minor'], $summary['exponent']) }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500">{{ __('home.expense') }}</div>
                                <div class="font-semibold">{{ \App\Support\MoneyAmount::format($summary['expense_minor'], $summary['exponent']) }}</div>
                            </div>
                            <div>
                                <div class="text-zinc-500">{{ __('home.net') }}</div>
                                <div class="font-semibold">{{ \App\Support\MoneyAmount::format($summary['net_minor'], $summary['exponent']) }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-app.empty-state :title="__('home.no_accounting_today')" :description="__('home.no_accounting_today_help')" />
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('home.obligations') }}</flux:heading>
                <flux:text>{{ __('home.obligations_help') }}</flux:text>
            </div>

            <div class="space-y-4">
                @forelse ($obligations as $summary)
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="mb-3 font-semibold">{{ $summary['code'] }}</div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('home.receivable') }}</div>
                                <div class="mt-2 text-sm">{{ __('home.total') }}: {{ \App\Support\MoneyAmount::format($summary['receivable_total_minor'], $summary['exponent']) }}</div>
                                <div class="text-sm">{{ __('home.paid') }}: {{ \App\Support\MoneyAmount::format($summary['receivable_paid_minor'], $summary['exponent']) }}</div>
                                <div class="font-semibold">{{ __('home.outstanding') }}: {{ \App\Support\MoneyAmount::format($summary['receivable_outstanding_minor'], $summary['exponent']) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('home.payable') }}</div>
                                <div class="mt-2 text-sm">{{ __('home.total') }}: {{ \App\Support\MoneyAmount::format($summary['payable_total_minor'], $summary['exponent']) }}</div>
                                <div class="text-sm">{{ __('home.paid') }}: {{ \App\Support\MoneyAmount::format($summary['payable_paid_minor'], $summary['exponent']) }}</div>
                                <div class="font-semibold">{{ __('home.outstanding') }}: {{ \App\Support\MoneyAmount::format($summary['payable_outstanding_minor'], $summary['exponent']) }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-app.empty-state :title="__('home.no_obligations')" />
                @endforelse
            </div>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('home.recent_activity') }}</flux:heading>
            <flux:text>{{ __('home.recent_activity_help') }}</flux:text>
        </div>

        <div class="space-y-3">
            @forelse ($recentActivity as $entry)
                <a href="{{ $entry->url }}" class="block rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge color="zinc">{{ $entry->kind }}</flux:badge>
                                <span class="font-semibold" dir="auto">{{ $entry->title }}</span>
                            </div>
                            @if ($entry->summary)
                                <div class="mt-2 text-sm text-zinc-500" dir="auto">{{ $entry->summary }}</div>
                            @endif
                        </div>
                        <div class="shrink-0 text-xs text-zinc-500">
                            <x-app.local-datetime :value="$entry->occurredAt" />
                        </div>
                    </div>
                </a>
            @empty
                <x-app.empty-state :title="__('home.no_recent_activity')" />
            @endforelse
        </div>
    </flux:card>

    <flux:callout>{{ __('home.boundary') }}</flux:callout>
    @endunless
</section>
