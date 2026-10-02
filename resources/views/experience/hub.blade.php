<section class="mx-auto max-w-7xl space-y-6">
    <x-app.page-header :title="$title" :description="$description" />

    <div class="grid gap-5 lg:grid-cols-3">
        @foreach ($sections as $section)
            <flux:card class="flex min-h-64 flex-col gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <flux:heading size="lg">{{ $section['title'] }}</flux:heading>
                        <flux:text>{{ $section['help'] }}</flux:text>
                    </div>
                    @if (array_key_exists('count', $section))
                        <flux:badge>{{ $section['count'] }}</flux:badge>
                    @endif
                </div>

                @if (! empty($section['items']))
                    <div class="space-y-2">
                        @foreach ($section['items'] as $item)
                            <a
                                href="{{ $item['href'] }}"
                                class="block rounded-xl border border-zinc-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-zinc-800 dark:hover:border-indigo-800 dark:hover:bg-indigo-950/20"
                            >
                                <div class="font-medium" dir="auto">{{ $item['title'] }}</div>
                                @if (! empty($item['meta']))
                                    <div class="mt-1 text-sm text-zinc-500" dir="auto">{{ $item['meta'] }}</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @elseif (! empty($section['empty']))
                    <div class="rounded-xl border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-800">
                        {{ $section['empty'] }}
                    </div>
                @endif

                <div class="mt-auto flex flex-wrap gap-2 pt-2">
                    @if (! empty($section['primary']))
                        <flux:button :href="$section['primary']['href']" variant="primary" size="sm">
                            {{ $section['primary']['label'] }}
                        </flux:button>
                    @endif
                    @if (! empty($section['secondary']))
                        <flux:button :href="$section['secondary']['href']" variant="ghost" size="sm">
                            {{ $section['secondary']['label'] }}
                        </flux:button>
                    @endif
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:callout>
        {{ __('experience.hubs.boundary') }}
    </flux:callout>
</section>
