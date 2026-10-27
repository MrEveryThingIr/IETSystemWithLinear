<section class="mx-auto max-w-7xl space-y-6" @if($highlight) x-data x-init="requestAnimationFrame(() => document.getElementById('intent-{{ $highlight }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))" @endif>
    <x-app.page-header :title="__('intents.directory.title')" :description="__('intents.directory.help')">
        <x-slot:actions>
            <flux:button :href="route('intents.compose')" variant="primary" icon="plus">{{ __('intents.directory.add') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))<flux:callout variant="success">{{ session('status') }}</flux:callout>@endif

    <div class="flex flex-wrap gap-2">
        @foreach (['all','needs','offers','services','property','capital','collaboration'] as $option)
            <button type="button" wire:click="$set('quick', '{{ $option }}')" aria-pressed="{{ $quick === $option ? 'true' : 'false' }}" class="rounded-full border px-3 py-1.5 text-sm {{ $quick === $option ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 dark:border-zinc-700' }}">
                {{ __('intents.quick.'.$option) }}
            </button>
        @endforeach
    </div>

    <flux:card class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model.live.debounce.300ms="search" :label="__('intents.filters.search')" class="lg:col-span-2" />
            <flux:input wire:model.live.debounce.300ms="location" :label="__('intents.filters.location')" />
            <div>
                <label class="mb-2 block text-sm font-medium">{{ __('intents.filters.kind') }}</label>
                <select wire:model.live="kind" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="all">{{ __('intents.filters.all') }}</option>
                    <option value="need">{{ __('intents.kinds.need') }}</option>
                    <option value="offer">{{ __('intents.kinds.offer') }}</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium">{{ __('intents.filters.subject') }}</label>
                <select wire:model.live="subject" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="all">{{ __('intents.filters.all') }}</option>
                    @foreach (['property','good','service','capital','collaboration','other'] as $option)<option value="{{ $option }}">{{ __('intents.subjects.'.$option) }}</option>@endforeach
                </select>
            </div>
            <div class="min-w-0">
                <label for="intent-area" class="mb-2 block text-sm font-medium">{{ __('intents.directory.area') }}</label>
                <select id="intent-area" wire:model.live="area" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="0">{{ __('intents.directory.all_areas') }}</option>
                    @foreach ($areas as $option)<option value="{{ $option['id'] }}">{{ $option['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="min-w-0">
                <label for="intent-scope" class="mb-2 block text-sm font-medium">{{ __('intents.directory.scope') }}</label>
                <select id="intent-scope" wire:model.live="scope" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="all">{{ __('intents.directory.scope_all') }}</option>
                    <option value="mine">{{ __('intents.directory.scope_mine') }}</option>
                </select>
            </div>
            <div class="min-w-0">
                <label for="intent-detail" class="mb-2 block text-sm font-medium">{{ __('composer.filter_detail') }}</label>
                <select id="intent-detail" wire:model.live="detail" class="block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="">{{ __('composer.filter_any') }}</option>
                    @foreach ($detailCatalog as $key => $spec)<option value="{{ $key }}">{{ \App\Support\Market\IntentDetailCatalog::label($key) }}</option>@endforeach
                </select>
                @if (isset($detailCatalog[$detail]))
                    @if ($detailCatalog[$detail]['filter'] === 'value_number' || ($detailCatalog[$detail]['type'] ?? '') === 'year')
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="detailMin" aria-label="{{ __('composer.filter_min') }}" placeholder="{{ __('composer.filter_min') }}" class="block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                            <input type="text" inputmode="decimal" wire:model.live.debounce.500ms="detailMax" aria-label="{{ __('composer.filter_max') }}" placeholder="{{ __('composer.filter_max') }}" class="block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                        </div>
                    @elseif ($detailCatalog[$detail]['filter'] === 'value_boolean')
                        <select wire:model.live="detailText" aria-label="{{ \App\Support\Market\IntentDetailCatalog::label($detail) }}" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="">{{ __('intents.filters.all') }}</option>
                            <option value="1">{{ __('composer.yes') }}</option>
                        </select>
                    @elseif (($detailCatalog[$detail]['type'] ?? '') === 'choice')
                        <select wire:model.live="detailText" aria-label="{{ \App\Support\Market\IntentDetailCatalog::label($detail) }}" class="mt-2 block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="">{{ __('intents.filters.all') }}</option>
                            @foreach ($detailCatalog[$detail]['options'] as $option)<option value="{{ $option }}">{{ \App\Support\Market\IntentDetailCatalog::optionLabel($option) }}</option>@endforeach
                        </select>
                    @else
                        <input type="text" wire:model.live.debounce.500ms="detailText" aria-label="{{ \App\Support\Market\IntentDetailCatalog::label($detail) }}" placeholder="{{ __('composer.filter_contains') }}" class="mt-2 block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                    @endif
                @endif
            </div>
            <div class="flex items-end">
                <flux:button wire:click="clearFilters" variant="ghost" class="w-full">{{ __('intents.filters.clear') }}</flux:button>
            </div>
        </div>
    </flux:card>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($intents as $intent)
            @php
                $publishedMedia = collect($intent->publishedVersion?->snapshot['media'] ?? [])->where('visibility', 'same')->pluck('uuid')->all();
                $cover = $intent->media->first(fn ($item) => $item->visibility !== 'private' && in_array($item->uuid, $publishedMedia, true)
                    && in_array($item->asset?->mediaKind(), ['image', 'video'], true)
                    && $item->asset?->isPublishable() && $item->asset?->isReadyForPublication());
                $coverUrl = $cover ? route('intents.media.show', [$intent, $cover]) : (isset($listingCovers[(int) $intent->business_listing_version_id]) ? route('intents.media.listing-cover', $intent) : null);
                $coverIsVideo = $cover?->asset?->mediaKind() === 'video';
            @endphp
            <article wire:key="intent-{{ $intent->uuid }}" id="intent-{{ $intent->uuid }}" tabindex="-1" class="min-w-0 rounded-2xl border bg-white p-5 shadow-sm transition dark:bg-zinc-900 {{ $highlight === $intent->uuid ? 'border-amber-500 ring-2 ring-amber-300/60 dark:border-amber-400' : 'border-zinc-200 dark:border-zinc-800' }}">
                @if ($coverUrl)
                    <div class="-mx-5 -mt-5 mb-4 aspect-[16/9] overflow-hidden rounded-t-2xl bg-zinc-100 dark:bg-zinc-800">
                        @if ($coverIsVideo)
                            <video src="{{ $coverUrl }}" class="h-full w-full object-cover" preload="none" controls playsinline></video>
                        @else
                            <img src="{{ $coverUrl }}" alt="{{ $cover?->caption ?? __('intents.directory.photo') }}" loading="lazy" decoding="async" class="h-full w-full object-cover">
                        @endif
                    </div>
                @endif
                <x-market.intention-presentation :presentation="$cards[$intent->id]" density="card" />

                <div class="mt-4 flex flex-col gap-3 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 [overflow-wrap:anywhere]">
                        @if ($intent->publisherBusiness)
                            @if ($intent->publisherBusiness->status === 'active' && $intent->publisherBusiness->visibility === 'public')
                                <a href="{{ route('public.businesses.show', $intent->publisherBusiness) }}" class="inline-block py-1 font-medium hover:underline" dir="auto">{{ $intent->publisherBusiness->name }}</a>
                            @else
                                <span class="font-medium" dir="auto">{{ $intent->publisherBusiness->name }}</span>
                            @endif
                        @elseif ($intent->publisherGroup)
                            <span class="font-medium" dir="auto">{{ $intent->publisherGroup->name }}</span>
                        @elseif (\Illuminate\Support\Facades\Gate::allows('view', $intent->profile))
                            <a href="{{ route('profiles.show', $intent->profile) }}" class="inline-block py-1 font-medium hover:underline">
                                {{ $intent->profile->display_name ?: ($intent->profile->actor->user?->username ?? __('intents.directory.participant')) }}
                            </a>
                        @else
                            <span class="text-zinc-500">{{ __('intents.directory.private_participant') }}</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                    @unless (config('release.profile') === 'office_alpha')
                        @can('update', $intent)
                            <flux:button :href="route('intents.show', $intent)" size="sm" variant="ghost" data-own-view>{{ __('items.offers_needs.view') }}</flux:button>
                            <flux:button :href="route('intents.compose.edit', $intent)" size="sm" variant="primary" data-own-edit>{{ __('composer.edit_post') }}</flux:button>
                            <flux:button :href="route('intents.matches', $intent)" size="sm" variant="ghost">{{ __('items.offers_needs.matches_optional') }}</flux:button>
                        @else
                            <flux:button
                                :href="route('relationships.create', ['intent' => $intent->uuid])"
                                size="sm"
                                variant="ghost"
                            >
                                {{ __('deals.market_response') }}
                            </flux:button>
                        @endcan
                    @endunless
                    </div>
                </div>
            </article>
        @empty
            <div class="lg:col-span-2"><x-app.empty-state :title="__('intents.directory.none')" :description="__('intents.directory.none_help')" /></div>
        @endforelse
    </div>

    <div>{{ $intents->links() }}</div>
</section>
