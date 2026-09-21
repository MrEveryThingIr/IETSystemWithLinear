<section class="rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h2 class="text-lg font-semibold">{{ __('ui.profile.temporal.title') }}</h2>
            <p class="mt-1 max-w-3xl text-sm text-zinc-500">{{ __('ui.profile.temporal.help') }}</p>
        </div>

        <div
            class="rounded-xl bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-950/60"
            data-temporal-preview
            data-locale="{{ $intlLocale }}"
            data-calendar="{{ $resolvedCalendar }}"
            data-timezone="{{ $timezone }}"
        >
            <span class="block text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('ui.profile.temporal.preview') }}</span>
            <span class="mt-1 block font-medium" data-temporal-preview-value>{{ __('ui.profile.temporal.preview_loading') }}</span>
        </div>
    </div>

    <form wire:submit="save" class="mt-6 grid gap-5 lg:grid-cols-2">
        <div class="space-y-2">
            <label for="profile-timezone" class="text-sm font-medium">{{ __('ui.profile.temporal.timezone') }}</label>
            <div class="flex gap-2">
                <input
                    id="profile-timezone"
                    wire:model="timezone"
                    list="profile-timezones"
                    autocomplete="off"
                    class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                >
                <flux:button
                    type="button"
                    variant="ghost"
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
            <p class="text-xs text-zinc-500">{{ __('ui.profile.temporal.timezone_help') }}</p>
        </div>

        <div class="space-y-2">
            <label for="profile-calendar" class="text-sm font-medium">{{ __('ui.profile.temporal.calendar') }}</label>
            <select
                id="profile-calendar"
                wire:model="calendar"
                class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
            >
                <option value="auto">
                    {{ __('ui.profile.temporal.calendar_auto', ['calendar' => __('ui.profile.temporal.calendars.'.$defaultCalendar)]) }}
                </option>
                @foreach ($calendarOptions as $option)
                    <option value="{{ $option->value }}">{{ __('ui.profile.temporal.calendars.'.$option->value) }}</option>
                @endforeach
            </select>
            <p class="text-xs text-zinc-500">
                {{ __('ui.profile.temporal.calendar_help', ['language' => $localeName]) }}
            </p>
        </div>

        <div class="lg:col-span-2 flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('ui.common.save') }}</flux:button>
        </div>
    </form>
</section>
