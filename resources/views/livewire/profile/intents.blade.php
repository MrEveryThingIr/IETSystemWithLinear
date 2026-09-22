<section
    class="space-y-6 rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900"
    @if ($timezoneAutomatic)
        x-data
        x-init="$wire.useBrowserTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)"
    @endif
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold">{{ __('ui.profile.intents.title') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile.intents.help') }}</p>
        </div>
        @unless ($editorOpen)
            <flux:button wire:click="openCreate" size="sm" variant="ghost" icon="plus" class="w-full shrink-0 sm:w-auto">
                {{ __('ui.profile.intents.add') }}
            </flux:button>
        @endunless
    </div>

    @if ($editorOpen)
    <form wire:submit="save" class="min-w-0 space-y-5 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950/50">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <label class="text-sm font-medium" for="intent-kind">{{ __('ui.profile.intents.kind') }}</label>
                <select id="intent-kind" wire:model="kind" @disabled($editingIntentId !== null) class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="need">{{ __('ui.profile.intents.kinds.need') }}</option>
                    <option value="offer">{{ __('ui.profile.intents.kinds.offer') }}</option>
                </select>
            </div>

            <div class="relative space-y-2">
                <flux:input wire:model.live.debounce.250ms="conceptLabel" :disabled="$editingIntentId !== null" :label="__('ui.profile.intents.concept')" maxlength="120" />
                @if ($editingIntentId === null && $conceptSuggestions !== [])
                    <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($conceptSuggestions as $suggestion)
                            <button
                                type="button"
                                wire:click="selectConceptSuggestion(@js($suggestion))"
                                class="block w-full px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                dir="auto"
                            >
                                {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>
                @elseif ($editingIntentId === null && mb_strlen(trim($conceptLabel)) >= 2)
                    <p class="text-xs text-zinc-500">{{ __('ui.profile.intents.custom_concept_hint') }}</p>
                @endif
            </div>
        </div>

        <div>
            <p class="text-sm font-medium">{{ __('ui.profile.intents.add_details') }}</p>
            <p class="mt-1 text-xs text-zinc-500">{{ __('ui.profile.intents.add_details_help') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['title', 'description', 'quantity', 'location', 'route', 'timing', 'visibility'] as $facet)
                    <button
                        type="button"
                        wire:click="toggleFacet('{{ $facet }}')"
                        class="rounded-full border px-3 py-1.5 text-sm transition {{ in_array($facet, $activeFacets, true) ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 bg-white hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800' }}"
                    >
                        {{ in_array($facet, $activeFacets, true) ? '−' : '+' }}
                        {{ __('ui.profile.intents.facets.'.$facet) }}
                    </button>
                @endforeach
            </div>
        </div>

        @if (in_array('title', $activeFacets, true))
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <flux:input wire:model="title" :label="__('ui.profile.intents.intent_title')" maxlength="180" />
            </div>
        @endif

        @if (in_array('description', $activeFacets, true))
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <flux:textarea wire:model="description" :label="__('ui.profile.intents.description')" rows="3" maxlength="3000" />
            </div>
        @endif

        @if (in_array('quantity', $activeFacets, true))
            <div class="grid gap-4 rounded-xl border border-zinc-200 p-4 md:grid-cols-2 dark:border-zinc-800">
                <flux:input wire:model="quantity" type="number" step="0.0001" min="0" :label="__('ui.profile.intents.quantity')" />
                <flux:input wire:model="unit" :label="__('ui.profile.intents.unit')" maxlength="64" placeholder="kg, seat, hour…" />
            </div>
        @endif

        @if (in_array('location', $activeFacets, true))
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <flux:input wire:model="locationText" :label="__('ui.profile.intents.location')" maxlength="255" />
            </div>
        @endif

        @if (in_array('route', $activeFacets, true))
            <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="originText" :label="__('ui.profile.intents.origin')" maxlength="255" />
                    <flux:input wire:model="destinationText" :label="__('ui.profile.intents.destination')" maxlength="255" />
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="roundTrip">
                        {{ __('ui.profile.intents.round_trip') }}
                    </label>
                    @if ($roundTrip)
                        <div class="w-full sm:w-48">
                            <flux:input wire:model="returnAfterDays" type="number" min="0" max="3650" :label="__('ui.profile.intents.return_after_days')" />
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if (in_array('timing', $activeFacets, true))
            <div class="space-y-5 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium" for="schedule-kind">{{ __('ui.profile.intents.schedule_kind') }}</label>
                        <select id="schedule-kind" wire:model.live="scheduleKind" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($scheduleKinds as $option)
                                <option value="{{ $option->value }}">{{ __('ui.profile.intents.schedules.'.$option->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-app.calendar-date-input
                        model="startsOn"
                        :label="__('ui.profile.intents.starts_on')"
                        :calendar="$calendar"
                        :locale="$intlLocale"
                        :timezone="$timezone"
                        :first-day="$firstDay"
                    />
                    <x-app.calendar-date-input
                        model="endsOn"
                        :label="__('ui.profile.intents.ends_on')"
                        :calendar="$calendar"
                        :locale="$intlLocale"
                        :timezone="$timezone"
                        :first-day="$firstDay"
                    />
                    <flux:input wire:model="timezone" :label="__('ui.profile.intents.timezone')" maxlength="64" />
                </div>

                @if (in_array($scheduleKind, ['daily', 'weekly', 'monthly'], true))
                    <div class="grid gap-4 md:grid-cols-3">
                        <flux:input wire:model="recurrenceInterval" type="number" min="1" max="365" :label="__('ui.profile.intents.repeat_every')" />
                        <flux:input wire:model="timeWindowStart" type="time" :label="__('ui.profile.intents.time_from')" />
                        <flux:input wire:model="timeWindowEnd" type="time" :label="__('ui.profile.intents.time_to')" />
                    </div>
                @endif

                @if ($scheduleKind === 'weekly')
                    <fieldset>
                        <legend class="text-sm font-medium">{{ __('ui.profile.intents.weekdays') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($weekdayOrder as $weekday)
                                <label class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-800">
                                    <input type="checkbox" wire:model="recurrenceWeekdays" value="{{ $weekday }}">
                                    {{ __('ui.profile.intents.weekday_names.'.$weekday) }}
                                </label>
                            @endforeach
                        </div>
                        @error('recurrenceWeekdays') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                @if ($scheduleKind === 'monthly')
                    <flux:input wire:model="recurrenceDayOfMonth" type="number" min="1" max="31" :label="__('ui.profile.intents.day_of_month')" />
                @endif
            </div>
        @endif

        @if (in_array('visibility', $activeFacets, true))
            <div class="space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <label class="text-sm font-medium" for="intent-visibility">{{ __('ui.profile.item_visibility') }}</label>
                <select id="intent-visibility" wire:model="itemVisibility" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    @foreach ($visibilityOptions as $option)
                        <option value="{{ $option->value }}">{{ __('ui.profile.item_visibility_options.'.$option->value) }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
            <flux:button type="button" wire:click="cancelEdit" variant="ghost" class="w-full sm:w-auto">{{ __('ui.common.cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto">
                {{ $editingIntentId !== null ? __('ui.profile.intents.save_changes') : __('ui.profile.intents.add') }}
            </flux:button>
        </div>
    </form>
    @endif

    @if ($intents->isNotEmpty())
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($intents as $intent)
                <article class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" wire:key="profile-intent-{{ $intent->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge :color="$intent->kind->value === 'need' ? 'amber' : 'green'">
                                    {{ __('ui.profile.intents.kinds.'.$intent->kind->value) }}
                                </flux:badge>
                                <flux:badge color="zinc">{{ __('ui.profile.intents.statuses.'.$intent->status->value) }}</flux:badge>
                            </div>
                            <h3 class="mt-2 font-semibold" dir="auto">{{ $intent->title ?: $intent->concept->displayLabel() }}</h3>
                            @if ($intent->title)
                                <p class="mt-0.5 text-sm text-zinc-500" dir="auto">{{ $intent->concept->displayLabel() }}</p>
                            @endif
                        </div>
                        <span class="text-xs text-zinc-500">{{ __('ui.profile.intents.schedules.'.$intent->schedule_kind->value) }}</span>
                    </div>

                    @if ($intent->description)
                        <p class="mt-3 text-sm leading-6 text-zinc-700 dark:text-zinc-200" dir="auto">{{ $intent->description }}</p>
                    @endif

                    <dl class="mt-3 grid gap-1 text-sm text-zinc-600 dark:text-zinc-300">
                        @if ($intent->quantity !== null)
                            <div><dt class="inline font-medium">{{ __('ui.profile.intents.quantity') }}:</dt> <dd class="inline">{{ rtrim(rtrim($intent->quantity, '0'), '.') }} {{ $intent->unit }}</dd></div>
                        @endif
                        @if ($intent->origin_text && $intent->destination_text)
                            <div><dt class="inline font-medium">{{ __('ui.profile.intents.route') }}:</dt> <dd class="inline" dir="auto">{{ $intent->origin_text }} → {{ $intent->destination_text }}</dd></div>
                        @elseif ($intent->location_text)
                            <div><dt class="inline font-medium">{{ __('ui.profile.intents.location') }}:</dt> <dd class="inline" dir="auto">{{ $intent->location_text }}</dd></div>
                        @endif
                        @if ($intent->starts_on || $intent->ends_on)
                            <div>
                                <dt class="inline font-medium">{{ __('ui.profile.intents.date_range') }}:</dt>
                                <dd class="inline">
                                    @if ($intent->starts_on)
                                        <time
                                            datetime="{{ $intent->starts_on->toDateString() }}"
                                            data-localized-date="{{ $intent->starts_on->toDateString() }}"
                                            data-locale="{{ $intlLocale }}"
                                            data-calendar="{{ $calendar }}"
                                        >{{ $intent->starts_on->toDateString() }}</time>
                                    @endif
                                    @if ($intent->ends_on)
                                        <span aria-hidden="true"> → </span>
                                        <time
                                            datetime="{{ $intent->ends_on->toDateString() }}"
                                            data-localized-date="{{ $intent->ends_on->toDateString() }}"
                                            data-locale="{{ $intlLocale }}"
                                            data-calendar="{{ $calendar }}"
                                        >{{ $intent->ends_on->toDateString() }}</time>
                                    @endif
                                </dd>
                            </div>
                        @endif
                        @if ($intent->schedule_kind->value === 'weekly' && ! empty($intent->recurrence_weekdays))
                            <div>
                                <dt class="inline font-medium">{{ __('ui.profile.intents.weekdays') }}:</dt>
                                <dd class="inline">{{ collect($intent->recurrence_weekdays)->map(fn ($day) => __('ui.profile.intents.weekday_names.'.$day))->join(', ') }}</dd>
                            </div>
                        @elseif ($intent->schedule_kind->value === 'monthly' && $intent->recurrence_day_of_month)
                            <div><dt class="inline font-medium">{{ __('ui.profile.intents.day_of_month') }}:</dt> <dd class="inline">{{ $intent->recurrence_day_of_month }}</dd></div>
                        @endif
                        @if ($intent->time_window_start || $intent->time_window_end)
                            <div>
                                <dt class="inline font-medium">{{ __('ui.profile.intents.time_window') }}:</dt>
                                <dd class="inline">
                                    @if ($intent->time_window_start)
                                        <span data-localized-time="{{ substr((string) $intent->time_window_start, 0, 5) }}" data-locale="{{ $intlLocale }}">{{ substr((string) $intent->time_window_start, 0, 5) }}</span>
                                    @endif
                                    @if ($intent->time_window_end)
                                        <span aria-hidden="true">–</span>
                                        <span data-localized-time="{{ substr((string) $intent->time_window_end, 0, 5) }}" data-locale="{{ $intlLocale }}">{{ substr((string) $intent->time_window_end, 0, 5) }}</span>
                                    @endif
                                    <span>{{ $intent->timezone }}</span>
                                </dd>
                            </div>
                        @endif
                        @if ($intent->round_trip)
                            <div><dt class="inline font-medium">{{ __('ui.profile.intents.round_trip') }}:</dt> <dd class="inline">{{ trans_choice('ui.profile.intents.return_days', $intent->return_after_days ?? 0, ['count' => $intent->return_after_days ?? 0]) }}</dd></div>
                        @endif
                    </dl>

                    @if ($intent->status->value !== 'closed')
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <flux:button wire:click="edit({{ $intent->id }})" size="xs" variant="ghost" class="w-full sm:w-auto">{{ __('ui.common.edit') }}</flux:button>
                            @if ($intent->status->value === 'active')
                                <flux:button wire:click="setStatus({{ $intent->id }}, 'paused')" size="xs" variant="ghost" class="w-full sm:w-auto">{{ __('ui.profile.intents.pause') }}</flux:button>
                            @else
                                <flux:button wire:click="setStatus({{ $intent->id }}, 'active')" size="xs" variant="ghost" class="w-full sm:w-auto">{{ __('ui.profile.intents.activate') }}</flux:button>
                            @endif
                            <flux:button wire:click="setStatus({{ $intent->id }}, 'closed')" wire:confirm="{{ __('ui.profile.intents.close_confirm') }}" size="xs" variant="danger" class="w-full sm:w-auto">
                                {{ __('ui.profile.intents.close') }}
                            </flux:button>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>
