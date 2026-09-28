<section
    class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
    x-data
    x-init="if ($wire.timezoneMode === 'auto') $wire.useBrowserTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)"
>
    <div class="border-b border-zinc-200 bg-zinc-50/80 p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-950/50">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold">{{ __('ui.profile.temporal.title') }}</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('ui.profile.temporal.help') }}</p>
            </div>

            @unless ($editorOpen)
                <flux:button wire:click="openEditor" size="sm" variant="ghost" icon="pencil-square" class="w-full sm:w-auto">
                    {{ __('ui.common.edit') }}
                </flux:button>
            @endunless
        </div>

        <div
            class="mt-4 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
            data-temporal-preview
            data-locale="{{ $intlLocale }}"
            data-calendar="{{ $resolvedCalendar }}"
            data-timezone="{{ $timezone }}"
            data-date-format="{{ $dateFormat }}"
            data-time-format="{{ $timeFormat }}"
            data-show-equivalent="{{ $showGregorianEquivalent ? 'true' : 'false' }}"
            data-equivalent-label="{{ __('ui.profile.temporal.gregorian_equivalent') }}"
        >
            <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('ui.profile.temporal.preview') }}</span>
            <span class="mt-1 block break-words text-base font-semibold" data-temporal-preview-value>{{ __('ui.profile.temporal.preview_loading') }}</span>
            <span class="mt-1 block break-words text-xs text-zinc-500" data-temporal-preview-equivalent hidden></span>
        </div>
    </div>

    @if ($editorOpen)
        <form wire:submit="save" class="grid min-w-0 gap-5 p-5 sm:p-6 lg:grid-cols-2">
            <div class="space-y-2">
                <label for="profile-timezone-mode" class="text-sm font-medium">{{ __('ui.profile.temporal.timezone_mode') }}</label>
                <select
                    id="profile-timezone-mode"
                    wire:model.live="timezoneMode"
                    x-on:change="if ($event.target.value === 'auto') $wire.useBrowserTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)"
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
                >
                    <option value="auto">{{ __('ui.profile.temporal.timezone_modes.auto') }}</option>
                    <option value="fixed">{{ __('ui.profile.temporal.timezone_modes.fixed') }}</option>
                </select>
            </div>

            <div class="space-y-2">
                <label for="profile-calendar" class="text-sm font-medium">{{ __('ui.profile.temporal.calendar') }}</label>
                <select
                    id="profile-calendar"
                    wire:model="calendar"
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
                >
                    <option value="auto">
                        {{ __('ui.profile.temporal.calendar_auto', ['calendar' => __('ui.profile.temporal.calendars.'.$defaultCalendar)]) }}
                    </option>
                    @foreach ($calendarOptions as $option)
                        <option value="{{ $option->value }}">{{ __('ui.profile.temporal.calendars.'.$option->value) }}</option>
                    @endforeach
                </select>
                <p class="text-xs leading-5 text-zinc-500">{{ __('ui.profile.temporal.calendar_help', ['language' => $localeName]) }}</p>
            </div>

            <div class="space-y-2 lg:col-span-2">
                <label for="profile-timezone" class="text-sm font-medium">{{ __('ui.profile.temporal.timezone') }}</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        id="profile-timezone"
                        wire:model="timezone"
                        @readonly($timezoneMode === 'auto')
                        list="profile-timezones"
                        autocomplete="off"
                        class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
                    >
                    <flux:button
                        type="button"
                        variant="ghost"
                        class="w-full shrink-0 sm:w-auto"
                        x-on:click="$wire.useBrowserTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)"
                    >
                        {{ __('ui.profile.temporal.use_device_timezone') }}
                    </flux:button>
                </div>
                <datalist id="profile-timezones">
                    @foreach ($timezones as $zone)
                        <option value="{{ $zone }}"></option>
                    @endforeach
                </datalist>
                @error('timezone')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                <p class="text-xs leading-5 text-zinc-500">{{ __('ui.profile.temporal.timezone_help') }}</p>
            </div>

            <div class="space-y-2">
                <label for="profile-date-format" class="text-sm font-medium">{{ __('ui.profile.temporal.date_format') }}</label>
                <select
                    id="profile-date-format"
                    wire:model="dateFormat"
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
                >
                    <option value="long">{{ __('ui.profile.temporal.date_formats.long') }}</option>
                    <option value="medium">{{ __('ui.profile.temporal.date_formats.medium') }}</option>
                    <option value="numeric">{{ __('ui.profile.temporal.date_formats.numeric') }}</option>
                </select>
            </div>

            <div class="space-y-2">
                <label for="profile-time-format" class="text-sm font-medium">{{ __('ui.profile.temporal.time_format') }}</label>
                <select
                    id="profile-time-format"
                    wire:model="timeFormat"
                    class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
                >
                    <option value="24h">{{ __('ui.profile.temporal.time_formats.24h') }}</option>
                    <option value="12h">{{ __('ui.profile.temporal.time_formats.12h') }}</option>
                </select>
            </div>

            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-950/60">
                <input
                    type="checkbox"
                    wire:model="showGregorianEquivalent"
                    class="mt-1 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-400 dark:border-zinc-700"
                >
                <span>
                    <span class="block text-sm font-medium">{{ __('ui.profile.temporal.show_gregorian_equivalent') }}</span>
                    <span class="mt-1 block text-xs leading-5 text-zinc-500">{{ __('ui.profile.temporal.show_gregorian_equivalent_help') }}</span>
                </span>
            </label>

            <div class="flex flex-col-reverse gap-2 border-t border-zinc-200 pt-4 lg:col-span-2 sm:flex-row sm:justify-end dark:border-zinc-800">
                <flux:button type="button" wire:click="cancelEditor" variant="ghost" class="w-full sm:w-auto">
                    {{ __('ui.common.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" class="w-full sm:w-auto">
                    {{ __('ui.common.save') }}
                </flux:button>
            </div>
        </form>
    @endif
</section>
