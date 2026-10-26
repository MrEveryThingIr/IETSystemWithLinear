<section class="mx-auto max-w-3xl space-y-4" @if ($canTalk || $canRespond) wire:poll.8s @endif>
    <x-app.flash-message />

    @php
        $title = $relationship->title ?: ($origin['title'] ?? $relationship->purposeConcept->displayLabel());
        $ended = in_array($relationship->status, [\App\RelationshipStatus::Ended, \App\RelationshipStatus::Cancelled], true);
        $version = $tc['version'];
        $myId = auth()->user()->actor->id;
        $introAuthor = $relationship->participants->firstWhere('actor_id', $relationship->created_by_actor_id)?->actor;
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

    {{-- Conversation --}}
    <flux:card class="space-y-3 !p-3 sm:!p-4">
        <div class="max-h-[28rem] min-h-[8rem] space-y-3 overflow-y-auto" id="deal-thread">
            @if (! empty($relationship->metadata['intro']['text']) && $introAuthor)
                <div class="flex {{ (int) $introAuthor->id === (int) $myId ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-2xl bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-800">
                        <div class="mb-1 text-xs opacity-70"><x-app.actor-identity :actor="$introAuthor" size="xs" /></div>
                        <div class="whitespace-pre-wrap break-words" dir="auto">{{ $relationship->metadata['intro']['text'] }}</div>
                    </div>
                </div>
            @endif
            @forelse ($messages as $m)
                @if ($m->kind === 'deal')
                    <div wire:key="m-{{ $m->uuid }}" class="flex items-center gap-2 rounded-lg bg-zinc-100 px-3 py-2 text-xs dark:bg-zinc-800">
                        <span class="font-medium">{{ __('deals.timeline.'.($m->meta['type'] ?? 'terms_proposed'), ['version' => $m->meta['version'] ?? '']) }}</span>
                        <span class="text-zinc-500">· <x-app.actor-identity :actor="$m->author" size="xs" /></span>
                    </div>
                @else
                    @php($mine = (int) $m->author_actor_id === (int) $myId)
                    <div wire:key="m-{{ $m->uuid }}" class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%] rounded-2xl px-3 py-2 text-sm {{ $mine ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                            @unless ($mine)<div class="mb-1 text-xs opacity-70"><x-app.actor-identity :actor="$m->author" size="xs" /></div>@endunless
                            <div class="whitespace-pre-wrap break-words" dir="auto">{{ $m->body }}</div>
                            <div class="mt-1 text-[10px] opacity-60">@if ($m->created_at)<x-app.local-datetime :value="$m->created_at" />@endif</div>
                        </div>
                    </div>
                @endif
            @empty
                @if (empty($relationship->metadata['intro']['text']))<p class="py-6 text-center text-sm text-zinc-500">{{ __('deals.no_messages') }}</p>@endif
            @endforelse
        </div>

        @if ($canTalk || $canRespond)
            <form wire:submit="send" class="flex items-end gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                <flux:textarea wire:model="message" rows="2" maxlength="4000" class="flex-1" :placeholder="$canRespond ? __('deals.reply_to_join') : __('deals.write')" />
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="send">{{ __('deals.send') }}</flux:button>
            </form>
        @endif
    </flux:card>

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
        <flux:card class="space-y-3 border-sky-300 dark:border-sky-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading>{{ __('deals.terms_card', ['version' => $version->version]) }}</flux:heading>
                <flux:badge color="{{ $proposalOpen ? 'sky' : ($tc['proposal']->status === \App\ProposalStatus::Accepted ? 'emerald' : 'zinc') }}">{{ __('proposals.status.'.$tc['proposal']->status->value) }}</flux:badge>
            </div>

            <div class="whitespace-pre-wrap break-words rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900" dir="auto">{{ $version->termsRevision->payload['terms'] ?? '' }}</div>

            @if ($tc['diff'] && $tc['diff']['rows'])
                <div class="space-y-1 text-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('deals.what_changed') }}</div>
                    @foreach ($tc['diff']['rows'] as $row)
                        <div class="flex flex-wrap gap-x-2" wire:key="diff-{{ $row['key'] }}">
                            <span class="font-medium">{{ $row['label'] }}</span>
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
                @if ($proposalOpen && $canPropose && ! $editingTerms)
                    <flux:button wire:click="openTerms" size="sm">{{ __('deals.propose_changes') }}</flux:button>
                @endif
                @if ($proposalOpen && $tc['canWithdraw'])
                    <flux:button wire:click="withdrawTerms" size="sm" variant="ghost" wire:confirm="{{ __('deals.withdraw_confirm') }}">{{ __('deals.withdraw') }}</flux:button>
                @endif
                @if (! $proposalOpen && $canPropose && ! $latestContract && ! $editingTerms)
                    <flux:button wire:click="openTerms" size="sm" variant="primary">{{ __('deals.propose_new') }}</flux:button>
                @endif
            </div>
        </flux:card>
    @elseif ($canPropose && ! $editingTerms)
        <div class="flex justify-center">
            <flux:button wire:click="openTerms" variant="primary">{{ __('deals.propose_terms') }}</flux:button>
        </div>
    @endif

    @if ($editingTerms)
        <flux:card class="space-y-3">
            <flux:heading>{{ __('deals.propose_terms') }}</flux:heading>
            <p class="text-sm text-zinc-500">{{ __('deals.propose_help') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input type="text" inputmode="decimal" wire:model="terms.price_min" :label="__('deals.change.price_min')" />
                <flux:input type="text" inputmode="decimal" wire:model="terms.price_max" :label="__('deals.change.price_max')" />
                <flux:input wire:model="terms.currency" :label="__('deals.change.price_currency')" maxlength="5" />
                <flux:input wire:model="terms.basis" :label="__('deals.change.price_basis')" />
                <flux:input wire:model="terms.place" :label="__('deals.terms.place')" class="sm:col-span-2" />
                <x-app.calendar-date-input model="terms.starts_on" :label="__('deals.change.time_starts_on')" />
                <x-app.calendar-date-input model="terms.ends_on" :label="__('deals.change.time_ends_on')" />
                <flux:input type="text" inputmode="decimal" wire:model="terms.quantity" :label="__('deals.change.quantity_value')" />
                <flux:input wire:model="terms.unit" :label="__('deals.change.quantity_unit')" />
            </div>
            <flux:textarea wire:model="terms.notes" rows="3" :label="__('deals.terms.notes')" />
            <flux:input wire:model="changeNote" :label="__('deals.note_optional')" maxlength="1000" />
            <p class="text-xs text-zinc-500">{{ __('deals.propose_note') }}</p>
            <div class="flex gap-2">
                <flux:button wire:click="propose" variant="primary" wire:loading.attr="disabled" wire:target="propose">{{ __('deals.propose_send') }}</flux:button>
                <flux:button wire:click="closeTerms" variant="ghost">{{ __('studio.cancel') }}</flux:button>
            </div>
        </flux:card>
    @endif

    {{-- Details --}}
    <details class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700">
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

            @if ($tc['history']->isNotEmpty())
                <div class="space-y-2">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('deals.earlier_versions') }}</div>
                    @foreach ($tc['history'] as $old)
                        <details wire:key="old-{{ $old->id }}" class="rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                            <summary class="cursor-pointer">{{ __('deals.terms_card', ['version' => $old->version]) }}</summary>
                            <div class="mt-2 whitespace-pre-wrap break-words" dir="auto">{{ $old->termsRevision->payload['terms'] ?? '' }}</div>
                        </details>
                    @endforeach
                </div>
            @endif

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
