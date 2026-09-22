@extends('layouts.app')

@section('title', __('ui.profile.title'))

@section('content')
    <section class="mx-auto max-w-2xl space-y-6">
        <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-start">
                <x-app.actor-avatar :actor="$actor" size="xl" :alt="$actor->user?->username" />
                <div class="min-w-0">
                    <h1 class="break-words text-2xl font-semibold" dir="auto">{{ $actor->user?->username ?? __('ui.common.unknown_account') }}</h1>
                    <p class="mt-2 text-sm text-zinc-500">{{ __('ui.profile.no_shared_details') }}</p>
                </div>
            </div>
        </div>
    </section>
@endsection
