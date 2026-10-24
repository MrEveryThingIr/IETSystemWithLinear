@extends('layouts.app')
@section('title', __('business.catalog.title').' — '.$business->name)

@section('content')
@php
    $item = 'min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50';
    $categoryOptions = $business->categories->mapWithKeys(fn ($category) => [$category->id => $category->name])->all();
    $sharedLabels = app(\App\Support\Categories\CategoryCatalog::class)->labels($business->categories->pluck('concept_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all());
@endphp
<div class="mx-auto max-w-6xl space-y-6" data-business-catalog>
    @if (session('status'))<flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>@endif
    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle"><ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>
    @endif

    <div>
        <flux:button :href="route('businesses.show', $business)" variant="ghost" size="sm" icon="arrow-left" class="rtl:[&_svg]:rotate-180">{{ $business->name }}</flux:button>
    </div>
    <x-app.page-header :title="__('business.catalog.heading')" :description="__('business.catalog.intro')">
        @if ($canUsePlanner && $business->contextBinding?->context)
            <x-slot:actions>
                <flux:button :href="route('planner.index', ['context' => $business->contextBinding->context->uuid])" variant="ghost">{{ __('business.catalog.routines') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-app.page-header>

    @if ($canManage)
        <div class="grid gap-6 lg:grid-cols-2">
            <x-app.panel :title="__('business.catalog.taxonomy')" :help="__('business.catalog.taxonomy_help')" icon="folder">
                @if ($business->categories->isNotEmpty())
                    <div class="flex flex-wrap gap-2" data-business-categories>
                        @foreach ($business->categories as $category)
                            <flux:badge size="sm" :color="$category->concept_id ? 'indigo' : 'zinc'" class="max-w-full">
                                <span class="min-w-0 [overflow-wrap:anywhere]">{{ $category->name }}</span>
                                @if ($category->concept_id && ($sharedLabels[(int) $category->concept_id] ?? '') !== '')
                                    <span class="ms-1 opacity-75">↔ {{ $sharedLabels[(int) $category->concept_id] }}</span>
                                @endif
                            </flux:badge>
                        @endforeach
                    </div>
                    <flux:text class="text-xs">{{ __('business.catalog.shared_link_help') }}</flux:text>
                @endif
                <form method="POST" action="{{ route('businesses.catalog.categories.store', $business) }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.input name="name" required maxlength="180" :label="__('business.catalog.category_name')" />
                        <x-form.input name="slug" maxlength="180" :label="__('business.catalog.slug_optional')" />
                    </div>
                    <x-form.select name="parent_id" :label="__('business.catalog.parent_category')" :placeholder="__('business.catalog.root')" :options="$categoryOptions" />
                    <flux:button type="submit" icon="plus">{{ __('business.catalog.add_category') }}</flux:button>
                </form>
            </x-app.panel>

            <x-app.panel :title="__('business.catalog.new_listing')" :help="__('business.catalog.new_listing_help')" icon="plus-circle">
                <form method="POST" action="{{ route('businesses.catalog.listings.store', $business) }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-form.select name="listing_type" required :label="__('business.catalog.listing_type')" :options="collect(['good', 'service', 'property', 'other'])->mapWithKeys(fn ($type) => [$type => __('business.catalog.types.'.$type)])->all()" />
                        <x-form.select name="category_id" :label="__('business.catalog.category')" :placeholder="__('business.catalog.no_category')" :options="$categoryOptions" />
                        <x-form.select name="visibility" :label="__('business.catalog.visibility')" :options="collect(['private', 'members', 'public'])->mapWithKeys(fn ($visibility) => [$visibility => __('business.catalog.visibilities.'.$visibility)])->all()" />
                    </div>
                    <x-form.input name="title" required maxlength="220" :label="__('business.catalog.listing_title')" />
                    <x-form.input name="short_description" maxlength="500" :label="__('business.catalog.short_description')" />
                    <x-form.textarea name="description" :label="__('business.catalog.description')" />
                    <flux:button type="submit" variant="primary">{{ __('business.catalog.create_draft') }}</flux:button>
                </form>
            </x-app.panel>
        </div>
    @endif

    <x-app.panel :title="__('business.catalog.listings')" :help="__('business.catalog.versions_help')" icon="rectangle-stack">
        <x-slot:actions><flux:badge>{{ $business->listings->count() }}</flux:badge></x-slot:actions>
        <div class="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(min(100%,18rem),1fr))]">
            @forelse ($business->listings as $listing)
                @php($version = $listing->currentVersion)
                <article class="flex flex-col gap-3 {{ $item }}" wire:key="listing-{{ $listing->uuid }}">
                    <div class="flex flex-wrap gap-1">
                        <flux:badge size="sm">{{ $listing->listing_type }}</flux:badge>
                        <flux:badge size="sm" color="zinc">{{ $listing->status }}</flux:badge>
                        <flux:badge size="sm" color="zinc">{{ $listing->visibility }}</flux:badge>
                        @if ($listing->category)<flux:badge size="sm" color="indigo" class="max-w-full"><span class="min-w-0 [overflow-wrap:anywhere]">{{ $listing->category->name }}</span></flux:badge>@endif
                    </div>
                    <div class="min-w-0">
                        <flux:heading size="lg" level="3" class="[overflow-wrap:anywhere]" dir="auto">{{ $version?->title ?? __('business.catalog.untitled') }}</flux:heading>
                        @if ($version?->short_description)<flux:text class="mt-1 [overflow-wrap:anywhere]" dir="auto">{{ $version->short_description }}</flux:text>@endif
                    </div>

                    @if ($version?->propertyDetails)
                        <div class="flex flex-wrap gap-1">
                            @if ($version->propertyDetails->transaction_mode)<flux:badge size="sm" color="zinc">{{ $version->propertyDetails->transaction_mode }}</flux:badge>@endif
                            @if ($version->propertyDetails->property_class)<flux:badge size="sm" color="zinc">{{ $version->propertyDetails->property_class }}</flux:badge>@endif
                            @if ($version->propertyDetails->construction_area)<flux:badge size="sm" color="zinc">{{ $version->propertyDetails->construction_area }} m²</flux:badge>@endif
                            @if ($version->propertyDetails->bedrooms !== null)<flux:badge size="sm" color="zinc">{{ $version->propertyDetails->bedrooms }} {{ __('business.catalog.bed') }}</flux:badge>@endif
                        </div>
                    @endif

                    @if ($listing->prices->isNotEmpty())
                        <ul class="space-y-1 text-sm">
                            @foreach ($listing->prices->take(4) as $price)
                                <li class="min-w-0 [overflow-wrap:anywhere]">
                                    <strong>{{ $price->price_type }}</strong>:
                                    {{ \App\Support\MoneyAmount::format((int) $price->amount_minor, (int) $price->monetaryUnit->exponent) }} {{ $price->monetaryUnit->code }}
                                    @if ($price->basis) / {{ $price->basis }} @endif
                                    <flux:badge size="sm" color="zinc">{{ $price->visibility }}</flux:badge>
                                    @if ($price->reason)<div class="text-xs text-zinc-500">{{ $price->reason }}</div>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-auto flex flex-wrap gap-2">
                        @if ($canManage)
                            <flux:button size="sm" variant="primary" :href="route('businesses.catalog.listings.edit', [$business, $listing])">{{ __('business.catalog.manage_listing') }}</flux:button>
                        @endif
                        <flux:button size="sm" variant="ghost" :href="route('businesses.catalog.listings.preview', [$business, $listing])">{{ __('business_listing.preview') }}</flux:button>
                    </div>

                    @if ($listing->publishedVersion)
                        <div class="text-sm font-medium text-emerald-700 dark:text-emerald-400">✓ {{ __('business.catalog.published_version') }} #{{ $listing->publishedVersion->version_number }}</div>
                        @php($marketOffer = $marketOffersByListing->get($listing->uuid))
                        @if ($marketOffer && $canUseMarket)
                            <div class="flex flex-wrap items-center gap-2 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                <strong>{{ __('business.catalog.active_market') }}</strong>
                                <a href="{{ route('intents.matches', $marketOffer) }}" class="inline-block py-1 font-semibold underline">{{ __('business.catalog.review_matches') }}</a>
                            </div>
                        @elseif ($canManage && $canUseMarket)
                            <form method="POST" action="{{ route('businesses.catalog.listings.market.store', [$business, $listing]) }}" class="space-y-2 border-t border-dashed border-zinc-300 pt-3 dark:border-zinc-700">
                                @csrf
                                <x-form.input name="concept_label" :label="__('business.catalog.market_concept_optional')" :placeholder="__('business.catalog.market_concept_placeholder')" />
                                <flux:button type="submit" size="sm" variant="primary">{{ __('business.catalog.publish_market') }}</flux:button>
                            </form>
                        @endif
                    @endif
                </article>
            @empty
                <flux:text>{{ __('business.catalog.empty') }}</flux:text>
            @endforelse
        </div>
    </x-app.panel>

    @if ($business->kind === 'real_estate' && $business->publicIntakePortals->isNotEmpty())
        <x-app.panel :title="__('business.catalog.real_estate_channels')" :help="__('business.catalog.real_estate_help')" icon="home-modern">
            @foreach ($business->publicIntakePortals as $portal)
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="ghost" :href="route('office.real-estate.index', ['portal' => $portal->uuid])">{{ __('business.catalog.office_cases') }}</flux:button>
                    @if ($business->visibility === 'public')
                        <flux:button size="sm" variant="ghost" target="_blank" rel="noopener" :href="route('public.businesses.show', ['business' => $business->slug])">{{ __('business.catalog.public_website') }}</flux:button>
                    @endif
                </div>
            @endforeach
        </x-app.panel>
    @endif
</div>
@endsection
