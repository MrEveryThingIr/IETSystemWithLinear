@extends('layouts.app')
@section('title', __('changelog.title'))

@section('content')
<section class="mx-auto max-w-3xl space-y-6" data-whats-new>
    <header>
        <h1 class="text-2xl font-semibold">{{ __('changelog.title') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('changelog.help', ['name' => config('app.name'), 'version' => $version]) }}</p>
    </header>

    <ol class="relative space-y-5 border-s border-zinc-200 ps-6 dark:border-zinc-800">
        @foreach ($entries as $entry)
            <li class="relative" data-release="{{ $entry['version'] }}">
                <span class="absolute -start-[1.95rem] top-1.5 size-3 rounded-full border-2 border-white {{ $entry['current'] ? 'bg-emerald-500' : 'bg-zinc-300 dark:bg-zinc-600' }} dark:border-zinc-950" aria-hidden="true"></span>
                <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold" dir="ltr">{{ config('app.name') }} {{ $entry['version'] }}</h2>
                        @if ($entry['current'])
                            <flux:badge size="sm" color="green">{{ __('changelog.current') }}</flux:badge>
                        @endif
                        <span class="text-xs text-zinc-500"><x-app.local-date :value="\Carbon\CarbonImmutable::parse($entry['date'])" /></span>
                    </div>
                    @if ($entry['title'])
                        <p class="mt-1 font-medium text-zinc-700 dark:text-zinc-300">{{ $entry['title'] }}</p>
                    @endif
                    <ul class="mt-3 list-disc space-y-1.5 ps-5 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                        @foreach ($entry['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </article>
            </li>
        @endforeach
    </ol>
</section>
@endsection
