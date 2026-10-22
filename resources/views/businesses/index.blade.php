@extends('layouts.app')
@section('title', __('business.index.title'))

@section('content')
<div class="mx-auto max-w-6xl space-y-6" data-business-index>
    <x-app.page-header :title="__('business.index.title')" :description="__('business.index.intro')">
        <x-slot:actions>
            <flux:button :href="route('businesses.create')" variant="primary" icon="plus">{{ __('business.index.create') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('business.index.filter_label') }}">
        @php($chip = 'inline-flex min-h-9 items-center rounded-full border px-3 py-1.5 text-sm')
        <a class="{{ $chip }} {{ request('kind') ? 'border-zinc-300 dark:border-zinc-700' : 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' }}" href="{{ route('businesses.index') }}" @if (! request('kind')) aria-current="page" @endif>{{ __('business.index.all') }}</a>
        @foreach ($kindLabels as $kind => $label)
            <a class="{{ $chip }} {{ request('kind') === $kind ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 dark:border-zinc-700' }}" href="{{ route('businesses.index', ['kind' => $kind]) }}" @if (request('kind') === $kind) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($businesses->isEmpty())
        <x-app.empty-state :title="__('business.index.empty_title')" :description="__('business.index.empty_help')">
            <x-slot:actions>
                <flux:button :href="route('businesses.create')" variant="primary">{{ __('business.index.empty_cta') }}</flux:button>
            </x-slot:actions>
        </x-app.empty-state>
    @else
        <section class="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(min(100%,20rem),1fr))]">
            @foreach ($businesses as $business)
                <article class="flex min-w-0 flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="min-w-0">
                        <flux:badge size="sm">{{ $kindLabels[$business->kind] ?? $business->kind }}</flux:badge>
                        <flux:heading size="lg" level="2" class="mt-2 [overflow-wrap:anywhere]">{{ $business->name }}</flux:heading>
                        <flux:text class="mt-1 [overflow-wrap:anywhere]">{{ $business->short_intro ?: __('business.index.no_intro') }}</flux:text>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                        @foreach ([['active_members_count', 'team'], ['business_contacts_count', 'clients'], ['listings_count', 'listings'], ['public_intake_portals_count', 'intake_channels']] as [$count, $label])
                            <div class="min-w-0 rounded-lg bg-zinc-50 p-2 text-center dark:bg-zinc-800/60">
                                <dd class="text-lg font-semibold">{{ $business->{$count} }}</dd>
                                <dt class="text-xs text-zinc-500 [overflow-wrap:anywhere]">{{ __('business.index.'.$label) }}</dt>
                            </div>
                        @endforeach
                    </dl>
                    <div class="mt-auto flex flex-wrap gap-2">
                        <flux:button :href="route('businesses.show', $business)" size="sm" variant="primary">{{ __('business.index.open') }}</flux:button>
                        <flux:button :href="route('businesses.catalog.index', $business)" size="sm" variant="ghost">{{ __('business.index.catalog') }}</flux:button>
                        @if ($business->visibility === 'public')
                            <flux:button :href="route('public.businesses.show', ['business' => $business->slug])" target="_blank" rel="noopener" size="sm" variant="ghost">{{ __('business.index.open_website') }}</flux:button>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
