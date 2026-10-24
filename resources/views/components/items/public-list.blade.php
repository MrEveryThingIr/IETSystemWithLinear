@props(['abilities', 'interests', 'labels' => []])

{{-- Abilities and interests as someone else sees them: what the holder chose to show, nothing more. --}}
@if ($abilities->isNotEmpty() || $interests->isNotEmpty())
    <div {{ $attributes->class('grid gap-4 lg:grid-cols-2') }} data-public-items>
        @if ($abilities->isNotEmpty())
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" data-public-abilities>
                <h2 class="font-semibold">{{ __('items.abilities.title') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($abilities as $ability)
                        <li class="rounded-full bg-zinc-100 px-3 py-1.5 text-sm dark:bg-zinc-800" dir="auto">
                            {{ filled($ability->title) ? $ability->title : ($labels[$ability->concept_id] ?? '') }}
                            @if ($ability->level)
                                <span class="text-zinc-500">· {{ __('capabilities.levels.'.$ability->level) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-zinc-500">{{ __('items.abilities.self_declared') }}</p>
            </section>
        @endif
        @if ($interests->isNotEmpty())
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" data-public-interests>
                <h2 class="font-semibold">{{ __('items.interests.title') }}</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($interests as $interest)
                        <li class="rounded-full bg-zinc-100 px-3 py-1.5 text-sm dark:bg-zinc-800" dir="auto">
                            {{ $labels[$interest->concept_id] ?? '' }}
                            <span class="text-zinc-500">· {{ collect($interest->purposes)->map(fn ($purpose) => __('items.interests.purposes.'.$purpose))->implode(', ') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endif
