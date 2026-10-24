<div class="mx-auto max-w-4xl space-y-6" data-day-texts>
    <x-app.page-header :title="__('time_bar.admin.title')" :description="__('time_bar.admin.description')">
        <x-slot:actions>
            @unless ($formOpen)
                <flux:button variant="primary" icon="plus" wire:click="create" data-day-text-add>{{ __('time_bar.admin.add') }}</flux:button>
            @endunless
        </x-slot:actions>
    </x-app.page-header>

    @if ($status)
        <flux:callout variant="success" icon="check-circle" wire:key="status-{{ md5($status.microtime()) }}">{{ $status }}</flux:callout>
    @endif

    <flux:card class="space-y-4" data-day-text-today>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <flux:heading size="lg">{{ __('time_bar.admin.today') }}</flux:heading>
                <flux:text class="text-sm">{{ __('time_bar.admin.how') }}</flux:text>
            </div>
        </div>
        @if ($today === [])
            <flux:text class="rounded-lg border border-dashed border-zinc-300 p-3 text-sm dark:border-zinc-700">{{ __('time_bar.admin.today_empty') }}</flux:text>
        @else
            <ul class="space-y-2">
                @foreach ($today as $item)
                    <li class="flex min-w-0 flex-wrap items-baseline gap-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800/60">
                        <flux:badge size="sm" :color="$item['kind'] === 'news' ? 'sky' : ($item['kind'] === 'info' ? 'emerald' : 'indigo')">{{ __('time_bar.kinds.'.$item['kind']) }}</flux:badge>
                        <span class="min-w-0 [overflow-wrap:anywhere]" dir="auto">{{ $item['text'] }}</span>
                        @if ($item['attribution'])<span class="text-zinc-500" dir="auto">— {{ $item['attribution'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
        <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
            <input type="checkbox" wire:model.live="showDefault" class="mt-1 size-4 rounded" data-day-text-show-default>
            <span class="min-w-0">
                <span class="block text-sm font-medium">{{ __('time_bar.admin.show_default') }}</span>
                <span class="block text-sm text-zinc-500 [overflow-wrap:anywhere]" dir="auto">{{ __('time_bar.admin.default_label') }}: {{ $defaultText }}</span>
            </span>
        </label>
    </flux:card>

    @if ($formOpen)
        <flux:card class="space-y-4" data-day-text-form>
            <flux:heading size="lg">{{ $editing ? __('time_bar.admin.edit') : __('time_bar.admin.add') }}</flux:heading>
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ __('time_bar.admin.fields.kind') }}">
                    @foreach (\App\Models\DayText::KINDS as $option)
                        <label @class(['flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-3 text-sm', 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' => $kind === $option, 'border-zinc-200 dark:border-zinc-700' => $kind !== $option])>
                            <input type="radio" wire:model.live="kind" value="{{ $option }}">
                            <span>{{ __('time_bar.kinds.'.$option) }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="space-y-3">
                    <flux:text class="text-sm">{{ __('time_bar.admin.fields.text_help') }}</flux:text>
                    @foreach ($languages as $code => $language)
                        <label class="block min-w-0">
                            <span class="mb-1.5 block text-sm font-medium">{{ __('time_bar.admin.fields.text', ['language' => $language['native_name']]) }}</span>
                            <textarea wire:model="texts.{{ $code }}" rows="2" maxlength="500" lang="{{ str_replace('_', '-', $code) }}" dir="{{ $language['direction'] }}"
                                class="block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base shadow-xs focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-200 sm:text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:focus:ring-zinc-700"
                                data-day-text-locale="{{ $code }}"></textarea>
                            @error('texts.'.$code)<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                    @error('texts')<span class="block text-sm text-red-600" data-day-text-error>{{ $message }}</span>@enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="attribution" maxlength="160" :label="__('time_bar.admin.fields.attribution')" class="[&_input]:text-base sm:[&_input]:text-sm" />
                    <div>
                        <flux:input wire:model="link" type="url" inputmode="url" dir="ltr" maxlength="500" :label="__('time_bar.admin.fields.link')" class="[&_input]:text-base sm:[&_input]:text-sm" />
                        <flux:text class="mt-1 text-xs">{{ __('time_bar.admin.fields.link_help') }}</flux:text>
                    </div>
                    <div>
                        <x-app.calendar-date-input model="startsOn" :label="__('time_bar.admin.fields.starts_on')" />
                        @error('startsOn')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <x-app.calendar-date-input model="endsOn" :label="__('time_bar.admin.fields.ends_on')" />
                        @error('endsOn')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </div>
                </div>

                <label class="flex min-h-11 cursor-pointer items-center gap-3">
                    <input type="checkbox" wire:model="active" class="size-4 rounded">
                    <span class="text-sm font-medium">{{ __('time_bar.admin.fields.active') }}</span>
                </label>

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" data-day-text-save>{{ __('time_bar.admin.save') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancel">{{ __('time_bar.admin.cancel') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    <div class="space-y-3" data-day-text-list>
        @forelse ($entries as $entry)
            <flux:card @class(['space-y-3', 'opacity-60' => ! $entry->active]) wire:key="day-text-{{ $entry->uuid }}" data-day-text="{{ $entry->uuid }}">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" :color="$entry->kind === 'news' ? 'sky' : ($entry->kind === 'info' ? 'emerald' : 'indigo')">{{ __('time_bar.kinds.'.$entry->kind) }}</flux:badge>
                    <flux:badge size="sm" color="zinc">
                        @if (! $entry->isScheduled())
                            {{ __('time_bar.admin.rotation') }}
                        @elseif ($entry->starts_on && $entry->ends_on)
                            {{ __('time_bar.admin.range', ['from' => \App\Support\TemporalCalendar::dateLabel($entry->starts_on, auth()->user(), 'UTC'), 'to' => \App\Support\TemporalCalendar::dateLabel($entry->ends_on, auth()->user(), 'UTC')]) }}
                        @elseif ($entry->starts_on)
                            {{ __('time_bar.admin.from', ['from' => \App\Support\TemporalCalendar::dateLabel($entry->starts_on, auth()->user(), 'UTC')]) }}
                        @else
                            {{ __('time_bar.admin.until', ['to' => \App\Support\TemporalCalendar::dateLabel($entry->ends_on, auth()->user(), 'UTC')]) }}
                        @endif
                    </flux:badge>
                    @unless ($entry->active)<flux:badge size="sm" color="amber">{{ __('time_bar.admin.inactive') }}</flux:badge>@endunless
                    <span class="text-xs text-zinc-500">{{ __('time_bar.admin.languages', ['list' => collect(array_keys($entry->texts ?? []))->map(fn ($code) => $languages[$code]['native_name'] ?? $code)->implode(' · ')]) }}</span>
                </div>
                <p class="text-sm [overflow-wrap:anywhere]" dir="auto">{{ $entry->textFor($locale) }} @if ($entry->attribution)<span class="text-zinc-500">— {{ $entry->attribution }}</span>@endif</p>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $entry->uuid }}')">{{ __('time_bar.admin.edit') }}</flux:button>
                    <flux:button size="sm" variant="ghost" :icon="$entry->active ? 'eye-slash' : 'eye'" wire:click="toggle('{{ $entry->uuid }}')">{{ $entry->active ? __('time_bar.admin.hide') : __('time_bar.admin.show') }}</flux:button>
                    <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="move('{{ $entry->uuid }}', -1)" :disabled="$loop->first" :aria-label="__('time_bar.admin.move_up')" />
                    <flux:button size="sm" variant="ghost" icon="arrow-down" wire:click="move('{{ $entry->uuid }}', 1)" :disabled="$loop->last" :aria-label="__('time_bar.admin.move_down')" />
                    <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600" wire:click="delete('{{ $entry->uuid }}')" wire:confirm="{{ __('time_bar.admin.delete_confirm') }}">{{ __('time_bar.admin.delete') }}</flux:button>
                </div>
            </flux:card>
        @empty
            <x-app.empty-state icon="megaphone" :title="__('time_bar.admin.empty')" />
        @endforelse
    </div>
</div>
