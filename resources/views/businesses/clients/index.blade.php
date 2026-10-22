@extends('layouts.app')
@section('title', __('business.clients.title').' — '.$business->name)

@section('content')
@php
    $item = 'min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50';
    $subform = 'space-y-3 rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-700';
@endphp
<div class="mx-auto max-w-6xl space-y-6" data-business-clients>
    @if (session('status'))<flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>@endif
    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle"><ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>
    @endif

    <div>
        <flux:button :href="route('businesses.show', $business)" variant="ghost" size="sm" icon="arrow-left" class="rtl:[&_svg]:rotate-180">{{ $business->name }}</flux:button>
    </div>
    <x-app.page-header :title="__('business.clients.heading')" :description="__('business.clients.intro')">
        <x-slot:actions>
            <flux:button :href="route('businesses.catalog.index', $business)" variant="ghost">{{ __('business.clients.catalog') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if ($canManage)
        <x-app.panel :title="__('business.clients.add_title')" icon="user-plus">
            <form method="POST" action="{{ route('businesses.clients.store', $business) }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-form.input name="display_name" required maxlength="160" :label="__('business.clients.name')" />
                    <x-form.input name="phone" required dir="ltr" inputmode="tel" :label="__('business.clients.primary_phone')" />
                    <x-form.input name="secondary_phone" dir="ltr" inputmode="tel" :label="__('business.clients.secondary_phone')" />
                    <x-form.input name="source" :label="__('business.clients.source')" :placeholder="__('business.clients.source_placeholder')" />
                    <x-form.input name="city" :label="__('business.clients.city')" />
                    <x-form.input name="district" :label="__('business.clients.district')" />
                </div>
                <x-form.input name="street" :label="__('business.clients.street')" />
                <x-form.textarea name="notes" rows="3" :label="__('business.clients.notes')" />
                <flux:button type="submit" variant="primary">{{ __('business.clients.save') }}</flux:button>
            </form>
        </x-app.panel>
    @endif

    <form method="GET" class="flex flex-wrap items-end gap-2" role="search">
        <div class="min-w-0 flex-1 basis-60">
            <x-form.input name="q" :value="request('q')" :label="__('business.clients.search')" :placeholder="__('business.clients.search_placeholder')" />
        </div>
        <flux:button type="submit" variant="primary" icon="magnifying-glass">{{ __('business.clients.search') }}</flux:button>
        @if (request('q'))<flux:button :href="route('businesses.clients.index', $business)" variant="ghost">{{ __('business.clients.clear') }}</flux:button>@endif
    </form>

    <x-app.panel :title="__('business.clients.directory')" icon="users">
        <x-slot:actions><flux:badge>{{ $clients->total() }}</flux:badge></x-slot:actions>
        <div class="space-y-3">
            @forelse ($clients as $client)
                <article class="flex flex-wrap items-start justify-between gap-4 {{ $item }}" wire:key="client-{{ $client->uuid }}">
                    <div class="min-w-0 flex-1 basis-64">
                        <strong class="text-base [overflow-wrap:anywhere]" dir="auto">{{ $client->display_name }}</strong>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <flux:badge size="sm" color="zinc">{{ $client->status }}</flux:badge>
                            @if ($client->source)<flux:badge size="sm" color="zinc">{{ $client->source }}</flux:badge>@endif
                            @foreach ($client->contactPoints as $point)
                                <flux:badge size="sm" dir="ltr">{{ $point->value }}</flux:badge>
                            @endforeach
                        </div>
                        @if ($client->notes)<p class="mt-2 whitespace-pre-line text-sm text-zinc-600 [overflow-wrap:anywhere] dark:text-zinc-300" dir="auto">{{ $client->notes }}</p>@endif
                        @foreach ($client->addresses as $address)
                            <div class="mt-1 text-sm text-zinc-500" dir="auto">{{ collect([$address->city, $address->district, $address->street])->filter()->join(' · ') }}</div>
                        @endforeach
                    </div>
                    @if ($canManage && $client->status === 'active')
                        <div class="w-full min-w-0 space-y-2 sm:w-auto sm:max-w-sm">
                            @if ($canUseMarket)
                                @php($marketNeed = $marketNeedsByContact->get($client->uuid))
                                @if ($marketNeed)
                                    <flux:button size="sm" variant="primary" :href="route('intents.matches', $marketNeed)">{{ __('business.clients.active_need') }}</flux:button>
                                @else
                                    <details class="group">
                                        <summary class="inline-flex min-h-9 cursor-pointer list-none items-center rounded-lg bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">{{ __('business.clients.publish_need') }}</summary>
                                        <form method="POST" action="{{ route('businesses.clients.needs.store', [$business, $client]) }}" class="mt-2 {{ $subform }}">
                                            @csrf
                                            <x-form.input name="concept_label" required :label="__('business.clients.need_concept')" :placeholder="__('business.clients.need_concept_placeholder')" />
                                            <x-form.input name="title" :label="__('business.clients.need_title')" />
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <x-form.select name="subject_kind" :label="__('business.clients.type')" :options="collect(['service', 'good', 'property', 'other'])->mapWithKeys(fn ($type) => [$type => __('business.clients.types.'.$type)])->all()" />
                                                <x-form.select name="arrangement_kind" :label="__('business.clients.arrangement')" :options="['service' => __('business.clients.types.service'), 'ownership_transfer' => __('business.clients.arrangements.ownership_transfer'), 'temporary_use' => __('business.clients.arrangements.temporary_use'), 'other' => __('business.clients.types.other')]" />
                                            </div>
                                            <x-form.input name="location_text" :label="__('business.clients.location')" />
                                            <x-form.textarea name="description" rows="2" :value="$client->notes" :label="__('business.clients.description')" />
                                            <flux:button type="submit" size="sm" variant="primary">{{ __('business.clients.publish_and_match') }}</flux:button>
                                        </form>
                                    </details>
                                @endif
                            @endif
                            <form method="POST" action="{{ route('businesses.clients.archive', [$business, $client]) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('business.clients.archive_confirm')) }})">
                                @csrf @method('PATCH')
                                <flux:button type="submit" size="sm" variant="danger">{{ __('business.clients.archive') }}</flux:button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <flux:text class="py-6 text-center">{{ __('business.clients.empty') }}</flux:text>
            @endforelse
        </div>
        @if ($clients->hasPages())<div>{{ $clients->links() }}</div>@endif
    </x-app.panel>
</div>
@endsection
