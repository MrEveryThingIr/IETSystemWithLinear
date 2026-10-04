@extends('layouts.app')
@section('title', __('publication.user_title'))

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
        @if(session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 font-semibold text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <a class="text-sm font-bold text-indigo-600" href="{{ route('platform.publication.index') }}">
                ← {{ __('publication.all_users') }}
            </a>
            <h1 class="mt-2 text-3xl font-black">{{ $subject->username ?: $subject->email }}</h1>
            <div class="mt-1 text-zinc-500" dir="ltr">{{ $subject->email }}</div>
        </div>

        <form method="POST" action="{{ route('platform.publication.update', $subject) }}">
            @csrf
            @method('PUT')

            <div class="grid gap-5">
                @foreach($groups as $group => $surfaces)
                    <section class="rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <h2 class="mb-4 text-xl font-black">
                            {{ __("publication.groups.{$group}") }}
                        </h2>

                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach($surfaces as $surface)
                                <label class="flex cursor-pointer gap-3 rounded-2xl border border-zinc-200 p-4 hover:border-indigo-300 dark:border-zinc-700">
                                    <input
                                        class="mt-1 h-5 w-5 rounded"
                                        type="checkbox"
                                        name="surfaces[]"
                                        value="{{ $surface['key'] }}"
                                        data-key="{{ $surface['key'] }}"
                                        data-deps='@json($surface["dependencies"])'
                                        @checked(in_array($surface['key'], $granted, true))
                                    >
                                    <span>
                                        <strong class="text-base">
                                            {{ __("experience.surfaces.{$surface['key']}") }}
                                        </strong>

                                        @if($surface['dependencies'])
                                            <span class="mt-2 block text-xs font-semibold text-violet-600">
                                                {{ __('publication.also_reveals') }}:
                                                {{ collect($surface['dependencies'])->map(fn ($dependency) => __("experience.surfaces.{$dependency}"))->implode(' · ') }}
                                            </span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="sticky bottom-3 mt-5 rounded-2xl border border-zinc-200 bg-white/95 p-4 shadow-xl backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
                <button class="rounded-xl bg-indigo-600 px-5 py-3 text-base font-black text-white hover:bg-indigo-700">
                    {{ __('publication.save') }}
                </button>
            </div>
        </form>
    </div>

    <script>
        document.querySelectorAll('input[data-deps]').forEach((box) => {
            box.addEventListener('change', () => {
                if (!box.checked) return;

                const enable = (key) => {
                    const dependency = document.querySelector(`input[data-key="${key}"]`);
                    if (!dependency) return;

                    dependency.checked = true;
                    JSON.parse(dependency.dataset.deps || '[]').forEach(enable);
                };

                JSON.parse(box.dataset.deps || '[]').forEach(enable);
            });
        });
    </script>
@endsection
