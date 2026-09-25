<section class="mx-auto max-w-7xl space-y-6">
    <x-app.page-header :title="__('intents.directory.title')" :description="__('intents.directory.help')">
        <x-slot:actions>
            <flux:button :href="route('intents.create')" variant="primary" icon="plus">{{ __('intents.directory.add') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))<flux:callout variant="success">{{ session('status') }}</flux:callout>@endif

    <div class="flex flex-wrap gap-2">
        @foreach (['all','needs','offers','services','property','capital','collaboration'] as $option)
            <button type="button" wire:click="$set('quick', '{{ $option }}')" class="rounded-full border px-3 py-1.5 text-sm {{ $quick === $option ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 dark:border-zinc-700' }}">
                {{ __('intents.quick.'.$option) }}
            </button>
        @endforeach
    </div>

    <flux:card class="space-y-4">
        <div class="grid gap-3 lg:grid-cols-6">
            <flux:input wire:model.live.debounce.300ms="search" :label="__('intents.filters.search')" class="lg:col-span-2" />
            <flux:input wire:model.live.debounce.300ms="location" :label="__('intents.filters.location')" />
            <div>
                <label class="mb-2 block text-sm font-medium">{{ __('intents.filters.kind') }}</label>
                <select wire:model.live="kind" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="all">{{ __('intents.filters.all') }}</option>
                    <option value="need">{{ __('intents.kinds.need') }}</option>
                    <option value="offer">{{ __('intents.kinds.offer') }}</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">{{ __('intents.filters.subject') }}</label>
                <select wire:model.live="subject" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="all">{{ __('intents.filters.all') }}</option>
                    @foreach (['property','good','service','capital','collaboration','other'] as $option)<option value="{{ $option }}">{{ __('intents.subjects.'.$option) }}</option>@endforeach
                </select>
            </div>
            <div class="flex items-end">
                <flux:button wire:click="clearFilters" variant="ghost" class="w-full">{{ __('intents.filters.clear') }}</flux:button>
            </div>
        </div>
    </flux:card>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($intents as $intent)
            <article id="intent-{{ $intent->uuid }}" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap gap-2">
                            <flux:badge :color="$intent->kind->value === 'need' ? 'amber' : 'green'">{{ __('intents.kinds.'.$intent->kind->value) }}</flux:badge>
                            <flux:badge>{{ __('intents.subjects.'.$intent->subject_kind->value) }}</flux:badge>
                            <flux:badge color="zinc">{{ __('intents.arrangements.'.$intent->kind->value.'.'.$intent->arrangement_kind->value) }}</flux:badge>
                        </div>
                        <h2 class="mt-3 text-lg font-semibold" dir="auto">{{ $intent->title ?: $intent->concept->displayLabel() }}</h2>
                        @if ($intent->title)<div class="mt-1 text-sm text-zinc-500" dir="auto">{{ $intent->concept->displayLabel() }}</div>@endif
                    </div>
                    <span class="font-mono text-xs text-zinc-400">INT-{{ str_pad((string) $intent->id, 6, '0', STR_PAD_LEFT) }}</span>
                </div>

                @if ($intent->description)<p class="mt-3 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-200" dir="auto">{{ $intent->description }}</p>@endif

                <dl class="mt-4 grid gap-2 text-sm">
                    @if ($intent->location_text)<div><dt class="inline font-medium">{{ __('intents.fields.location') }}:</dt> <dd class="inline" dir="auto">{{ $intent->location_text }}</dd></div>@endif
                    @if ($intent->cash_min !== null || $intent->cash_max !== null)
                        <div>
                            <dt class="inline font-medium">{{ __('intents.directory.cash') }}:</dt>
                            <dd class="inline">
                                @if ($intent->cash_min !== null && $intent->cash_max !== null){{ $intent->cash_min }}–{{ $intent->cash_max }}
                                @elseif ($intent->cash_min !== null){{ __('intents.directory.from') }} {{ $intent->cash_min }}
                                @else{{ __('intents.directory.up_to') }} {{ $intent->cash_max }}@endif
                                {{ $intent->currency_code }}
                                @if ($intent->cash_basis) · {{ __('intents.cash_basis.'.$intent->cash_basis) }}@endif
                            </dd>
                        </div>
                    @endif
                    <div><dt class="inline font-medium">{{ __('intents.fields.exchange_preference') }}:</dt> <dd class="inline">{{ __('intents.exchange.'.$intent->exchange_preference->value.'.title') }}</dd></div>
                    @if ($intent->exchange_notes)<div><dt class="inline font-medium">{{ __('intents.fields.exchange_notes') }}:</dt> <dd class="inline" dir="auto">{{ $intent->exchange_notes }}</dd></div>@endif
                </dl>

                <div class="mt-4 flex flex-col gap-3 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        @can('view', $intent->profile)
                            <a href="{{ route('profiles.show', $intent->profile) }}" class="font-medium hover:underline">
                                {{ $intent->profile->display_name ?: ($intent->profile->actor->user?->username ?? __('intents.directory.participant')) }}
                            </a>
                        @else
                            <span class="text-zinc-500">{{ __('intents.directory.private_participant') }}</span>
                        @endcan
                    </div>

                    @unless (config('release.profile') === 'office_alpha')
                        @if ((int) $intent->profile->actor_id === (int) request()->user()?->actor?->id)
                            <flux:button
                                :href="route('intents.matches', $intent)"
                                size="sm"
                                variant="ghost"
                            >
                                {{ __('intents.matches.find') }}
                            </flux:button>
                        @else
                            <flux:button
                                :href="route('relationships.create', ['intent' => $intent->uuid])"
                                size="sm"
                                variant="ghost"
                            >
                                {{ __('relationships.from_intent') }}
                            </flux:button>
                        @endif
                    @endunless
                </div>
            </article>
        @empty
            <div class="lg:col-span-2"><x-app.empty-state :title="__('intents.directory.none')" :description="__('intents.directory.none_help')" /></div>
        @endforelse
    </div>

    <flux:text class="text-xs text-zinc-500">{{ __('intents.directory.limit_note') }}</flux:text>
</section>
