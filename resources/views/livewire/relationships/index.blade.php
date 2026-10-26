<section class="mx-auto max-w-6xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('deals.title')" :description="__('deals.help')">
        <x-slot:actions>
            <flux:button :href="route('intents.index')" variant="primary" icon="magnifying-glass">
                {{ __('deals.browse_market') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if ($drafts->isNotEmpty())
        <section class="rounded-2xl border border-sky-200 bg-sky-50 p-5 dark:border-sky-900 dark:bg-sky-950/30" data-agreement-drafts>
            <h2 class="font-semibold">{{ __('deals.drafts.title', ['count' => $drafts->count()]) }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ __('deals.drafts.help') }}</p>
            <ul class="mt-3 divide-y divide-sky-100 dark:divide-sky-900">
                @foreach ($drafts as $deal)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="draft-{{ $deal->id }}">
                        <span class="min-w-0">
                            <span class="block font-medium [overflow-wrap:anywhere]" dir="auto">{{ $deal->metadata['agreement_draft']['draft']['title'] ?? '' ?: ($deal->title ?: __('deals.agreement')) }}</span>
                            <span class="block text-xs text-zinc-500">{{ __('deals.drafts.saved') }} <x-app.local-datetime :value="$deal->metadata['agreement_draft']['at']" /></span>
                        </span>
                        <flux:button :href="route('relationships.show', ['relationship' => $deal, 'tab' => 'agreement', 'resume' => 1])" size="sm" variant="primary" wire:navigate>{{ __('deals.drafts.resume') }}</flux:button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($attention !== [] || $due !== [])
        <div class="grid gap-4 lg:grid-cols-2" data-deals-overview>
            @foreach (['attention' => $attention, 'due' => $due] as $block => $entries)
                @if ($entries !== [])
                    <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" data-deals-{{ $block }}>
                        <h2 class="font-semibold">{{ $block === 'attention' ? __('experience.hubs.work.attention') : __('work.hub.commitments') }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">{{ $block === 'attention' ? __('experience.hubs.work.attention_help') : __('work.hub.commitments_help') }}</p>
                        <ul class="mt-3 divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($entries as $entry)
                                <li>
                                    <a href="{{ $entry['href'] }}" wire:navigate class="block py-2.5 hover:underline">
                                        <span class="block font-medium [overflow-wrap:anywhere]" dir="auto">{{ $entry['title'] }}</span>
                                        <span class="block text-sm text-zinc-500 [overflow-wrap:anywhere]" dir="auto">{{ $entry['meta'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        </div>
    @endif

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
