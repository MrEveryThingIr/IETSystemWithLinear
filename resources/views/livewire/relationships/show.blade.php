<section class="mx-auto max-w-3xl space-y-4">
    <x-app.flash-message />

    @php
        $title = $relationship->title ?: ($origin['title'] ?? $relationship->purposeConcept->displayLabel());
        $ended = in_array($relationship->status, [\App\RelationshipStatus::Ended, \App\RelationshipStatus::Cancelled], true);
        $version = $tc['version'];
        $intro = $relationship->metadata['intro']['text'] ?? null;
        $terms = $version?->terms_data;
        $partyName = fn ($pp) => $pp->business?->name ?? $pp->group?->name ?? ($pp->actor?->user?->username ?? '');
        $partyNames = $tc['parties']->map(fn ($row) => $partyName($row['party']))->filter()->values()->all();
        $agreed = $latestContract && in_array($latestContract->status, [\App\ContractStatus::Active, \App\ContractStatus::Pending], true) && $tc['proposal']?->status === \App\ProposalStatus::Accepted;
        $needsMe = $tc['open'] && $tc['canRespond'];
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

    @error('terms') <flux:callout variant="danger">{{ $message }}</flux:callout> @enderror

    {{-- One workspace, four lenses. The chosen tab lives in Alpine, so a re-render never changes it. --}}
    <div x-data="dealTabs" data-key="{{ $relationship->uuid }}" data-start="{{ $startTab }}" class="space-y-3" data-deal-tabs>
        <div role="tablist" class="flex border-b border-zinc-200 dark:border-zinc-700">
            @foreach (['conversation', 'agreement', 'work', 'money'] as $name)
                <button type="button" role="tab" x-on:click="tab = '{{ $name }}'" x-bind:aria-selected="tab === '{{ $name }}'" x-bind:class="tab === '{{ $name }}' ? 'border-b-2 border-zinc-900 font-semibold dark:border-zinc-100' : 'text-zinc-500'" class="flex-1 whitespace-nowrap px-1 py-2 text-center text-sm sm:px-3" data-tab="{{ $name }}">
                    {{ __('deals.tabs.'.$name) }}
                    @if ($name === 'agreement' && ($needsMe || ($canDraft && ($hasDraft || $requests))))<span class="ms-1 inline-block size-2 rounded-full bg-sky-500" aria-hidden="true"></span>@endif
                </button>
            @endforeach
        </div>

        {{-- Conversation --}}
        <div x-show="tab === 'conversation'" data-panel="conversation" class="space-y-3">
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

            @if ($relationship->status === \App\RelationshipStatus::Active && ! $agreed && ! $tc['open'])
                <p class="text-center text-sm text-zinc-500">
                    {{ __('deals.agree_in_chat_no') }}
                    <button type="button" class="underline" x-on:click="tab = 'agreement'">{{ __('deals.go_to_agreement') }}</button>
                </p>
            @endif
        </div>

        {{-- Agreement --}}
        <div x-show="tab === 'agreement'" x-cloak data-panel="agreement" class="space-y-3">
            @if ($canDraft && $requests)
                <flux:callout>
                    <div class="space-y-1 text-sm" data-agreement-requests>
                        @foreach ($requests as $r)
                            <div>{{ __('deals.requested_by', ['name' => $r['actor']?->user?->username ?? '']) }}@if ($r['note']) — <span dir="auto">{{ $r['note'] }}</span>@endif</div>
                        @endforeach
                    </div>
                </flux:callout>
            @endif

            @if ($agreed && $version && $terms)
                <flux:card class="space-y-3 border-emerald-300 dark:border-emerald-700" data-agreement-final>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:heading>{{ $terms['title'] ?? __('deals.agreement') }}</flux:heading>
                        <flux:badge color="emerald">{{ __('contracts.status.'.$latestContract->status->value) }}</flux:badge>
                    </div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('deals.agreement_help') }}</p>
                    @if (! empty($terms['summary']))<p class="text-sm" dir="auto">{{ $terms['summary'] }}</p>@endif
                    <x-deals.document :sections="$doc" :parties="$partyNames" />
                    <ul class="space-y-1 border-t border-zinc-200 pt-3 text-xs dark:border-zinc-700" data-agreement-signers>
                        @foreach ($tc['parties'] as $row)
                            <li wire:key="sig-{{ $row['party']->id }}" class="flex flex-wrap justify-between gap-2">
                                <span dir="auto">{{ $partyName($row['party']) }}</span>
                                @if ($row['decision'])<span class="text-zinc-500">{{ __('deals.signed_by', ['name' => $row['decision']->decidedByUser?->username]) }} · <x-app.local-datetime :value="$row['decision']->decided_at" /></span>@endif
                            </li>
                        @endforeach
                    </ul>
                    <div class="flex flex-wrap gap-2">
                        <flux:button :href="route('relationships.agreement.print', $relationship)" target="_blank" size="sm" variant="primary" icon="printer">{{ __('deals.print.open') }}</flux:button>
                        <flux:button :href="route('contracts.show', $latestContract)" size="sm" variant="ghost">{{ __('deals.open_contract') }}</flux:button>
                    </div>
                </flux:card>
            @elseif ($tc['proposal'] && $version)
                @php($proposalOpen = $tc['open'])
                <flux:card class="space-y-3 border-sky-300 dark:border-sky-700" data-terms-card>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:heading>{{ $terms['title'] ?? __('deals.agreement') }} · {{ __('deals.terms_card', ['version' => $version->version]) }}</flux:heading>
                        <flux:badge color="{{ $proposalOpen ? 'sky' : ($tc['proposal']->status === \App\ProposalStatus::Accepted ? 'emerald' : 'zinc') }}">{{ __('proposals.status.'.$tc['proposal']->status->value) }}</flux:badge>
                    </div>

                    @if ($terms)
                        @if (! empty($terms['summary']))<p class="text-sm" dir="auto">{{ $terms['summary'] }}</p>@endif
                        <x-deals.document :sections="$doc" :parties="$partyNames" />
                    @else
                        <div class="whitespace-pre-wrap break-words rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900" dir="auto">{{ $version->termsRevision->payload['terms'] ?? '' }}</div>
                    @endif

                    @if ($tc['diff'] && $tc['diff']['rows'])
                        <div class="space-y-1 rounded-lg bg-amber-50 p-3 text-sm dark:bg-amber-950/30" data-agreement-changes>
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
                            <li wire:key="tp-{{ $row['party']->id }}" class="flex flex-wrap items-center justify-between gap-2">
                                <span dir="auto">{{ $partyName($row['party']) }}</span>
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
                        @if ($proposalOpen && $canDraft)
                            <flux:button wire:click="$dispatchTo('deals.agreement-composer', 'open-agreement')" size="sm">{{ __('deals.revise_agreement') }}</flux:button>
                        @endif
                        @if ($proposalOpen && $tc['canWithdraw'])
                            <flux:button wire:click="withdrawTerms" size="sm" variant="ghost" wire:confirm="{{ __('deals.withdraw_confirm') }}">{{ __('deals.withdraw') }}</flux:button>
                        @endif
                        @if (! $proposalOpen && $canDraft && ! $latestContract)
                            <flux:button wire:click="$dispatchTo('deals.agreement-composer', 'open-agreement')" size="sm" variant="primary">{{ __('deals.propose_new') }}</flux:button>
                        @endif
                    </div>
                </flux:card>
            @elseif ($relationship->status === \App\RelationshipStatus::Active)
                <flux:card class="space-y-3" data-agreement-start>
                    <flux:heading>{{ __('deals.agreement') }}@if ($canDraft && $hasDraft) <flux:badge size="sm" color="sky" data-draft-badge>{{ __('deals.drafts.badge') }}</flux:badge>@endif</flux:heading>
                    @if ($canDraft)
                        <p class="text-sm text-zinc-500">{{ $hasDraft ? __('deals.draft_saved') : __('deals.start_agreement_help') }}</p>
                        <flux:button wire:click="$dispatchTo('deals.agreement-composer', 'open-agreement')" variant="primary" size="sm">{{ $hasDraft ? __('deals.continue_draft') : __('deals.start_agreement') }}</flux:button>
                    @else
                        <p class="text-sm text-zinc-500">{{ __('deals.ask_agreement_help') }}</p>
                        <flux:input wire:model="requestNote" :placeholder="__('deals.note_optional')" maxlength="1000" />
                        <flux:button wire:click="requestAgreement" size="sm" variant="primary">{{ __('deals.ask_agreement') }}</flux:button>
                    @endif
                </flux:card>
            @else
                <p class="py-4 text-center text-sm text-zinc-500">{{ __('deals.agreement_after_join') }}</p>
            @endif

            @if ($tc['history']->isNotEmpty())
                <div class="space-y-2">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('deals.earlier_versions') }}</div>
                    @foreach ($tc['history'] as $old)
                        <details wire:key="old-{{ $old->id }}" id="version-{{ $old->version }}" x-data="{ open: {{ $focusVersion === $old->version ? 'true' : 'false' }} }" x-bind:open="open" x-on:toggle="open = $el.open" class="rounded-lg border border-zinc-200 p-2 text-sm dark:border-zinc-700">
                            <summary class="cursor-pointer">{{ __('deals.terms_card', ['version' => $old->version]) }}</summary>
                            <div class="mt-2">
                                @if ($old->terms_data)<x-deals.document :sections="\App\Support\Deals\AgreementDocument::sections($old->terms_data)" />@else<div class="whitespace-pre-wrap break-words" dir="auto">{{ $old->termsRevision->payload['terms'] ?? '' }}</div>@endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif

            @if ($canPropose)
                <livewire:deals.agreement-composer :relationship="$relationship" :autostart="$canDraft && $autostart" :key="'agreement-composer-'.$relationship->uuid" />
            @endif

            @if ($isOwnerSide && $relationship->status === \App\RelationshipStatus::Active)
                <details wire:key="drafter-rule" x-data="{ open: false }" x-bind:open="open" x-on:toggle="open = $el.open" class="rounded-lg border border-zinc-200 p-2 text-sm dark:border-zinc-700" data-drafter-rule>
                    <summary class="cursor-pointer">{{ __('deals.who_drafts') }}</summary>
                    <p class="mt-2 text-xs text-zinc-500">{{ __('deals.who_drafts_help') }}</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($relationship->participants->where('status', \App\RelationshipParticipantStatus::Active) as $p)
                            <div wire:key="drafter-{{ $p->actor_id }}" class="flex flex-wrap items-center justify-between gap-2">
                                <x-app.actor-identity :actor="$p->actor" size="xs" />
                                @if ((int) $p->actor_id === (int) $drafterActorId)
                                    <flux:badge size="sm" color="sky">{{ __('deals.drafter') }}</flux:badge>
                                @else
                                    <flux:button size="xs" wire:click="delegateDrafting({{ $p->actor_id }})">{{ __('deals.let_draft') }}</flux:button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>

        {{-- Work: the connection point for planning and confirmed delivery (M4). Nothing is tracked here yet. --}}
        <div x-show="tab === 'work'" x-cloak data-panel="work" class="space-y-3">
            <flux:card class="space-y-2">
                <flux:heading>{{ __('deals.tabs.work') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ __('deals.work_not_yet') }}</p>
                @if ($agreed && $terms)
                    <dl class="space-y-1 text-sm" data-work-hooks>
                        @foreach (['time' => __('deals.doc.sections.time'), 'recurrence' => __('deals.doc.sections.recurrence'), 'completion' => __('deals.doc.sections.completion')] as $id => $label)
                            @php($sec = collect($doc)->firstWhere('id', $id))
                            @if ($sec)<div class="flex flex-wrap gap-x-2"><dt class="text-zinc-500">{{ $label }}</dt><dd class="font-medium" dir="auto">{{ collect($sec['lines'])->pluck('value')->implode(' · ') }}</dd></div>@endif
                        @endforeach
                    </dl>
                @endif
            </flux:card>
        </div>

        {{-- Money: the connection point for bookkeeping and settlement (M5). No balances exist here. --}}
        <div x-show="tab === 'money'" x-cloak data-panel="money" class="space-y-3">
            <flux:card class="space-y-2">
                <flux:heading>{{ __('deals.tabs.money') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ __('deals.money_not_yet') }}</p>
                @if ($terms && ($terms['price'] ?? $terms['payment'] ?? false))
                    <dl class="space-y-1 text-sm" data-money-hooks>
                        @foreach (['price', 'payment'] as $id)
                            @php($sec = collect($doc)->firstWhere('id', $id))
                            @if ($sec)<div class="flex flex-wrap gap-x-2"><dt class="text-zinc-500">{{ $sec['title'] }}</dt><dd class="font-medium" dir="auto">{{ collect($sec['lines'])->pluck('value')->implode(' · ') }}</dd></div>@endif
                        @endforeach
                    </dl>
                    <p class="text-xs text-zinc-500">{{ $agreed ? __('deals.money_agreed') : __('deals.money_proposed') }}</p>
                @endif
            </flux:card>
        </div>
    </div>

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
