<section class="mx-auto max-w-4xl space-y-6">
    <x-app.page-header :title="__('planner.create.title')" :description="__('planner.create.help')" />

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

    <flux:callout>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="font-medium">{{ __('planner.create.context') }}</div>
                <div class="mt-1" dir="auto">{{ $contextLabel }}</div>
                <div class="mt-1 text-sm text-zinc-500">{{ __('planner.context.help') }}</div>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('contexts.contents.index', $context)" size="sm" variant="ghost">
                    {{ __('planner.context.content') }}
                </flux:button>
                <flux:button :href="route('planner.index', ['view' => 'calendar', 'context' => $context->uuid, 'date' => $startsOn])" size="sm" variant="ghost">
                    {{ __('planner.create.back_to_calendar') }}
                </flux:button>
            </div>
        </div>
    </flux:callout>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('planner.create.activity') }}</flux:heading>

            <flux:input wire:model="title" :label="__('planner.create.activity_title')" maxlength="180" />
            <flux:textarea wire:model="description" :label="__('planner.create.description')" rows="4" maxlength="10000" />
            <flux:input wire:model="timezone" :label="__('planner.create.timezone')" />
        </flux:card>

        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('planner.create.schedule') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model.live="frequency" :label="__('planner.create.frequency')">
                    @foreach (['once', 'daily', 'weekly', 'selected_dates'] as $value)
                        <option value="{{ $value }}">{{ __('planner.frequency.'.$value) }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="startsOn" type="date" :label="__('planner.create.starts_on')" />
                <flux:input wire:model="startTime" type="time" :label="__('planner.create.start_time')" />
                <flux:input wire:model="durationMinutes" type="number" min="1" max="10080" :label="__('planner.create.duration')" />
                <flux:input wire:model="interval" type="number" min="1" max="365" :label="__('planner.create.interval')" />

                @if (in_array($frequency, ['daily', 'weekly'], true))
                    <flux:input wire:model="endsOn" type="date" :label="__('planner.create.ends_on')" />
                    <flux:input wire:model="occurrenceLimit" type="number" min="1" max="10000" :label="__('planner.create.occurrence_limit')" />
                @endif
            </div>

            @if ($frequency === 'weekly')
                <div>
                    <div class="text-sm font-medium">{{ __('planner.create.weekdays') }}</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($weekdayOrder as $weekday)
                            <label class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                                <input type="checkbox" wire:model="weekdays" value="{{ $weekday }}">
                                <span>{{ __('planner.weekdays.'.$weekday) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($frequency === 'selected_dates')
                <div>
                    <flux:textarea wire:model="selectedDates" :label="__('planner.create.selected_dates')" rows="3" />
                    <p class="mt-1 text-xs text-zinc-500">{{ __('planner.create.selected_dates_help') }}</p>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model="windowBeforeMinutes" type="number" min="0" max="10080" :label="__('planner.create.early_window')" />
                    <p class="mt-1 text-xs text-zinc-500">{{ __('planner.create.early_window_help') }}</p>
                </div>
                <div>
                    <flux:input wire:model="windowAfterMinutes" type="number" min="0" max="10080" :label="__('planner.create.late_window')" />
                    <p class="mt-1 text-xs text-zinc-500">{{ __('planner.create.late_window_help') }}</p>
                </div>
            </div>

            <flux:callout>{{ __('planner.create.execution_help') }}</flux:callout>

            <div>
                <flux:input wire:model="reminderOffsets" :label="__('planner.create.reminders')" />
                <p class="mt-1 text-xs text-zinc-500">{{ __('planner.create.reminders_help') }}</p>
            </div>
        </flux:card>

        <flux:callout variant="warning">{{ __('planner.create.non_authority') }}</flux:callout>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button :href="route('planner.index')" variant="ghost" class="w-full sm:w-auto">
                {{ __('studio.cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                {{ __('planner.create.submit') }}
            </flux:button>
        </div>
    </form>
</section>
