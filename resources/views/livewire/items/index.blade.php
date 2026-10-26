<div class="mx-auto max-w-4xl space-y-6" data-my-items>
    <x-app.page-header :title="__('items.title')" :description="__('items.help')" />

    <nav class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="{{ __('items.sections_label') }}" data-items-jump>
        @foreach (array_filter(['interests' => true, 'abilities' => true, 'offers-needs' => $market, 'saved' => $market]) as $anchor => $show)
            <a href="#{{ $anchor }}" class="inline-flex min-h-10 shrink-0 items-center rounded-full border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 hover:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                {{ __('items.jump.'.$anchor) }}
            </a>
        @endforeach
    </nav>

    <livewire:items.interests :open-form="$openForm === 'interest'" />

    <livewire:profile.capabilities :embedded="true" :open-form="$openForm === 'ability'" />

    @if ($market)
        <section class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900" id="offers-needs" aria-labelledby="offers-needs-heading" data-items-offers-needs>
            <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex items-center gap-2">
                        <flux:icon name="megaphone" class="size-5 text-zinc-500" />
                        <flux:heading size="lg" level="2" id="offers-needs-heading">{{ __('items.offers_needs.title') }}</flux:heading>
                        <flux:badge size="sm">{{ array_sum($counts) }}</flux:badge>
                    </div>
                    <flux:text size="sm">{{ __('items.offers_needs.help') }}</flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" icon="plus" :href="route('intents.compose', ['side' => 'offer'])" wire:navigate data-items-new-offer>{{ __('items.offers_needs.new_offer') }}</flux:button>
                    <flux:button size="sm" icon="plus" :href="route('intents.compose', ['side' => 'need'])" wire:navigate data-items-new-need>{{ __('items.offers_needs.new_need') }}</flux:button>
                </div>
            </header>

            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('items.offers_needs.filter_label') }}" data-items-filter>
                @foreach (['active', 'draft', 'paused', 'closed'] as $status)
                    <button type="button" wire:click="$set('show', '{{ $status }}')" aria-pressed="{{ $show === $status ? 'true' : 'false' }}" data-items-filter-{{ $status }} class="inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3 text-sm {{ $show === $status ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-zinc-300 text-zinc-700 hover:border-zinc-500 dark:border-zinc-700 dark:text-zinc-200' }}">{{ __('items.offers_needs.filter_'.$status) }} <span class="text-xs opacity-80">{{ $counts[$status] ?? 0 }}</span></button>
                @endforeach
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                @foreach (['offers' => $offers, 'needs' => $needs] as $side => $list)
                    <div class="min-w-0 space-y-2" data-items-{{ $side }}>
                        <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ __('items.offers_needs.'.$side) }}</h3>
                        @forelse ($list as $intent)
                            <article class="rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800" wire:key="intent-{{ $intent->uuid }}" data-items-intent="{{ $intent->uuid }}">
                                <div class="font-semibold [overflow-wrap:anywhere]" dir="auto">{{ $intent->title ?: $intent->concept->displayLabel() }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                    @if ($intent->title)
                                        <span class="text-zinc-500 [overflow-wrap:anywhere]" dir="auto">{{ $intent->concept->displayLabel() }}</span>
                                    @endif
                                    @if ($intent->status->value === 'paused')
                                        <flux:badge size="sm" color="amber">{{ __('items.offers_needs.paused') }}</flux:badge>
                                    @elseif ($intent->status->value === 'draft')
                                        <flux:badge size="sm">{{ __('items.offers_needs.draft') }}</flux:badge>
                                    @elseif ($intent->status->value === 'closed')
                                        <flux:badge size="sm">{{ __('items.offers_needs.closed') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm" data-items-actions>
                                    @if ($intent->status->value === 'draft')
                                        <a href="{{ route('intents.compose.edit', $intent) }}" wire:navigate class="font-medium text-zinc-900 underline-offset-4 hover:underline dark:text-zinc-100" data-items-resume>{{ __('items.offers_needs.resume') }}</a>
                                    @else
                                        <a href="{{ route('intents.show', $intent) }}" class="font-medium text-zinc-900 underline-offset-4 hover:underline dark:text-zinc-100" data-items-view>{{ __('items.offers_needs.view') }}</a>
                                        @if ($intent->status->value !== 'closed')
                                            <a href="{{ route('intents.compose.edit', $intent) }}" wire:navigate class="text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300" data-items-edit>{{ __('items.offers_needs.edit') }}</a>
                                        @endif
                                        @if (($conversations[$intent->id] ?? 0) > 0)
                                            <a href="{{ route('intents.show', $intent) }}#deals" class="text-sky-700 underline-offset-4 hover:underline dark:text-sky-300" data-items-conversations>{{ __('items.offers_needs.conversations', ['count' => $conversations[$intent->id]]) }}</a>
                                        @endif
                                        @if (in_array($intent->status->value, ['active', 'paused'], true))
                                            <a href="{{ route('intents.matches', $intent) }}" wire:navigate class="text-xs text-zinc-500 underline-offset-4 hover:underline">{{ __('items.offers_needs.matches_optional') }}</a>
                                        @endif
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700">
                                {{ $show === 'active' ? __('items.offers_needs.empty_'.$side) : __('items.offers_needs.empty_filter') }}
                            </div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </section>

        <livewire:items.saved-structures />
    @endif
</div>
