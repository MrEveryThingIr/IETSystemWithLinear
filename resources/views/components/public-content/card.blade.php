{{-- One public post in a list. $card comes from GuestContentController::cards(). --}}
@props(['card'])

<article class="flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white transition hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900" data-public-card>
    @if ($card['cover'])
        <a href="{{ $card['url'] }}" tabindex="-1" aria-hidden="true" class="block aspect-[16/9] overflow-hidden bg-zinc-100 dark:bg-zinc-800">
            <img src="{{ $card['cover'] }}" alt="" class="size-full object-cover" loading="lazy">
        </a>
    @endif
    <div class="flex flex-1 flex-col gap-2 p-4">
        @if ($card['kind'])
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $card['kind'] }}</div>
        @endif
        <h2 class="text-lg font-semibold leading-snug" dir="auto">
            <a href="{{ $card['url'] }}" class="hover:underline underline-offset-4">{{ $card['title'] !== '' ? $card['title'] : __('ui.content.untitled') }}</a>
        </h2>
        @if ($card['excerpt'] !== '')
            <p class="line-clamp-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300" dir="auto">{{ $card['excerpt'] }}</p>
        @endif
        <div class="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1 pt-2 text-xs text-zinc-500">
            @if ($card['author'])
                <x-app.actor-identity :actor="$card['author']" size="xs" :linked="false" />
            @endif
            @if ($card['published_at'])
                <span aria-hidden="true">·</span>
                <x-app.local-date :value="$card['published_at']" />
            @endif
        </div>
    </div>
</article>
