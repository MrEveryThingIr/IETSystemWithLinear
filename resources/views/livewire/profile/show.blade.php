<section class="space-y-8">
    @php
        $displayImage = $profile->displayImage;
        $displayName = $profile->display_name ?: ($profile->actor->user?->username ?? __('ui.profile.unnamed'));
    @endphp

    <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="h-28 bg-gradient-to-r from-zinc-100 via-zinc-50 to-zinc-100 dark:from-zinc-800 dark:via-zinc-900 dark:to-zinc-800"></div>
        <div class="px-5 pb-6 sm:px-8">
            <div class="-mt-14 flex flex-col gap-5 sm:flex-row sm:items-end">
                <div class="h-28 w-28 shrink-0 overflow-hidden rounded-3xl border-4 border-white bg-zinc-200 shadow-sm dark:border-zinc-900 dark:bg-zinc-800">
                    @if ($displayImage && $displayImage->asset?->isReadyForPublication())
                        <img
                            src="{{ route('profiles.images.show', [$profile, $displayImage]) }}"
                            alt="{{ __('ui.profile.avatar_alt', ['name' => $displayName]) }}"
                            class="h-full w-full object-cover"
                        >
                    @else
                        <div class="flex h-full w-full items-center justify-center text-3xl font-semibold text-zinc-500 dark:text-zinc-300">
                            {{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1 pb-1">
                    <h1 class="truncate text-2xl font-semibold tracking-tight sm:text-3xl" dir="auto">{{ $displayName }}</h1>
                    @if ($profile->headline)
                        <p class="mt-1 text-base text-zinc-600 dark:text-zinc-300" dir="auto">{{ $profile->headline }}</p>
                    @endif
                </div>

                @auth
                    @if ((int) auth()->user()?->actor?->id === (int) $profile->actor_id)
                        <flux:button :href="route('profile.edit')" variant="primary">{{ __('ui.profile.edit') }}</flux:button>
                    @endif
                @endauth
            </div>

            @if ($profile->location_text || $profile->website_url)
                <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                    @if ($profile->location_text)
                        <span dir="auto">{{ $profile->location_text }}</span>
                    @endif
                    @if ($profile->website_url)
                        <a href="{{ $profile->website_url }}" target="_blank" rel="noopener noreferrer nofollow" class="font-medium underline decoration-zinc-300 underline-offset-4 hover:decoration-current">
                            {{ __('ui.profile.website') }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if ($profile->bio)
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 sm:p-7 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">{{ __('ui.profile.about') }}</h2>
            <p class="mt-3 whitespace-pre-line leading-7 text-zinc-700 dark:text-zinc-200" dir="auto">{{ $profile->bio }}</p>
        </article>
    @endif

    @if ($skills->isNotEmpty() || $interests->isNotEmpty() || $learningGoals->isNotEmpty())
        <section class="grid gap-4 lg:grid-cols-3">
            @foreach ([
                ['items' => $skills, 'title' => __('ui.profile.semantics.sections.skills')],
                ['items' => $interests, 'title' => __('ui.profile.semantics.sections.interests')],
                ['items' => $learningGoals, 'title' => __('ui.profile.semantics.sections.learning_goals')],
            ] as $semanticSection)
                @if ($semanticSection['items']->isNotEmpty())
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                        <h2 class="font-semibold">{{ $semanticSection['title'] }}</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($semanticSection['items'] as $assertion)
                                <span class="rounded-full bg-zinc-100 px-3 py-1.5 text-sm dark:bg-zinc-800" dir="auto">
                                    {{ $assertion->concept->displayLabel() }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </section>
    @endif

    @if ($needs->isNotEmpty() || $offers->isNotEmpty())
        <section class="grid gap-6 lg:grid-cols-2">
            @foreach ([
                ['items' => $needs, 'title' => __('ui.profile.intents.public_needs'), 'badge' => 'amber'],
                ['items' => $offers, 'title' => __('ui.profile.intents.public_offers'), 'badge' => 'green'],
            ] as $intentSection)
                @if ($intentSection['items']->isNotEmpty())
                    <div class="space-y-3">
                        <h2 class="text-lg font-semibold">{{ $intentSection['title'] }}</h2>
                        @foreach ($intentSection['items'] as $intent)
                            <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold" dir="auto">{{ $intent->title ?: $intent->concept->displayLabel() }}</h3>
                                        @if ($intent->title)
                                            <p class="mt-0.5 text-sm text-zinc-500" dir="auto">{{ $intent->concept->displayLabel() }}</p>
                                        @endif
                                    </div>
                                    <flux:badge :color="$intentSection['badge']">{{ __('ui.profile.intents.schedules.'.$intent->schedule_kind->value) }}</flux:badge>
                                </div>

                                @if ($intent->description)
                                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-200" dir="auto">{{ $intent->description }}</p>
                                @endif

                                <dl class="mt-4 grid gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    @if ($intent->quantity !== null)
                                        <div><dt class="inline font-medium">{{ __('ui.profile.intents.quantity') }}:</dt> <dd class="inline">{{ rtrim(rtrim($intent->quantity, '0'), '.') }} {{ $intent->unit }}</dd></div>
                                    @endif

                                    @if ($intent->origin_text && $intent->destination_text)
                                        <div><dt class="inline font-medium">{{ __('ui.profile.intents.route') }}:</dt> <dd class="inline" dir="auto">{{ $intent->origin_text }} → {{ $intent->destination_text }}</dd></div>
                                    @elseif ($intent->location_text)
                                        <div><dt class="inline font-medium">{{ __('ui.profile.intents.location') }}:</dt> <dd class="inline" dir="auto">{{ $intent->location_text }}</dd></div>
                                    @endif

                                    @if ($intent->schedule_kind->value === 'weekly' && ! empty($intent->recurrence_weekdays))
                                        <div>
                                            <dt class="inline font-medium">{{ __('ui.profile.intents.weekdays') }}:</dt>
                                            <dd class="inline">
                                                {{ collect($intent->recurrence_weekdays)->map(fn ($day) => __('ui.profile.intents.weekday_names.'.$day))->join(', ') }}
                                            </dd>
                                        </div>
                                    @elseif ($intent->schedule_kind->value === 'monthly' && $intent->recurrence_day_of_month)
                                        <div><dt class="inline font-medium">{{ __('ui.profile.intents.day_of_month') }}:</dt> <dd class="inline">{{ $intent->recurrence_day_of_month }}</dd></div>
                                    @endif

                                    @if ($intent->schedule_kind->isRecurring() && $intent->recurrence_interval > 1)
                                        <div><dt class="inline font-medium">{{ __('ui.profile.intents.repeat_every') }}:</dt> <dd class="inline">{{ $intent->recurrence_interval }}</dd></div>
                                    @endif

                                    @if ($intent->time_window_start || $intent->time_window_end)
                                        <div><dt class="inline font-medium">{{ __('ui.profile.intents.time_window') }}:</dt> <dd class="inline">{{ substr((string) $intent->time_window_start, 0, 5) }}{{ $intent->time_window_end ? '–'.substr((string) $intent->time_window_end, 0, 5) : '' }} {{ $intent->timezone }}</dd></div>
                                    @endif

                                    @if ($intent->round_trip)
                                        <div><dt class="inline font-medium">{{ __('ui.profile.intents.round_trip') }}:</dt> <dd class="inline">{{ trans_choice('ui.profile.intents.return_days', $intent->return_after_days ?? 0, ['count' => $intent->return_after_days ?? 0]) }}</dd></div>
                                    @endif
                                </dl>
                            </article>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </section>
    @endif
</section>
