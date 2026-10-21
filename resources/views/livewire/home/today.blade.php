<section class="mx-auto max-w-7xl space-y-6">
    @php
        $homeUser = request()->user();
        $homeName = $homeUser?->actor?->profile?->display_name ?: $homeUser?->username;
        $hasActor = $homeUser?->actor !== null;
        $officeAlpha = config('release.profile') === 'office_alpha';
    @endphp

    <x-app.page-header
        :title="__('home.title')"
        :description="__('home.welcome', ['name' => $homeName, 'date' => $todayDisplay])"
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
            </div>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('email_verified_now'))
        <flux:callout variant="success">{{ __('ui.dashboard.verified') }}</flux:callout>
    @endif

    @if (! $hasActor)
        <flux:callout>{{ __('home.actor_setup_pending') }}</flux:callout>
    @endif

    @if ($nextAction)
        <flux:card class="space-y-4 border-indigo-200 bg-indigo-50/60 dark:border-indigo-900 dark:bg-indigo-950/20">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl space-y-2">
                    <flux:badge color="indigo">{{ __('home.guidance.recommended') }}</flux:badge>
                    <flux:heading size="xl" dir="auto">{{ $nextAction->title }}</flux:heading>
                    <flux:text>{{ $nextAction->summary }}</flux:text>
                </div>
                <flux:button :href="$nextAction->url" variant="primary">
                    {{ $nextAction->cta }}
                </flux:button>
            </div>
            <div class="rounded-xl border border-indigo-100 bg-white/70 p-4 text-sm dark:border-indigo-900 dark:bg-zinc-950/40">
                <div class="font-semibold">{{ __('home.guidance.what_happens_next') }}</div>
                <div class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $nextAction->consequence }}</div>
            </div>
        </flux:card>
    @endif

    @if (! $onboarding['complete'])
        <flux:card id="getting-started" class="space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('home.onboarding.title') }}</flux:heading>
                    <flux:text>{{ __('home.onboarding.help') }}</flux:text>
                </div>
                <flux:badge>{{ $onboarding['completed'] }}/{{ $onboarding['total'] }}</flux:badge>
            </div>

            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                <div class="h-full rounded-full bg-indigo-500 transition-all" style="width: {{ $onboarding['percent'] }}%"></div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                @foreach ($onboarding['steps'] as $step)
                    <div class="rounded-xl border p-4 {{ $step->complete ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900 dark:bg-emerald-950/20' : 'border-zinc-200 dark:border-zinc-800' }}">
                        <div class="flex items-center gap-2">
                            <flux:badge :color="$step->complete ? 'green' : 'zinc'">
                                {{ $step->complete ? __('home.onboarding.done') : __('home.onboarding.next') }}
                            </flux:badge>
                            <div class="font-semibold">{{ $step->title }}</div>
                        </div>
                        <div class="mt-2 text-sm text-zinc-500">{{ $step->summary }}</div>
                        @unless ($step->complete || ($step->key === 'first_goal' && $firstGoalChoices !== []))
                            <flux:button :href="$step->url" size="sm" variant="ghost" class="mt-3">
                                {{ $step->cta }}
                            </flux:button>
                        @endunless
                    </div>
                @endforeach
            </div>

            @if ($firstGoalChoices !== [])
                <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <div>
                        <div class="font-semibold">{{ __('home.goals.title') }}</div>
                        <div class="text-sm text-zinc-500">{{ __('home.goals.help') }}</div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        @foreach ($firstGoalChoices as $goal)
                            <a href="{{ $goal->url }}" class="rounded-xl border border-zinc-200 p-4 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-zinc-800 dark:hover:border-indigo-800 dark:hover:bg-indigo-950/20">
                                <div class="font-semibold">{{ $goal->title }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ $goal->summary }}</div>
                                <div class="mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ $goal->cta }} →</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </flux:card>
    @endif

    @if ($dontForget->isNotEmpty())
        <flux:card class="space-y-3 border-sky-300 bg-sky-50/60 dark:border-sky-900 dark:bg-sky-950/20" data-today-dont-forget>
            <div class="flex items-center gap-2">
                <flux:icon name="bell-alert" class="size-5 text-sky-700 dark:text-sky-300" />
                <flux:heading size="lg">{{ __('planner.reminders.dont_forget') }}</flux:heading>
            </div>
            <ul class="divide-y divide-sky-100 dark:divide-sky-900">
                @foreach ($dontForget as $due)
                    @php $overdue = $due->scheduled_start_at->isPast(); @endphp
                    <li wire:key="due-{{ $due->uuid }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <a href="{{ route('planner.show', $due->plan).'#occurrence-'.$due->uuid }}" wire:navigate class="min-w-0">
                            <span class="block truncate font-medium" dir="auto">{{ $due->plan->title }}</span>
                            <span class="text-xs {{ $overdue ? 'font-medium text-rose-700 dark:text-rose-400' : 'text-zinc-500' }}">
                                {{ $overdue ? __('planner.reminders.overdue') : __('planner.reminders.due') }} · <x-app.local-datetime :value="$due->scheduled_start_at" />
                            </span>
                        </a>
                        <flux:button wire:click="markDone('{{ $due->uuid }}')" size="sm" icon="check" data-mark-done>{{ __('planner.reminders.done') }}</flux:button>
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @endif

    @unless ($officeAlpha)
    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card class="order-2 space-y-4" data-today-schedule>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ __('home.today_actions') }}</flux:heading>
                        @if ($todayOccurrenceCount > 0)
                            <flux:badge size="sm" data-today-count>{{ $todayOccurrencesTruncated ? $todayOccurrenceCount.'+' : $todayOccurrenceCount }}</flux:badge>
                        @endif
                    </div>
                    <flux:text>{{ __('home.today_actions_help') }}</flux:text>
                </div>
                <flux:button :href="route('planner.index')" size="sm" variant="ghost">{{ __('home.open_all') }}</flux:button>
            </div>

            @php
                $focusOccurrence = $inProgressOccurrences->first() ?? $nextOccurrence;
            @endphp
            @if ($focusOccurrence)
                @php
                    $focusRunning = $inProgressOccurrences->isNotEmpty();
                    $focusRelative = $focusOccurrence->scheduled_start_at
                        ->locale(app()->getLocale())
                        ->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE);
                @endphp
                <a
                    href="{{ route('planner.show', $focusOccurrence->plan).'#occurrence-'.$focusOccurrence->uuid }}"
                    class="flex items-center justify-between gap-3 rounded-xl border border-indigo-200 bg-indigo-50/60 p-3 hover:bg-indigo-50 dark:border-indigo-900 dark:bg-indigo-950/20"
                    data-today-focus
                >
                    <div class="min-w-0">
                        <div class="text-xs font-medium uppercase tracking-wide text-indigo-700 dark:text-indigo-300">
                            {{ $focusRunning ? __('home.schedule.now') : __('home.schedule.next') }}
                        </div>
                        <div class="truncate font-semibold" dir="auto">{{ $focusOccurrence->plan->title }}</div>
                        <div class="text-xs text-zinc-600 dark:text-zinc-400">
                            <x-app.local-time :value="$focusOccurrence->scheduled_start_at" />
                            ·
                            @if ($focusRunning && $inProgressOccurrences->count() > 1)
                                {{ __('home.schedule.running_count', ['count' => $inProgressOccurrences->count()]) }}
                            @elseif ($focusRunning)
                                {{ __('home.schedule.in_progress') }}
                            @else
                                {{ __('home.schedule.starts_in', ['time' => $focusRelative]) }}
                            @endif
                        </div>
                    </div>
                    <span class="shrink-0 text-sm font-medium text-indigo-700 dark:text-indigo-300">{{ __('home.schedule.open') }} →</span>
                </a>
            @endif

            @php
                $scheduleRows = ($showFullSchedule ? $todayOccurrences : $todayOccurrencePreview)
                    ->groupBy(fn ($occurrence) => $occurrence->scheduled_start_at->getTimestamp());
                $hiddenCount = max(0, $todayOccurrenceCount - $todayOccurrencePreview->count());
            @endphp

            @if ($todayOccurrenceCount === 0)
                <x-app.empty-state :title="__('home.no_today_actions')" :description="__('home.no_today_actions_help')" />
            @else
                <ul @class(['divide-y divide-zinc-100 dark:divide-zinc-800', 'max-h-96 overflow-y-auto' => $showFullSchedule]) data-today-schedule-list>
                    @foreach ($scheduleRows as $slot => $slotOccurrences)
                        @if ($slotOccurrences->count() > 1)
                            <li wire:key="today-slot-{{ $slot }}">
                                <details class="group">
                                    <summary class="flex cursor-pointer list-none items-center gap-3 py-2 text-sm">
                                        <x-app.local-time :value="$slotOccurrences->first()->scheduled_start_at" class="w-14 shrink-0 tabular-nums text-zinc-500" />
                                        <span class="flex-1 font-medium">{{ __('home.schedule.activities', ['count' => $slotOccurrences->count()]) }}</span>
                                        <span class="text-zinc-400 transition-transform group-open:rotate-90">›</span>
                                    </summary>
                                    <ul class="mb-2 ms-14 space-y-1 border-s border-zinc-200 ps-3 dark:border-zinc-800">
                                        @foreach ($slotOccurrences as $occurrence)
                                            @include('livewire.home.partials.today-schedule-row', ['occurrence' => $occurrence, 'nested' => true])
                                        @endforeach
                                    </ul>
                                </details>
                            </li>
                        @else
                            @include('livewire.home.partials.today-schedule-row', ['occurrence' => $slotOccurrences->first(), 'nested' => false])
                        @endif
                    @endforeach
                </ul>

                @if ($hiddenCount > 0 || $showFullSchedule)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                        <flux:button wire:click="toggleFullSchedule" size="sm" variant="ghost" data-today-more>
                            {{ $showFullSchedule ? __('home.schedule.show_less') : __('home.schedule.more_today', ['count' => $hiddenCount]) }}
                        </flux:button>
                        <flux:button :href="route('planner.index')" size="sm" variant="ghost">{{ __('home.schedule.open_planner') }}</flux:button>
                    </div>
                @endif
            @endif
        </flux:card>

        <flux:card class="order-1 space-y-4">
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="lg">{{ __('home.waiting_on_me') }}</flux:heading>
                    @if ($attentionCount > 0)
                        <flux:badge color="amber">{{ $attentionCount }}</flux:badge>
                    @endif
                </div>
                <flux:text>{{ __('home.waiting_on_me_help') }}</flux:text>
            </div>

            <div class="space-y-3">
                @forelse ($waitingOnMe->take(5) as $item)
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
                @if ($waitingOnMe->count() > 5)
                    <details class="group" data-today-overflow="waitingOnMe">
                        <summary class="cursor-pointer list-none text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                            {{ __('home.schedule.more_items', ['count' => $waitingOnMe->count() - 5]) }}
                        </summary>
                        <ul class="mt-2 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            @foreach ($waitingOnMe->skip(5) as $overflowItem)
                                <li><a href="{{ $overflowItem->url }}" class="flex items-center gap-2 py-2 hover:underline"><span class="truncate" dir="auto">{{ __('home.action_kind.'.$overflowItem->kind) }} · {{ $overflowItem->title }}</span></a></li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('home.waiting_on_others') }}</flux:heading>
            <flux:text>{{ __('home.waiting_on_others_help') }}</flux:text>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            @forelse ($waitingOnOthers->take(4) as $item)
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
                @if ($waitingOnOthers->count() > 4)
                    <details class="group md:col-span-2" data-today-overflow="waitingOnOthers">
                        <summary class="cursor-pointer list-none text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                            {{ __('home.schedule.more_items', ['count' => $waitingOnOthers->count() - 4]) }}
                        </summary>
                        <ul class="mt-2 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            @foreach ($waitingOnOthers->skip(4) as $overflowItem)
                                <li><a href="{{ $overflowItem->url }}" class="flex items-center gap-2 py-2 hover:underline"><span class="truncate" dir="auto">{{ __('home.action_kind.'.$overflowItem->kind) }} · {{ $overflowItem->title }}</span></a></li>
                            @endforeach
                        </ul>
                    </details>
                @endif
        </div>
    </flux:card>

    @endunless

    @unless ($officeAlpha)
        <details class="group rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                <div>
                    <div class="font-semibold">{{ __('home.more_overview') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('home.more_overview_help') }}</div>
                </div>
                <span class="text-sm text-zinc-500 transition-transform group-open:rotate-90">›</span>
            </summary>
            <div class="mt-5 space-y-6">
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
                <flux:button :href="route('deals.index')" size="sm" variant="ghost">{{ __('home.open_all') }}</flux:button>
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
    @include('livewire.home.partials.today-money')

        </div>
        </details>

        <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('home.recent_activity') }}</flux:heading>
            <flux:text>{{ __('home.recent_activity_help') }}</flux:text>
        </div>

        <div class="space-y-3">
            @forelse ($recentActivity->take(6) as $entry)
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
                @if ($recentActivity->count() > 6)
                    <details class="group" data-today-overflow="recentActivity">
                        <summary class="cursor-pointer list-none text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                            {{ __('home.schedule.more_items', ['count' => $recentActivity->count() - 6]) }}
                        </summary>
                        <ul class="mt-2 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            @foreach ($recentActivity->skip(6) as $overflowItem)
                                <li><a href="{{ $overflowItem->url }}" class="flex items-center gap-2 py-2 hover:underline"><span class="truncate" dir="auto">{{ $overflowItem->title }}</span></a></li>
                            @endforeach
                        </ul>
                    </details>
                @endif
        </div>
    </flux:card>

    @endunless
</section>
