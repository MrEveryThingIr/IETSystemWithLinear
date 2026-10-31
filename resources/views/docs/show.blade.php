<x-docs.layout :title="$page['title']" :description="$page['summary']" :canonical="route('docs.show', $page['slug'])" :noindex="$inviteOnly">
    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-12" data-docs-page="{{ $page['slug'] }}">
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_16rem]">
            <article class="min-w-0 space-y-6">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                        <a class="underline-offset-4 hover:underline" href="{{ route('docs.index') }}">{{ __('docs.nav.docs') }}</a>
                        <x-docs.status :status="$page['status']" />
                    </div>
                    <h1 class="text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">{{ $page['title'] }}</h1>
                    <p class="text-lg leading-8 text-zinc-600 dark:text-zinc-300">{{ $page['summary'] }}</p>
                    @if ($page['fallback'])
                        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100" data-docs-fallback>{{ __('docs.show.fallback') }}</p>
                    @endif
                </div>

                <div class="doc-body" data-docs-body>{!! $page['html'] !!}</div>

                @if ($page['experts'] !== [])
                    <aside class="space-y-3 rounded-2xl border border-sky-200 bg-sky-50 p-6 dark:border-sky-900 dark:bg-sky-950" data-docs-invite>
                        <h2 class="text-xl font-semibold">{{ __('docs.invite.title') }}</h2>
                        <p class="leading-7">{{ __('docs.invite.body', ['fields' => collect($page['experts'])->map(fn ($f) => __('docs.fields.'.$f))->implode(__('docs.list_separator'))]) }}</p>
                        <flux:button variant="primary" :href="route('docs.contribute', ['topic' => $page['slug'], 'field' => $page['experts'][0]])">{{ __('docs.invite.button') }}</flux:button>
                    </aside>
                @endif

                <nav class="flex flex-wrap justify-between gap-3 border-t border-zinc-200 pt-6 text-sm dark:border-zinc-800" aria-label="{{ __('docs.show.pager') }}">
                    @if ($previous)
                        <a class="underline-offset-4 hover:underline" href="{{ route('docs.show', $previous['slug']) }}" rel="prev">← {{ $previous['title'] }}</a>
                    @else
                        <span></span>
                    @endif
                    @if ($next)
                        <a class="underline-offset-4 hover:underline" href="{{ route('docs.show', $next['slug']) }}" rel="next">{{ $next['title'] }} →</a>
                    @endif
                </nav>
            </article>

            @if ($page['headings'] !== [])
                <aside class="hidden lg:block">
                    <div class="sticky top-6 space-y-2 text-sm">
                        <p class="font-semibold">{{ __('docs.show.on_this_page') }}</p>
                        <ul class="space-y-1.5 text-zinc-600 dark:text-zinc-300">
                            @foreach ($page['headings'] as $heading)
                                <li><a class="hover:underline" href="#{{ $heading['id'] }}">{{ $heading['text'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </aside>
            @endif
        </div>
    </main>
</x-docs.layout>
