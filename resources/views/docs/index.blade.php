<x-docs.layout :title="__('docs.index.title')" :description="__('docs.index.lead')" :canonical="route('docs.index')" :noindex="$inviteOnly">
    <main class="mx-auto w-full max-w-6xl space-y-10 px-4 py-8 sm:px-6 sm:py-12" data-docs-index>
        <section class="space-y-4">
            <h1 class="text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">{{ __('docs.index.title') }}</h1>
            <p class="max-w-3xl text-lg leading-8 text-zinc-600 dark:text-zinc-300">{{ __('docs.index.lead') }}</p>
            <div class="flex flex-wrap gap-2">
                <flux:button variant="primary" :href="route('docs.show', 'welcome')">{{ __('docs.index.start') }}</flux:button>
                <flux:button :href="route('docs.contribute')" data-docs-collaborate>{{ __('docs.index.collaborate') }}</flux:button>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">{{ __('docs.index.chapters') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($pages as $page)
                    <a href="{{ route('docs.show', $page['slug']) }}" class="block space-y-2 rounded-2xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-400 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-600" data-docs-card>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm text-zinc-500">{{ $loop->iteration }}</span>
                            <x-docs.status :status="$page['status']" />
                        </div>
                        <h3 class="text-lg font-semibold leading-snug">{{ $page['title'] }}</h3>
                        <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $page['summary'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="space-y-2 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-xl font-semibold">{{ __('docs.status.legend') }}</h2>
            <ul class="grid gap-2 text-sm text-zinc-600 sm:grid-cols-2 dark:text-zinc-300">
                @foreach (['available', 'partial', 'research', 'planned'] as $status)
                    <li class="flex items-start gap-2"><x-docs.status :status="$status" /><span>{{ __('docs.status.'.$status.'_help') }}</span></li>
                @endforeach
            </ul>
        </section>
    </main>
</x-docs.layout>
