@props([
    'model',
    'label',
    'calendar' => \App\Support\TemporalPreferences::calendarFor(auth()->user())->value,
    'locale' => \App\Support\Localization::intlLocale(),
    'timezone' => \App\Support\TemporalPreferences::timezoneFor(auth()->user()),
    'firstDay' => \App\Support\Localization::firstDayOfWeek(),
])

<div class="space-y-2">
    <label class="text-sm font-medium">{{ $label }}</label>

    <iet-date-picker
        class="relative block"
        data-locale="{{ $locale }}"
        data-calendar="{{ $calendar }}"
        data-timezone="{{ $timezone }}"
        data-first-day="{{ $firstDay }}"
        data-empty-label="{{ __('ui.profile.temporal.choose_date') }}"
    >
        <input type="hidden" wire:model="{{ $model }}" data-date-value>

        <button
            type="button"
            data-date-trigger
            class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-start text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:focus:ring-zinc-800"
            aria-haspopup="dialog"
            aria-expanded="false"
        >
            <span data-date-display class="min-w-0 truncate">{{ __('ui.profile.temporal.choose_date') }}</span>
            <span aria-hidden="true" class="text-zinc-400">▾</span>
        </button>

        <div
            data-date-popover
            hidden
            role="dialog"
            aria-label="{{ $label }}"
            class="absolute z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-zinc-200 bg-white p-4 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
        >
            <div class="flex items-center justify-between gap-3">
                <button type="button" data-date-prev class="rounded-lg px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('ui.profile.temporal.previous_month') }}">‹</button>
                <strong data-date-title class="text-sm"></strong>
                <button type="button" data-date-next class="rounded-lg px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('ui.profile.temporal.next_month') }}">›</button>
            </div>

            <div data-date-weekdays class="mt-3 grid grid-cols-7 gap-1 text-center text-xs font-medium text-zinc-500"></div>
            <div data-date-days class="mt-1 grid grid-cols-7 gap-1"></div>

            <div class="mt-3 flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
                <button type="button" data-date-clear class="text-sm text-zinc-500 hover:text-zinc-900 dark:hover:text-white">
                    {{ __('ui.profile.temporal.clear_date') }}
                </button>
                <button type="button" data-date-today class="text-sm font-medium">
                    {{ __('ui.profile.temporal.today') }}
                </button>
            </div>
        </div>
    </iet-date-picker>

    @error($model)
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
