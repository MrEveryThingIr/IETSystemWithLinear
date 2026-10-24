<section class="mx-auto max-w-4xl space-y-6" data-plan-wizard>
    <x-app.page-header :title="__('planner.create.title')" :description="__('planner.wizard.help')" />

    @if ($blueprintVersion)
        <flux:callout>
            <div class="space-y-3">
                <div class="font-medium">
                    {{ __('journeys.guided_by', [
                        'name' => $blueprintVersion->blueprint->name,
                        'version' => $blueprintVersion->version,
                    ]) }}
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($blueprintVersion->capabilities as $capability)
                        <flux:badge color="zinc">{{ __('journeys.capabilities.'.$capability) }}</flux:badge>
                    @endforeach
                </div>
            </div>
        </flux:callout>
    @endif

    {{-- Progress --}}
    <ol class="flex items-center gap-1 overflow-x-auto pb-1 text-sm" aria-label="{{ __('planner.wizard.progress') }}">
        @foreach ($steps as $index => $stepKey)
            @php $done = array_search($step, $steps, true) > $index; @endphp
            <li class="flex shrink-0 items-center gap-1">
                <button type="button" wire:click="goTo('{{ $stepKey }}')"
                    class="flex items-center gap-2 rounded-full px-3 py-1.5 {{ $step === $stepKey ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : ($done ? 'text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800' : 'text-zinc-400') }}"
                    @if ($step === $stepKey) aria-current="step" @endif data-wizard-step="{{ $stepKey }}">
                    <span class="flex size-5 items-center justify-center rounded-full text-xs {{ $done ? 'bg-emerald-500 text-white' : 'border border-current' }}">{{ $done ? '✓' : $index + 1 }}</span>
                    {{ __('planner.wizard.steps.'.$stepKey) }}
                </button>
                @if (! $loop->last)<span class="h-px w-4 bg-zinc-300 dark:bg-zinc-700" aria-hidden="true"></span>@endif
            </li>
        @endforeach
    </ol>

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" data-wizard-errors>
            <flux:callout.heading>{{ __('planner.wizard.fix_first') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="list-disc space-y-0.5 ps-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="{{ $step === 'review' ? 'save' : 'next' }}" class="space-y-6">
        {{-- 1. What --}}
        @if ($step === 'what')
            <flux:card class="space-y-5" wire:key="wizard-what">
                <div>
                    <flux:heading size="lg">{{ __('planner.wizard.what_title') }}</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('planner.wizard.what_help') }}</p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach ($kinds as $kindKey => $kindConfig)
                        <button type="button" wire:click="chooseKind('{{ $kindKey }}')"
                            class="flex items-start gap-3 rounded-xl border p-3 text-start transition {{ $kind === $kindKey ? 'border-transparent ring-2' : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700' }}"
                            style="{{ $kind === $kindKey ? '--tw-ring-color: '.$kindConfig['color'].'; background: color-mix(in srgb, '.$kindConfig['color'].' 8%, transparent)' : '' }}"
                            aria-pressed="{{ $kind === $kindKey ? 'true' : 'false' }}" data-plan-kind="{{ $kindKey }}">
                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg text-white" style="background: {{ $kindConfig['color'] }}">
                                <flux:icon :name="$kindConfig['icon']" class="size-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block font-medium">{{ __('planner.kinds.'.$kindKey.'.name') }}</span>
                                <span class="block text-xs text-zinc-500">{{ __('planner.kinds.'.$kindKey.'.help') }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
                <a href="{{ route('planner.habits.create') }}" wire:navigate class="flex items-start gap-3 rounded-xl border border-dashed border-teal-300 p-3 text-start hover:bg-teal-50 dark:border-teal-800 dark:hover:bg-teal-950/30" data-plan-kind-habit>
                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-teal-600 text-white"><flux:icon name="arrow-path-rounded-square" class="size-4" /></span>
                    <span class="min-w-0"><span class="block font-medium">{{ __('habits.create.title') }}</span><span class="block text-xs text-zinc-500">{{ __('habits.create.card_help') }}</span></span>
                </a>
                <flux:input wire:model="title" :label="__('planner.create.activity_title')" maxlength="180" :placeholder="__('planner.kinds.'.$kind.'.example')" autofocus />
                <details class="group" @if ($description !== '') open @endif>
                    <summary class="cursor-pointer text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('planner.wizard.add_description') }}</summary>
                    <flux:textarea wire:model="description" rows="3" maxlength="10000" class="mt-2" :aria-label="__('planner.create.description')" />
                </details>
                <div class="flex items-center gap-2 text-xs text-zinc-500">
                    <flux:icon name="map-pin" class="size-4" />
                    <span dir="auto">{{ $contextLabel }}</span>
                </div>
            </flux:card>
        @endif

        {{-- 2. When --}}
        @if ($step === 'when')
            <flux:card class="space-y-5" wire:key="wizard-when">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ $kind === 'meeting' ? __('planner.wizard.when_meeting_title') : __('planner.wizard.when_title') }}</flux:heading>
                        <p class="mt-1 text-sm text-zinc-500">{{ $kind === 'meeting' ? __('planner.wizard.when_meeting_help') : __('planner.wizard.when_help') }}</p>
                    </div>
                    <div class="flex rounded-lg bg-zinc-100 p-0.5 text-xs dark:bg-zinc-800" role="group" aria-label="{{ __('planner.wizard.time_mode') }}">
                        @foreach (['visual', 'exact'] as $mode)
                            <button type="button" wire:click="$set('timeMode', '{{ $mode }}')" class="rounded-md px-2.5 py-1 {{ $timeMode === $mode ? 'bg-white shadow-sm dark:bg-zinc-900' : 'text-zinc-500' }}" data-time-mode="{{ $mode }}">{{ __('planner.wizard.time_modes.'.$mode) }}</button>
                        @endforeach
                    </div>
                </div>

                @if ($timeMode === 'visual')
                    <x-planner.time-picker :picker="$picker" :busy="$busy" :slot-mode="$kind === 'meeting'" wire:key="picker-{{ $kind }}" />
                @else
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-app.calendar-date-input model="startsOn" :label="__('planner.create.starts_on')" :timezone="$timezone" />
                        <flux:input wire:model="startTime" type="time" :label="__('planner.create.start_time')" />
                        <flux:input wire:model="durationMinutes" type="number" min="1" max="10080" :label="__('planner.create.duration')" />
                    </div>
                    @if ($kind === 'meeting')
                        <flux:button type="button" wire:click="addSlotFromFields" size="sm" icon="plus">{{ __('planner.picker.add_option') }}</flux:button>
                    @endif
                @endif

                @if ($kind === 'meeting')
                    <div class="space-y-2" data-meeting-slots>
                        <div class="text-sm font-medium">{{ trans_choice('planner.meetings.options_count', count($meetingSlots), ['count' => count($meetingSlots)]) }}</div>
                        @error('slots')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        <div class="flex flex-wrap gap-2">
                            @foreach ($meetingSlots as $index => $slot)
                                @php $slotStart = \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i', $slot, $timezone); @endphp
                                <span wire:key="slot-{{ $slot }}" class="inline-flex items-center gap-2 rounded-full border border-violet-300 bg-violet-50 px-3 py-1 text-sm dark:border-violet-800 dark:bg-violet-950">
                                    <x-app.local-datetime :value="$slotStart" />
                                    <button type="button" wire:click="removeSlot({{ $index }})" class="text-violet-700 hover:text-red-600" aria-label="{{ __('planner.create.remove') }}">×</button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="space-y-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        @php $repeat = match ($frequency) { 'daily' => 'daily', 'weekly' => 'weekly', 'monthly' => 'monthly', default => 'none' }; @endphp
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium">{{ __('planner.wizard.repeat') }}</span>
                            @foreach (['none', 'daily', 'weekly', 'monthly'] as $option)
                                <button type="button" wire:click="setRepeat('{{ $option }}')" class="rounded-full border px-3 py-1 text-sm {{ $repeat === $option ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-200 dark:border-zinc-700' }}" data-repeat="{{ $option }}">{{ __('planner.wizard.repeats.'.$option) }}</button>
                            @endforeach
                        </div>
                        @if ($frequency === 'weekly')
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($weekdayOrder as $weekday)
                                    <label class="cursor-pointer">
                                        <input type="checkbox" wire:model.live="weekdays" value="{{ $weekday }}" class="peer sr-only">
                                        <span class="block rounded-lg border border-zinc-200 px-3 py-1.5 text-sm peer-checked:border-emerald-600 peer-checked:bg-emerald-600 peer-checked:text-white dark:border-zinc-700">{{ __('planner.weekdays.'.$weekday) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @if ($frequency === 'monthly')
                            <p class="text-sm text-zinc-600 dark:text-zinc-300" data-monthly-day>{{ __('planner.wizard.monthly_on', ['day' => $monthDayLabel]) }}</p>
                        @endif
                        @if (in_array($frequency, ['daily', 'weekly', 'monthly'], true))
                            <div class="grid gap-3 sm:grid-cols-3">
                                <flux:input wire:model="interval" type="number" min="1" max="365" :label="__('planner.wizard.every', ['unit' => __('planner.wizard.units.'.$frequency)])" :description="__('planner.wizard.help_texts.interval_'.$frequency)" title="{{ __('planner.wizard.help_texts.interval_'.$frequency) }}" />
                                <div title="{{ __('planner.wizard.help_texts.ends_on') }}">
                                    <x-app.calendar-date-input model="endsOn" :label="__('planner.create.ends_on')" :timezone="$timezone" />
                                    <p class="mt-1 text-xs text-zinc-500">{{ __('planner.wizard.help_texts.ends_on') }}</p>
                                </div>
                                <flux:input wire:model="occurrenceLimit" type="number" min="1" max="10000" :label="__('planner.create.occurrence_limit')" :description="__('planner.wizard.help_texts.occurrence_limit')" title="{{ __('planner.wizard.help_texts.occurrence_limit') }}" />
                            </div>
                        @endif
                        @if ($frequency === 'selected_dates' && $timeMode === 'exact')
                            <flux:textarea wire:model="selectedDates" :label="__('planner.create.selected_dates')" rows="2" :description="__('planner.create.selected_dates_help')" />
                        @endif
                    </div>
                @endif
                @error('startsOn')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('startTime')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </flux:card>
        @endif

        {{-- 3. Who (meetings) --}}
        @if ($step === 'who')
            <flux:card class="space-y-5" wire:key="wizard-who">
                <div>
                    <flux:heading size="lg">{{ __('planner.wizard.who_title') }}</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('planner.wizard.who_help') }}</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-sm font-medium">{{ __('planner.wizard.invite_user') }}</div>
                        <div class="flex gap-2">
                            <div class="min-w-0 flex-1"><flux:input wire:model="inviteUsername" wire:keydown.enter.prevent="addUserInvitee" :placeholder="__('planner.wizard.username')" :aria-label="__('planner.wizard.username')" /></div>
                            <flux:button type="button" wire:click="addUserInvitee" icon="plus" :aria-label="__('planner.wizard.add')" />
                        </div>
                        @error('inviteUsername')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="text-sm font-medium">{{ __('planner.wizard.invite_guest') }}</div>
                        <flux:input wire:model="guestName" :placeholder="__('planner.wizard.guest_name')" :aria-label="__('planner.wizard.guest_name')" />
                        <div class="flex gap-2">
                            <div class="min-w-0 flex-1"><flux:input wire:model="guestEmail" type="email" :placeholder="__('planner.wizard.guest_email')" :aria-label="__('planner.wizard.guest_email')" /></div>
                            <flux:button type="button" wire:click="addGuestInvitee" icon="plus" :aria-label="__('planner.wizard.add')" />
                        </div>
                        <p class="text-xs text-zinc-500">{{ __('planner.wizard.guest_help') }}</p>
                        @error('guestName')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                @error('invitees')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-2" data-meeting-invitees>
                    @foreach ($invitees as $index => $invitee)
                        <span wire:key="invitee-{{ $index }}" class="inline-flex items-center gap-2 rounded-full border border-zinc-200 px-3 py-1 text-sm dark:border-zinc-700">
                            <flux:icon :name="isset($invitee['username']) ? 'user' : 'link'" class="size-4 text-zinc-400" />
                            <span dir="auto">{{ $invitee['username'] ?? $invitee['name'] }}</span>
                            @if (! empty($invitee['email']))<span class="text-xs text-zinc-500">{{ $invitee['email'] }}</span>@endif
                            <button type="button" wire:click="removeInvitee({{ $index }})" class="hover:text-red-600" aria-label="{{ __('planner.create.remove') }}">×</button>
                        </span>
                    @endforeach
                </div>
                <flux:input wire:model="location" :label="__('planner.wizard.location')" maxlength="300" :placeholder="__('planner.wizard.location_placeholder')" />
            </flux:card>
        @endif

        {{-- 4. Details --}}
        @if ($step === 'details')
            <flux:card class="space-y-5" wire:key="wizard-details">
                <flux:heading size="lg">{{ __('planner.wizard.details_title') }}</flux:heading>

                <div class="space-y-2">
                    <div class="text-sm font-medium">{{ __('planner.labels.title') }}</div>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($labels as $label)
                            <button type="button" wire:click="chooseLabel('{{ $label->uuid }}')" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm {{ $labelUuid === $label->uuid ? 'border-transparent text-white' : 'border-zinc-200 dark:border-zinc-700' }}" style="{{ $labelUuid === $label->uuid ? 'background:'.$label->color : '' }}" data-plan-label="{{ $label->uuid }}">
                                <span class="size-2.5 rounded-full" style="background: {{ $label->color }}"></span>
                                <span dir="auto">{{ $label->name }}</span>
                            </button>
                        @endforeach
                        <div class="flex items-center gap-1">
                            <input type="text" wire:model="newLabelName" wire:keydown.enter.prevent="createLabel" maxlength="60" placeholder="{{ __('planner.labels.new') }}" class="w-36 rounded-full border border-dashed border-zinc-300 bg-transparent px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:border-zinc-700" dir="auto" data-new-label />
                            <flux:button type="button" wire:click="createLabel" size="xs" variant="ghost" icon="plus" :aria-label="__('planner.labels.create')" />
                        </div>
                    </div>
                    @error('newLabelName')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium">{{ __('planner.labels.color') }}</div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="$set('color', '')" class="rounded-full border px-2.5 py-1 text-xs {{ $color === '' ? 'border-zinc-900 dark:border-white' : 'border-zinc-200 dark:border-zinc-700' }}">{{ __('planner.labels.automatic') }}</button>
                        @foreach ($palette as $swatch)
                            <button type="button" wire:click="chooseColor('{{ $swatch }}')" class="size-7 rounded-full ring-offset-2 dark:ring-offset-zinc-900 {{ $color === $swatch ? 'ring-2 ring-zinc-900 dark:ring-white' : '' }}" style="background: {{ $swatch }}" aria-label="{{ $swatch }}"></button>
                        @endforeach
                        <label class="relative size-7 cursor-pointer overflow-hidden rounded-full border border-zinc-300 dark:border-zinc-600" title="{{ __('planner.labels.custom') }}">
                            <input type="color" wire:model.live="color" class="absolute -inset-2 size-12 cursor-pointer opacity-0" aria-label="{{ __('planner.labels.custom') }}">
                            <span class="flex size-full items-center justify-center text-xs">+</span>
                        </label>
                    </div>
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 {{ $important ? 'border-sky-500 bg-sky-50 dark:bg-sky-950' : 'border-zinc-200 dark:border-zinc-700' }}" data-important-toggle>
                    <input type="checkbox" wire:model.live="important" class="mt-1 size-4 rounded border-zinc-300">
                    <span>
                        <span class="block font-medium">{{ __('planner.wizard.important') }}</span>
                        <span class="block text-xs text-zinc-500">{{ __('planner.wizard.important_help') }}</span>
                    </span>
                </label>

                <div class="space-y-2">
                    <div class="text-sm font-medium">{{ __('planner.create.reminders') }}</div>
                    <p class="text-xs text-zinc-500">{{ __('planner.wizard.help_texts.reminders') }}</p>
                    @php $reminders = collect(preg_split('/[\s,;]+/', $reminderOffsets) ?: [])->filter(fn ($value) => $value !== '')->map(fn ($value) => (int) $value)->all(); @endphp
                    <div class="flex flex-wrap gap-2">
                        @foreach ([0, 10, 30, 60, 180, 1440, 4320, 10080] as $minutes)
                            <button type="button" wire:click="toggleReminder({{ $minutes }})" class="rounded-full border px-3 py-1 text-sm {{ in_array($minutes, $reminders, true) ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-200 dark:border-zinc-700' }}" data-reminder="{{ $minutes }}">{{ __('planner.wizard.reminders.'.$minutes) }}</button>
                        @endforeach
                    </div>
                </div>
                <x-app.device-notifications />

                <details class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" @if ($prerequisites !== [] || $expenseEstimates !== []) open @endif>
                    <summary class="cursor-pointer text-sm font-medium">{{ __('planner.wizard.more_options') }}</summary>
                    <div class="mt-4 space-y-5">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <flux:input wire:model="windowBeforeMinutes" type="number" min="0" max="10080" :label="__('planner.create.early_window')" :description="__('planner.wizard.help_texts.early_window')" />
                            <flux:input wire:model="windowAfterMinutes" type="number" min="0" max="10080" :label="__('planner.create.late_window')" :description="__('planner.wizard.help_texts.late_window')" />
                            <flux:input wire:model="timezone" :label="__('planner.create.timezone')" :description="__('planner.wizard.help_texts.timezone')" />
                        </div>

                        <div class="space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium">{{ __('planner.create.readiness') }}</div>
                                    <p class="text-xs text-zinc-500">{{ __('planner.create.readiness_help') }}</p>
                                </div>
                                <flux:button type="button" wire:click="addPrerequisite" size="sm" variant="ghost" icon="plus">{{ __('planner.create.add_prerequisite') }}</flux:button>
                            </div>
                            @foreach ($prerequisites as $index => $prerequisite)
                                <div wire:key="plan-prerequisite-{{ $index }}" class="grid gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                                    <flux:input wire:model="prerequisites.{{ $index }}.title" :label="__('planner.create.prerequisite')" maxlength="240" />
                                    <flux:checkbox wire:model="prerequisites.{{ $index }}.required" :label="__('planner.create.prerequisite_required')" />
                                    <flux:button type="button" wire:click="removePrerequisite({{ $index }})" size="sm" variant="ghost">{{ __('planner.create.remove') }}</flux:button>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium">{{ __('planner.create.expense_estimates') }}</div>
                                    <p class="text-xs text-zinc-500">{{ __('planner.create.expense_estimates_help') }}</p>
                                </div>
                                <flux:button type="button" wire:click="addExpenseEstimate" size="sm" variant="ghost" icon="plus">{{ __('planner.create.add_expense_estimate') }}</flux:button>
                            </div>
                            @foreach ($expenseEstimates as $index => $estimate)
                                <div wire:key="plan-estimate-{{ $index }}" class="grid gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 sm:grid-cols-[minmax(0,1fr)_10rem_9rem_auto] sm:items-end">
                                    <flux:input wire:model="expenseEstimates.{{ $index }}.label" :label="__('planner.create.expense_label')" maxlength="180" />
                                    <flux:input wire:model="expenseEstimates.{{ $index }}.amount" :label="__('planner.create.estimated_amount')" inputmode="decimal" />
                                    <flux:select wire:model="expenseEstimates.{{ $index }}.unit_code" :label="__('planner.create.monetary_unit')">
                                        @foreach ($unitCatalog as $code => $unit)
                                            <option value="{{ $code }}">{{ $code }} · {{ $unit['name'] }}</option>
                                        @endforeach
                                    </flux:select>
                                    <flux:button type="button" wire:click="removeExpenseEstimate({{ $index }})" size="sm" variant="ghost">{{ __('planner.create.remove') }}</flux:button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>
            </flux:card>
        @endif

        {{-- 5. Review --}}
        @if ($step === 'review')
            <flux:card class="space-y-4" wire:key="wizard-review" data-wizard-review>
                <flux:heading size="lg">{{ __('planner.wizard.review_title') }}</flux:heading>
                <div class="flex items-start gap-3 rounded-xl border-s-4 bg-zinc-50 p-4 dark:bg-zinc-900" style="border-color: {{ $previewColor }}">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg text-white" style="background: {{ $previewColor }}"><flux:icon :name="$kinds[$kind]['icon']" class="size-5" /></span>
                    <div class="min-w-0 space-y-1">
                        <div class="text-lg font-semibold" dir="auto">{{ $title }}</div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('planner.kinds.'.$kind.'.name') }} · <span dir="auto">{{ $contextLabel }}</span></div>
                        @if ($kind === 'meeting')
                            <div class="text-sm">{{ trans_choice('planner.meetings.options_count', count($meetingSlots), ['count' => count($meetingSlots)]) }} · {{ trans_choice('planner.meetings.invitee_count', count($invitees), ['count' => count($invitees)]) }}</div>
                        @else
                            <div class="text-sm">
                                <x-app.local-date :value="$startsOn" /> · {{ $startTime }} · {{ trans_choice('planner.wizard.minutes', $durationMinutes, ['count' => $durationMinutes]) }}
                                @if (count($selectedDays) > 1 && ! in_array($frequency, ['weekly', 'daily'], true))
                                    · {{ trans_choice('planner.series.selected_dates', count($selectedDays), ['count' => count($selectedDays)]) }}
                                @else
                                    · {{ __('planner.frequency.'.$frequency) }}
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                @if ($kind === 'meeting')
                    <flux:callout icon="information-circle">{{ __('planner.wizard.meeting_review_help') }}</flux:callout>
                @else
                    <flux:callout variant="warning">{{ __('planner.create.non_authority') }}</flux:callout>
                @endif
            </flux:card>
        @endif

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @if ($step !== $steps[0])
                    <flux:button type="button" wire:click="back" variant="ghost" icon="arrow-left" class="w-full sm:w-auto rtl:[&_svg]:rotate-180" data-wizard-back>{{ __('planner.wizard.back') }}</flux:button>
                @else
                    <flux:button :href="route('planner.index')" variant="ghost" class="w-full sm:w-auto">{{ __('studio.cancel') }}</flux:button>
                @endif
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                @if ($step !== 'review' && in_array($step, ['when', 'details'], true) && $kind !== 'meeting')
                    <flux:button type="button" wire:click="save" variant="ghost" class="w-full sm:w-auto" data-wizard-create-now>{{ __('planner.wizard.create_now') }}</flux:button>
                @endif
                @if ($step === 'review')
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save" data-wizard-create>
                        {{ $kind === 'meeting' ? __('planner.wizard.send_invitations') : __('planner.create.submit') }}
                    </flux:button>
                @else
                    <flux:button type="submit" variant="primary" icon:trailing="arrow-right" class="w-full sm:w-auto rtl:[&_svg]:rotate-180" data-wizard-next>{{ __('planner.wizard.next') }}</flux:button>
                @endif
            </div>
        </div>
    </form>
</section>
