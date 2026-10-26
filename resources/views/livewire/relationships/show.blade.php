<section class="mx-auto max-w-3xl space-y-4">
    <x-app.flash-message />

    @php
        $title = $relationship->title ?: ($origin['title'] ?? $relationship->purposeConcept->displayLabel());
        $ended = in_array($relationship->status, [\App\RelationshipStatus::Ended, \App\RelationshipStatus::Cancelled], true);
        $version = $tc['version'];
        $intro = $relationship->metadata['intro']['text'] ?? null;
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
            <h1 class="truncate text-xl font-semibold" dir="auto">{{ $title }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                <flux:badge size="sm">{{ __('relationships.status.'.$relationship->status->value) }}</flux:badge>
                <span>{{ __('deals.stages.'.$dealStage) }}</span>
            </div>
        </div>
        <flux:button :href="route('deals.index')" variant="ghost" size="sm" wire:navigate>{{ __('workflow.back') }}</flux:button>
    </div>

    <x-deals.origin :card="$origin" />

    @if ($canRespond)
        <flux:callout variant="warning">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span>{{ __('deals.invited') }}</span>
                <span class="flex gap-2">
                    <flux:button wire:click="accept" variant="primary" size="sm" wire:loading.attr="disabled" wire:target="accept">{{ __('deals.join') }}</flux:button>
                    <flux:button wire:click="decline" variant="ghost" size="sm" wire:loading.attr="disabled" wire:target="decline">{{ __('relationships.show.decline') }}</flux:button>
                </span>
            </div>
        </flux:callout>
    @elseif ($relationship->status === \App\RelationshipStatus::Proposed)
        <flux:callout>{{ __('relationships.show.pending_for_others') }}</flux:callout>
    @elseif ($ended)
        <flux:callout>{{ __('relationships.show.read_only_history') }}</flux:callout>
    @endif

    {{-- Conversation: the same thread as the Messages inbox (files, voice notes, location, replies, reactions) --}}
    @if ($intro && $introAuthor)
        <div class="flex items-start gap-2 rounded-xl bg-zinc-100 p-3 text-sm dark:bg-zinc-800" data-deal-intro>
            <div class="min-w-0">
                <div class="mb-1 text-xs opacity-70"><x-app.actor-identity :actor="$introAuthor" size="xs" /></div>
                <div class="whitespace-pre-wrap break-words" dir="auto">{{ $intro }}</div>
            </div>
        </div>
    @endif

    @if ($conversation)
        <div class="h-[32rem] overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" data-deal-thread>
            <livewire:messages.thread :conversation="$conversation" :embedded="true" :key="'deal-thread-'.$conversation->uuid" />
        </div>
    @endif

    @error('terms') <flux:callout variant="danger">{{ $message }}</flux:callout> @enderror

    {{-- Agreement --}}
    @if ($latestContract)
        <flux:card class="space-y-2 border-emerald-300 dark:border-emerald-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading>{{ __('deals.agreement') }}</flux:heading>
                <flux:badge color="emerald">{{ __('contracts.status.'.$latestContract->status->value) }}</flux:badge>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('deals.agreement_help') }}</p>
            <flux:button :href="route('contracts.show', $latestContract)" size="sm" variant="primary">{{ __('deals.open_contract') }}</flux:button>
        </flux:card>
    @endif

    {{-- Terms --}}
    @if ($tc['proposal'] && $version)
        @php($proposalOpen = $tc['open'])
        <flux:card class="space-y-3 border-sky-300 dark:border-sky-700" data-terms-card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading>{{ __('deals.terms_card', ['version' => $version->version]) }}</flux:heading>
                <flux:badge color="{{ $proposalOpen ? 'sky' : ($tc['proposal']->status === \App\ProposalStatus::Accepted ? 'emerald' : 'zinc') }}">{{ __('proposals.status.'.$tc['proposal']->status->value) }}</flux:badge>
            </div>

            @if ($tc['rows'])
                <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                    @foreach ($tc['rows'] as $path => $row)
                        <div class="flex flex-wrap gap-x-2" wire:key="row-{{ $path }}"><dt class="text-zinc-500" dir="auto">{{ $row['label'] }}</dt><dd class="font-medium" dir="auto">{{ $row['value'] }}</dd></div>
                    @endforeach
                </dl>
            @else
                <div class="whitespace-pre-wrap break-words rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900" dir="auto">{{ $version->termsRevision->payload['terms'] ?? '' }}</div>
            @endif

            @if ($tc['diff'] && $tc['diff']['rows'])
                <div class="space-y-1 rounded-lg bg-amber-50 p-3 text-sm dark:bg-amber-950/30">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('deals.what_changed') }}</div>
                    @foreach ($tc['diff']['rows'] as $row)
                        <div class="flex flex-wrap gap-x-2" wire:key="diff-{{ $row['key'] }}">
                            <span class="font-medium" dir="auto">{{ $row['label'] }}</span>
                            @if ($row['from'] !== null)<span class="text-zinc-500 line-through" dir="auto">{{ $row['from'] }}</span>@endif
                            @if ($row['to'] !== null)<span dir="auto">→ {{ $row['to'] }}</span>@else<span class="text-zinc-500">{{ __('deals.removed') }}</span>@endif
                        </div>
                    @endforeach
                </div>
            @endif

            <ul class="space-y-1 text-sm">
                @foreach ($tc['parties'] as $row)
                    @php($pp = $row['party'])
                    <li wire:key="tp-{{ $pp->id }}" class="flex flex-wrap items-center justify-between gap-2">
                        <span dir="auto">@if ($pp->business){{ $pp->business->name }}@elseif ($pp->group){{ $pp->group->name }}@else<x-app.actor-identity :actor="$pp->actor" size="xs" />@endif</span>
                        @if ($row['decision'])
                            <flux:badge size="sm" color="{{ $row['decision']->decision === \App\ProposalDecisionKind::Accepted ? 'emerald' : 'amber' }}">{{ __('deals.decision.'.$row['decision']->decision->value) }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('deals.waiting') }}</flux:badge>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($proposalOpen && $tc['canRespond'])
                <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                    <flux:input wire:model="changeNote" :placeholder="__('deals.note_optional')" maxlength="1000" />
                    <div class="flex flex-wrap gap-2">
                        <flux:button wire:click="agree" variant="primary" wire:loading.attr="disabled" wire:target="agree">{{ __('deals.agree', ['version' => $version->version]) }}</flux:button>
                        <flux:button wire:click="requestChanges" wire:loading.attr="disabled" wire:target="requestChanges">{{ __('deals.request_changes') }}</flux:button>
                        <flux:button wire:click="declineTerms" variant="ghost" wire:loading.attr="disabled" wire:target="declineTerms">{{ __('deals.decline_terms') }}</flux:button>
                    </div>
                    <p class="text-xs text-zinc-500">{{ __('deals.agree_note') }}</p>
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                @if ($proposalOpen && $canPropose)
                    <flux:button wire:click="$dispatchTo('deals.terms-composer', 'open-terms')" size="sm">{{ __('deals.propose_changes') }}</flux:button>
                @endif
                @if ($proposalOpen && $tc['canWithdraw'])
                    <flux:button wire:click="withdrawTerms" size="sm" variant="ghost" wire:confirm="{{ __('deals.withdraw_confirm') }}">{{ __('deals.withdraw') }}</flux:button>
                @endif
                @if (! $proposalOpen && $canPropose && ! $latestContract)
                    <flux:button wire:click="$dispatchTo('deals.terms-composer', 'open-terms')" size="sm" variant="primary">{{ __('deals.propose_new') }}</flux:button>
                @endif
            </div>
        </flux:card>
    @elseif ($canPropose)
        <div class="flex justify-center">
            <flux:button wire:click="$dispatchTo('deals.terms-composer', 'open-terms')" variant="primary">{{ __('deals.propose_terms') }}</flux:button>
        </div>
    @endif

    @if ($canPropose)
        <livewire:deals.terms-composer :relationship="$relationship" :key="'terms-composer-'.$relationship->uuid" />
    @endif

    {{-- Details: the open/closed state lives in Alpine, so re-renders of this page never reset it --}}
    <details wire:key="deal-details" x-data="{ open: false }" x-bind:open="open" x-on:toggle="open = $el.open" class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-deal-details>
        <summary class="cursor-pointer font-medium">{{ __('deals.details') }}</summary>
        <div class="mt-3 space-y-4">
            <div class="space-y-2">
                @foreach ($relationship->participants as $item)
                    <div wire:key="participant-{{ $item->id }}" class="flex flex-wrap items-center justify-between gap-2">
                        <x-app.actor-identity :actor="$item->actor" size="sm" />
                        <span class="flex items-center gap-2"><span dir="auto">{{ $item->role }}</span><flux:badge size="sm" color="zinc">{{ __('relationships.participant_status.'.$item->status->value) }}</flux:badge></span>
                    </div>
                @endforeach
            </div>

            @if ($relationship->domainBlueprintVersion)
                <div class="text-xs text-zinc-500">
                    {{ __('journeys.source_version', ['name' => $relationship->domainBlueprintVersion->blueprint->name, 'version' => $relationship->domainBlueprintVersion->version]) }}
                    @foreach ($relationship->domainBlueprintVersion->capabilities as $capability)
                        <flux:badge size="sm" color="zinc">{{ __('journeys.capabilities.'.$capability) }}</flux:badge>
                    @endforeach
                </div>
            @endif

            @if ($relationship->status === \App\RelationshipStatus::Active && ! $tc['open'] && ! $latestContract)
                <flux:button :href="route('proposals.create', ['relationship' => $relationship->uuid])" size="sm" variant="ghost">{{ __('deals.classic_form') }}</flux:button>
            @endif

            @if ($tc['history']->isNotEmpty())
                <div class="space-y-2">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('deals.earlier_versions') }}</div>
                    @foreach ($tc['history'] as $old)
                        <details wire:key="old-{{ $old->id }}" x-data="{ open: false }" x-bind:open="open" x-on:toggle="open = $el.open" class="rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                            <summary class="cursor-pointer">{{ __('deals.terms_card', ['version' => $old->version]) }}</summary>
                            <div class="mt-2 whitespace-pre-wrap break-words" dir="auto">{{ $old->termsRevision->payload['terms'] ?? '' }}</div>
                        </details>
                    @endforeach
                </div>
            @endif

            @if ($tc['proposal'])
                <div class="flex flex-wrap gap-2">
                    <flux:button :href="route('proposals.show', $tc['proposal'])" size="sm" variant="ghost">{{ __('deals.evidence') }}</flux:button>
                </div>
            @endif

            <ol class="space-y-2">
                @foreach ($relationship->events as $event)
                    <li wire:key="relationship-event-{{ $event->id }}" class="flex gap-2 text-xs">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-zinc-400"></span>
                        <span>{{ __('relationships.events.'.$event->event_type->value) }} · @if ($event->created_at)<x-app.local-datetime :value="$event->created_at" />@endif</span>
                    </li>
                @endforeach
            </ol>

            <div class="flex flex-wrap gap-2">
                @if ($context)
                    <flux:button :href="route('contexts.conversation', $context)" size="sm" variant="ghost">{{ __('deals.full_conversation') }}</flux:button>
                    <flux:button :href="route('contexts.contents.index', $context)" size="sm" variant="ghost">{{ __('collaboration.tabs.content') }}</flux:button>
                @endif
                @if ($canCancel)
                    <flux:button wire:click="cancel" size="sm" variant="danger" wire:loading.attr="disabled" wire:target="cancel">{{ __('relationships.show.cancel') }}</flux:button>
                @elseif ($canEnd)
                    <flux:button wire:click="end" size="sm" variant="danger" wire:loading.attr="disabled" wire:target="end">{{ __('relationships.show.end') }}</flux:button>
                @endif
            </div>
        </div>
    </details>
</section>
