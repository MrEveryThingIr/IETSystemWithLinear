<section class="mx-auto max-w-6xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('proposals.title')" :description="__('proposals.help')">
        <x-slot:actions>
            <flux:button :href="route('proposals.create')" variant="primary" icon="plus">
                {{ __('proposals.create.title') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>{{ __('proposals.boundary') }}</flux:callout>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($proposals as $proposal)
            @php($latest = $proposal->versions->first())
            <a
                href="{{ route('proposals.show', $proposal) }}"
                wire:key="proposal-{{ $proposal->uuid }}"
                class="block rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-semibold" dir="auto">{{ $proposal->title }}</div>
                        <div class="mt-1 text-xs text-zinc-500">
                            PRO-{{ str_pad((string) $proposal->id, 6, '0', STR_PAD_LEFT) }}
                            @if ($latest)
                                · v{{ $latest->version }}
                            @endif
                        </div>
                    </div>
                    <flux:badge>{{ __('proposals.status.'.$proposal->status->value) }}</flux:badge>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($proposal->parties as $party)
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs dark:bg-zinc-800">
                            {{ $party->actor->user?->username ?? __('relationships.unknown_actor') }}
                            · {{ $party->role }}
                        </span>
                    @endforeach
                </div>

                @if ($proposal->relationship)
                    <div class="mt-4 text-xs text-zinc-500">
                        {{ __('proposals.source_relationship') }}:
                        {{ $proposal->relationship->title ?: 'REL-'.str_pad((string) $proposal->relationship->id, 6, '0', STR_PAD_LEFT) }}
                    </div>
                @endif
            </a>
        @empty
            <div class="md:col-span-2">
                <x-app.empty-state :title="__('proposals.empty')" />
            </div>
        @endforelse
    </div>
</section>
