<section class="mx-auto max-w-6xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('relationships.title')" :description="__('relationships.help')">
        <x-slot:actions>
            <flux:button :href="route('relationships.create')" variant="primary" icon="plus">
                {{ __('relationships.new') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:card>
        <div class="max-w-xs">
            <flux:select wire:model.live="status" :label="__('relationships.filter.status')">
                <option value="all">{{ __('relationships.filter.all') }}</option>
                @foreach (['proposed', 'active', 'ended', 'cancelled'] as $option)
                    <option value="{{ $option }}">{{ __('relationships.status.'.$option) }}</option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($relationships as $relationship)
            @php
                $me = $relationship->participants->firstWhere('actor_id', request()->user()?->actor?->id);
                $others = $relationship->participants->where('actor_id', '!=', request()->user()?->actor?->id);
                $title = $relationship->title ?: $relationship->purposeConcept->displayLabel();
            @endphp

            <article wire:key="relationship-{{ $relationship->uuid }}" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap gap-2">
                            <flux:badge>{{ __('relationships.status.'.$relationship->status->value) }}</flux:badge>
                            @if ($me)
                                <flux:badge color="zinc">{{ __('relationships.participant_status.'.$me->status->value) }}</flux:badge>
                            @endif
                        </div>
                        <a href="{{ route('relationships.show', $relationship) }}" class="mt-3 block break-words text-lg font-semibold hover:underline" dir="auto">
                            {{ $title }}
                        </a>
                        <div class="mt-1 text-sm text-zinc-500" dir="auto">{{ $relationship->purposeConcept->displayLabel() }}</div>
                    </div>
                    <span class="font-mono text-xs text-zinc-400">REL-{{ str_pad((string) $relationship->id, 6, '0', STR_PAD_LEFT) }}</span>
                </div>

                <div class="mt-4 space-y-2">
                    @foreach ($others as $other)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <x-app.actor-identity :actor="$other->actor" size="xs" />
                            <span class="text-zinc-500" dir="auto">{{ $other->role }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($relationship->originatingIntent)
                    <div class="mt-4 text-xs text-zinc-500">
                        {{ __('relationships.show.origin') }}:
                        <a href="{{ route('intents.index') }}#intent-{{ $relationship->originatingIntent->uuid }}" class="font-medium hover:underline">
                            INT-{{ str_pad((string) $relationship->originatingIntent->id, 6, '0', STR_PAD_LEFT) }}
                        </a>
                    </div>
                @endif

                <div class="mt-5 flex justify-end">
                    <flux:button :href="route('relationships.show', $relationship)" size="sm" variant="ghost">
                        {{ __('relationships.open') }}
                    </flux:button>
                </div>
            </article>
        @empty
            <div class="lg:col-span-2">
                <x-app.empty-state :title="__('relationships.none')" :description="__('relationships.none_help')" />
            </div>
        @endforelse
    </div>
</section>
