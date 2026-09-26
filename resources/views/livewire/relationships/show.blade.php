<section class="mx-auto max-w-5xl space-y-6">
    <x-app.flash-message />

    @php
        $title = $relationship->title ?: $relationship->purposeConcept->displayLabel();
    @endphp

    <x-app.page-header :title="$title" :description="__('relationships.help')">
        <x-slot:actions>
            @if ($context)
                <flux:button :href="route('contexts.conversation', $context)" variant="ghost">
                    {{ __('collaboration.tabs.conversation') }}
                </flux:button>
                <flux:button :href="route('contexts.timeline', $context)" variant="ghost">
                    {{ __('collaboration.tabs.timeline') }}
                </flux:button>
                @if ($relationship->status === \App\RelationshipStatus::Active)
                    <flux:button :href="route('planner.index', ['context' => $context->uuid])" variant="ghost">
                        {{ __('planner.title') }}
                    </flux:button>
                @endif
                <flux:button :href="route('contexts.contents.index', $context)" variant="ghost">
                    {{ __('collaboration.tabs.content') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-app.page-header>

    @if ($relationship->domainBlueprintVersion)
        <flux:callout>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('journeys.source') }}</div>
                    <div class="mt-1 font-medium">
                        {{ __('journeys.source_version', [
                            'name' => $relationship->domainBlueprintVersion->blueprint->name,
                            'version' => $relationship->domainBlueprintVersion->version,
                        ]) }}
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($relationship->domainBlueprintVersion->capabilities as $capability)
                        <flux:badge color="zinc">{{ __('journeys.capabilities.'.$capability) }}</flux:badge>
                    @endforeach
                </div>
            </div>
        </flux:callout>
    @endif

    @if ($canRespond)
        <flux:callout variant="warning">{{ __('relationships.show.pending_for_you') }}</flux:callout>
    @elseif ($relationship->status === \App\RelationshipStatus::Proposed)
        <flux:callout>{{ __('relationships.show.pending_for_others') }}</flux:callout>
    @elseif (in_array($relationship->status, [\App\RelationshipStatus::Ended, \App\RelationshipStatus::Cancelled], true))
        <flux:callout>{{ __('relationships.show.read_only_history') }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <div>
                <flux:heading size="lg">{{ __('relationships.show.participants') }}</flux:heading>
            </div>

            <div class="space-y-3">
                @foreach ($relationship->participants as $item)
                    <div wire:key="participant-{{ $item->id }}" class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                        <x-app.actor-identity :actor="$item->actor" size="sm" />
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span dir="auto">{{ $item->role }}</span>
                            <flux:badge color="zinc">{{ __('relationships.participant_status.'.$item->status->value) }}</flux:badge>
                            @if ($item->can_manage)
                                <flux:badge>{{ __('relationships.manager') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($canRespond)
                <div class="flex flex-col gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800 sm:flex-row">
                    <flux:button wire:click="accept" variant="primary" wire:loading.attr="disabled" wire:target="accept">
                        {{ __('relationships.show.accept') }}
                    </flux:button>
                    <flux:button wire:click="decline" variant="danger" wire:loading.attr="disabled" wire:target="decline">
                        {{ __('relationships.show.decline') }}
                    </flux:button>
                </div>
            @elseif ($canCancel)
                <div class="border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <flux:button wire:click="cancel" variant="danger" wire:loading.attr="disabled" wire:target="cancel">
                        {{ __('relationships.show.cancel') }}
                    </flux:button>
                </div>
            @elseif ($canEnd)
                <div class="border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <flux:button wire:click="end" variant="danger" wire:loading.attr="disabled" wire:target="end">
                        {{ __('relationships.show.end') }}
                    </flux:button>
                </div>
            @endif
        </flux:card>

        <div class="space-y-6">
            <flux:card class="space-y-3">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('relationships.show.purpose') }}</div>
                    <div class="mt-1 font-semibold" dir="auto">{{ $relationship->purposeConcept->displayLabel() }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('relationships.filter.status') }}</div>
                    <div class="mt-1"><flux:badge>{{ __('relationships.status.'.$relationship->status->value) }}</flux:badge></div>
                </div>
                @if ($relationship->originatingIntent)
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('relationships.show.origin') }}</div>
                        <a href="{{ route('intents.index') }}#intent-{{ $relationship->originatingIntent->uuid }}" class="mt-1 block font-medium hover:underline">
                            INT-{{ str_pad((string) $relationship->originatingIntent->id, 6, '0', STR_PAD_LEFT) }}
                            · {{ $relationship->originatingIntent->title ?: $relationship->purposeConcept->displayLabel() }}
                        </a>
                    </div>
                @endif
            </flux:card>

            @if ($context)
                <flux:card class="space-y-3">
                    <div>
                        <flux:heading>{{ __('relationships.show.workspace') }}</flux:heading>
                        <flux:text>{{ __('relationships.show.workspace_help') }}</flux:text>
                    </div>
                    <div class="grid gap-2">
                        <flux:button :href="route('contexts.conversation', $context)" variant="primary" class="w-full">
                            {{ __('collaboration.tabs.conversation') }}
                        </flux:button>
                        <flux:button :href="route('contexts.timeline', $context)" variant="ghost" class="w-full">
                            {{ __('collaboration.tabs.timeline') }}
                        </flux:button>
                        @if ($relationship->status === \App\RelationshipStatus::Active)
                            <flux:button :href="route('proposals.create', ['relationship' => $relationship->uuid])" variant="ghost" class="w-full">
                                {{ __('proposals.relationship_action') }}
                            </flux:button>
                            <flux:button :href="route('contracts.create', ['relationship' => $relationship->uuid])" variant="ghost" class="w-full">
                                {{ __('contracts.relationship_action') }}
                            </flux:button>
                            <flux:button :href="route('planner.index', ['context' => $context->uuid])" variant="ghost" class="w-full">
                                {{ __('planner.title') }}
                            </flux:button>
                        @endif
                        <flux:button :href="route('contexts.contents.index', $context)" variant="ghost" class="w-full">
                            {{ __('collaboration.tabs.content') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        </div>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('relationships.show.lifecycle') }}</flux:heading>
        <ol class="space-y-3">
            @foreach ($relationship->events as $event)
                <li wire:key="relationship-event-{{ $event->id }}" class="flex gap-3 text-sm">
                    <div class="mt-1 h-2 w-2 shrink-0 rounded-full bg-zinc-400"></div>
                    <div class="min-w-0">
                        <div class="font-medium">{{ __('relationships.events.'.$event->event_type->value) }}</div>
                        <div class="text-xs text-zinc-500">
                            @if ($event->actor)
                                {{ $event->actor->user?->username ?? __('relationships.unknown_actor') }} ·
                            @endif
                            @if ($event->created_at)<x-app.local-datetime :value="$event->created_at" />@endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </flux:card>

    <flux:callout>{{ __('relationships.show.no_contract') }}</flux:callout>
</section>
