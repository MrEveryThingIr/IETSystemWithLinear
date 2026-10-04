@extends('layouts.app')

@section('title', __('publication.title'))

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 font-semibold text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <section class="overflow-hidden rounded-3xl bg-gradient-to-l from-indigo-700 via-violet-700 to-fuchsia-700 p-7 text-white shadow-xl">
        <div class="text-sm font-bold opacity-90">{{ __('publication.superadmin') }}</div>

        <h1 class="mt-2 text-3xl font-black md:text-4xl">
            {{ __('publication.title') }}
        </h1>

        <p class="mt-3 max-w-3xl text-base leading-8 text-violet-50">
            {{ __('publication.intro') }}
        </p>

        <div class="mt-4 inline-flex rounded-full bg-white/15 px-3 py-1.5 text-sm font-black ring-1 ring-white/25">
            {{ __('publication.strict_on') }}
        </div>
    </section>

    <section class="rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 p-5 dark:border-zinc-700">
            <h2 class="text-xl font-black">
                {{ __('publication.users') }}
            </h2>
        </div>

        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach($users as $user)
                <div class="flex flex-wrap items-center justify-between gap-4 p-5">
                    <div>
                        <div class="text-lg font-extrabold">
                            {{ $user->username ?: $user->email }}
                        </div>
                        <div class="mt-1 text-sm text-zinc-500" dir="ltr">
                            {{ $user->email }}
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-sm font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">
                            {{ $user->feature_surface_grants_count }}
                            {{ __('publication.facilities') }}
                        </span>

                        <a
                            href="{{ route('platform.publication.edit', $user) }}"
                            class="rounded-xl bg-indigo-600 px-4 py-2.5 font-bold text-white hover:bg-indigo-700"
                        >
                            {{ __('publication.configure') }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="p-5">{{ $users->links() }}</div>
    </section>
</div>
@endsection
