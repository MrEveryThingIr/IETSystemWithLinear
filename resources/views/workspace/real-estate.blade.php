@extends('layouts.app')
@section('title', __('business.real_estate_workspace.title'))

@section('content')
<div class="mx-auto max-w-5xl space-y-6" data-real-estate-workspace>
    <x-app.page-header :title="__('business.real_estate_workspace.title')" :description="__('business.real_estate_workspace.help')" />
    @if ($portals->isEmpty())
        <x-app.empty-state icon="home-modern" :title="__('business.real_estate_workspace.empty_title')" :description="__('business.real_estate_workspace.empty_help')" />
    @else
        <section class="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(min(100%,17rem),1fr))]">
            @foreach ($portals as $portal)
                <article class="flex min-w-0 flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:icon.home-modern class="size-7 text-zinc-500" />
                    <flux:heading size="lg" level="2" class="[overflow-wrap:anywhere]">{{ $portal->title }}</flux:heading>
                    <div class="mt-auto"><flux:button :href="route('office.real-estate.index', $portal)" variant="primary" size="sm">{{ __('business.real_estate_workspace.open') }}</flux:button></div>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
