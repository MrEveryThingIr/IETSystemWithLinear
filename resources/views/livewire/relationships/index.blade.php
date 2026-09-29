<section class="mx-auto max-w-6xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('deals.title')" :description="__('deals.help')">
        <x-slot:actions>
            <flux:button :href="route('intents.index')" variant="primary" icon="magnifying-glass">
                {{ __('deals.browse_market') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>
        {{ __('deals.boundary') }}
    </flux:callout>

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
                $stage = $pipeline->stage($relationship);
                $nextAction = $pipeline->nextAction($relationship);
            @endphp

            <article wire:key="deal-{{ $relationship->uuid }}" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap gap-2">
                            <flux:badge :color="$stage === 'closed' ? 'zinc' : 'blue'">
                                {{ __('deals.stages.'.$stage) }}
                            </flux:badge>
                            @if ($me && $relationship->status->value === 'proposed')
                                <flux:badge color="amber">{{ __('relationships.participant_status.'.$me->status->value) }}</flux:badge>
                            @endif
                        </div>
                        <a href="{{ route('relationships.show', $relationship) }}" class="mt-3 block break-words text-lg font-semibold hover:underline" dir="auto">
                            {{ $title }}
                        </a>
                        <div class="mt-1 text-sm text-zinc-500" dir="auto">{{ $relationship->purposeConcept->displayLabel() }}</div>
                    </div>
                    <span class="font-mono text-xs text-zinc-400">DEAL-{{ str_pad((string) $relationship->id, 6, '0', STR_PAD_LEFT) }}</span>
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
                    <div class="mt-4 rounded-xl bg-zinc-50 p-3 text-xs dark:bg-zinc-950">
                        <div class="font-medium text-zinc-500">{{ __('deals.origin') }}</div>
                        <div class="mt-1" dir="auto">
                            {{ $relationship->originatingIntent->title ?: $relationship->purposeConcept->displayLabel() }}
                        </div>
                    </div>
                @endif

                <div class="mt-5 flex items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <div class="text-sm text-zinc-500">
                        {{ __('deals.next') }}:
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('deals.actions.'.$nextAction) }}</span>
                    </div>
                    <flux:button :href="route('relationships.show', $relationship)" size="sm" variant="primary">
                        {{ __('deals.open') }}
                    </flux:button>
                </div>
            </article>
        @empty
            <div class="lg:col-span-2">
                <x-app.empty-state :title="__('deals.none')" :description="__('deals.none_help')" />
                <div class="mt-4 flex justify-center">
                    <flux:button :href="route('intents.index')" variant="primary">
                        {{ __('deals.browse_market') }}
                    </flux:button>
                </div>
            </div>
        @endforelse
    </div>
</section>
