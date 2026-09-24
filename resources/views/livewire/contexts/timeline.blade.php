<section class="mx-auto max-w-5xl space-y-6">
    <x-app.page-header :title="__('collaboration.timeline.title')" :description="__('collaboration.timeline.help')">
        <x-slot:actions>
            <flux:button :href="route('contexts.conversation', $context)" variant="ghost">
                {{ __('collaboration.tabs.conversation') }}
            </flux:button>
            <flux:button :href="route('contexts.contents.index', $context)" variant="ghost">
                {{ __('collaboration.tabs.content') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>{{ __('collaboration.timeline.projection_notice') }}</flux:callout>

    <flux:card>
        <ol class="space-y-5">
            @forelse ($entries as $entry)
                <li wire:key="timeline-{{ md5($entry->key) }}" class="relative border-s border-zinc-200 ps-5 dark:border-zinc-700">
                    <span class="absolute -start-1.5 top-2 h-3 w-3 rounded-full bg-zinc-400"></span>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm">{{ __('collaboration.timeline.kinds.'.$entry->kind) }}</flux:badge>
                                <span class="font-semibold" dir="auto">{{ $entry->title }}</span>
                            </div>
                            @if ($entry->summary)
                                <p class="mt-2 whitespace-pre-wrap break-words text-sm text-zinc-600 dark:text-zinc-300" dir="auto">{{ $entry->summary }}</p>
                            @endif
                            @if ($entry->actor)
                                <div class="mt-2"><x-app.actor-identity :actor="$entry->actor" size="xs" /></div>
                            @endif
                        </div>

                        <div class="shrink-0 text-xs text-zinc-500">
                            {{ $entry->occurredAt->format('Y-m-d H:i:s') }}
                        </div>
                    </div>

                    <a href="{{ $entry->url }}" class="mt-2 inline-block text-xs font-medium underline underline-offset-2">
                        {{ __('collaboration.timeline.source') }}
                    </a>
                </li>
            @empty
                <x-app.empty-state :title="__('collaboration.timeline.none')" />
            @endforelse
        </ol>
    </flux:card>
</section>
