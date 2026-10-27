<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="$contract->title" :description="__('contracts.show.help')">
        <x-slot:actions>
            <flux:badge>{{ __('contracts.status.'.$contract->status->value) }}</flux:badge>
            @if ($contract->relationship)
                <flux:button :href="\App\Support\Deals\DealLinks::workspace($contract->relationship, 'agreement')" size="sm" variant="primary">{{ __('deals.back_to_deal') }}</flux:button>
                <flux:button :href="route('relationships.agreement.print', $contract->relationship)" size="sm" icon="printer" target="_blank">{{ __('deals.print.open') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>{{ __('contracts.show.authority') }}</flux:callout>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($pendingVersion)
                <flux:card class="space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ __('contracts.show.current_version', ['version' => $pendingVersion->version]) }}</flux:heading>
                            <flux:text>
                                {{ __('contracts.show.proposed_by', ['username' => $pendingVersion->proposedBy->user?->username ?? __('relationships.unknown_actor')]) }}
                            </flux:text>
                        </div>
                        <flux:badge>{{ __('contracts.version_status.'.$pendingVersion->status->value) }}</flux:badge>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('contracts.create.terms') }}</div>
                        <div class="mt-2 whitespace-pre-wrap" dir="auto">{{ $pendingVersion->termsRevision->payload['terms'] ?? '' }}</div>
                    </div>

                    <div class="text-sm text-zinc-500">
                        {{ __('contracts.show.effective_label') }} <x-app.local-datetime :value="$pendingVersion->effective_from" />
                    </div>

                    @if ($pendingVersion->accepted_at && $pendingVersion->status->value === 'accepted')
                        <flux:callout>
                            {{ __('contracts.show.waiting_effective') }}
                        </flux:callout>
                    @endif

                    @if ($canAccept)
                        <div class="space-y-2">
                            <flux:text>{{ __('contracts.show.accept_help') }}</flux:text>
                            <flux:button wire:click="accept" variant="primary">
                                {{ __('contracts.actions.accept') }}
                            </flux:button>
                        </div>
                    @endif
                </flux:card>
            @endif

            @if ($activeVersion)
                <flux:card class="space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ __('contracts.show.current_version', ['version' => $activeVersion->version]) }}</flux:heading>
                            <flux:text>{{ __('contracts.version_status.'.$activeVersion->status->value) }}</flux:text>
                        </div>
                        <flux:badge color="zinc">{{ __('contracts.show.sealed_terms') }}</flux:badge>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('contracts.create.terms') }}</div>
                        <div class="mt-2 whitespace-pre-wrap" dir="auto">{{ $activeVersion->termsRevision->payload['terms'] ?? '' }}</div>
                    </div>

                    <div class="text-sm text-zinc-500">
                        {{ __('contracts.show.effective_label') }} <x-app.local-datetime :value="$activeVersion->effective_from" />
                    </div>
                </flux:card>
            @endif

            @php($displayServiceTerms = $pendingServiceTerms ?: $activeServiceTerms)
            @if ($displayServiceTerms)
                <flux:card class="space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ __('contracts.service.active_title') }}</flux:heading>
                            <flux:text>{{ __('contracts.show.service_terms') }}</flux:text>
                        </div>
                        <flux:badge color="zinc">
                            {{ $displayServiceTerms->monetaryUnit->code }}
                        </flux:badge>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                            <div class="text-xs text-zinc-500">{{ __('contracts.service.worker') }}</div>
                            <div class="mt-1 font-medium">{{ $displayServiceTerms->worker->user?->username }}</div>
                        </div>
                        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                            <div class="text-xs text-zinc-500">{{ __('contracts.service.employer') }}</div>
                            <div class="mt-1 font-medium">{{ $displayServiceTerms->employer->user?->username }}</div>
                        </div>
                        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                            <div class="text-xs text-zinc-500">{{ __('contracts.service.unit_rate') }}</div>
                            <div class="mt-1 font-medium">
                                {{ \App\Support\MoneyAmount::format($displayServiceTerms->unit_rate_minor, $displayServiceTerms->monetaryUnit->exponent) }}
                                {{ $displayServiceTerms->monetaryUnit->code }} / {{ $displayServiceTerms->unit }}
                            </div>
                        </div>
                        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                            <div class="text-xs text-zinc-500">{{ __('contracts.service.settlement_cycle') }}</div>
                            <div class="mt-1 font-medium">{{ __('contracts.service.cycles.'.$displayServiceTerms->settlement_cycle) }}</div>
                        </div>
                    </div>

                    <div class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('contracts.service.quantity_sentence', [
                            'quantity' => $displayServiceTerms->total_quantity,
                            'unit' => $displayServiceTerms->unit,
                            'per' => $displayServiceTerms->quantity_per_occurrence,
                        ]) }}
                    </div>

                    @if ($displayServiceTerms->commitment?->planBinding?->plan)
                        <flux:button
                            :href="route('planner.show', $displayServiceTerms->commitment->planBinding->plan)"
                            variant="primary"
                        >
                            {{ __('contracts.service.open_plan') }}
                        </flux:button>
                    @elseif ($pendingServiceTerms)
                        <flux:callout>{{ __('contracts.service.schedule_help') }}</flux:callout>
                    @endif
                </flux:card>
            @endif

            @if ($serviceAmendmentLocked)
                <flux:callout>{{ __('contracts.amendment.structured_service_locked') }}</flux:callout>
            @endif

            @if ($canAmend)
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('contracts.amendment.title') }}</flux:heading>
                        <flux:text>{{ __('contracts.amendment.help') }}</flux:text>
                    </div>

                    <form wire:submit="proposeAmendment" class="space-y-4">
                        <flux:input wire:model="amendmentTitle" :label="__('contracts.amendment.terms_title')" maxlength="255" />
                        <flux:textarea wire:model="amendmentSummary" :label="__('contracts.create.summary')" rows="3" />
                        <flux:textarea wire:model="amendmentTerms" :label="__('contracts.create.terms')" rows="12" />
                        <flux:textarea wire:model="amendmentNotes" :label="__('contracts.create.notes')" rows="4" />
                        <flux:input wire:model="versionNote" :label="__('contracts.amendment.version_note')" maxlength="1000" />
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-app.calendar-datetime-input model="effectiveAt" :label="__('contracts.create.effective_at')" :timezone="$timezone" />
                            <flux:input wire:model="timezone" :label="__('contracts.create.timezone')" maxlength="64" />
                        </div>
                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary">{{ __('contracts.amendment.submit') }}</flux:button>
                        </div>
                    </form>
                </flux:card>
            @endif

            <flux:card class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('commitments.contract_section') }}</flux:heading>
                        <flux:text>{{ __('commitments.contract_help') }}</flux:text>
                    </div>
                    @if ($canCreateCommitment)
                        <flux:button :href="route('commitments.create', $contract)" variant="primary">
                            {{ __('commitments.new') }}
                        </flux:button>
                    @endif
                </div>

                @forelse ($commitments as $commitment)
                    <a href="{{ route('commitments.show', $commitment) }}" class="block rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold" dir="auto">{{ $commitment->title }}</div>
                                <div class="mt-1 text-sm text-zinc-500">
                                    {{ $commitment->quantity }} {{ $commitment->unit }}
                                    · {{ $commitment->obligor->user?->username }}
                                    → {{ $commitment->beneficiary->user?->username }}
                                </div>
                            </div>
                            <flux:badge color="zinc">
                                {{ __('commitments.kind.'.$commitment->kind->value) }}
                            </flux:badge>
                        </div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('commitments.empty')" />
                @endforelse
            </flux:card>

            @if (\Illuminate\Support\Facades\Route::has('bookkeeping.index'))
                <flux:callout icon="banknotes" data-contract-bookkeeping-hint>
                    {{ __('contracts.show.bookkeeping_hint') }}
                    <x-slot name="actions">
                        <flux:button :href="route('bookkeeping.index')" size="sm">{{ __('contracts.show.open_bookkeeping') }}</flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('contracts.show.version_history') }}</flux:heading>

                @foreach ($contract->versions->sortByDesc('version') as $version)
                    <article wire:key="contract-version-{{ $version->uuid }}" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="font-semibold">{{ __('contracts.show.current_version', ['version' => $version->version]) }}</div>
                            <flux:badge color="zinc">{{ __('contracts.version_status.'.$version->status->value) }}</flux:badge>
                        </div>

                        <div class="text-sm text-zinc-500">
                            {{ __('contracts.show.effective_label') }} <x-app.local-datetime :value="$version->effective_from" />
                            @if ($version->effective_until)
                                · {{ __('contracts.show.effective_until_label') }} <x-app.local-datetime :value="$version->effective_until" />
                            @endif
                        </div>

                        @if ($version->note)
                            <div class="text-sm" dir="auto">{{ $version->note }}</div>
                        @endif

                        <div class="space-y-2">
                            @foreach ($version->parties as $versionParty)
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span>
                                        {{ $versionParty->actor->user?->username ?? __('relationships.unknown_actor') }}
                                        · {{ $versionParty->role }}
                                        @if ($versionParty->required)
                                            · {{ __('contracts.show.required_party') }}
                                        @endif
                                    </span>
                                    <flux:badge color="zinc">
                                        {{ $versionParty->acceptance ? __('contracts.show.accepted') : __('contracts.show.pending') }}
                                    </flux:badge>
                                </div>
                            @endforeach
                        </div>

                        @if ($context)
                            <a href="{{ route('contexts.contents.show', [$context, $version->termsRevision->content]) }}" class="text-sm font-medium hover:underline">
                                {{ __('contracts.show.open_terms') }}
                            </a>
                        @endif
                    </article>
                @endforeach
            </flux:card>
        </div>

        <div class="space-y-6">
            @php($displayVersion = $pendingVersion ?: $activeVersion ?: $contract->versions->sortByDesc('version')->first())
            @if ($displayVersion)
                <flux:card class="space-y-4">
                    <flux:heading>{{ __('contracts.show.parties') }}</flux:heading>
                    @foreach ($displayVersion->parties as $party)
                        <div wire:key="contract-party-{{ $party->uuid }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <x-app.actor-identity :actor="$party->actor" size="sm" />
                            <div class="mt-2 text-xs text-zinc-500">
                                {{ $party->role }}
                                @if ($party->required)
                                    · {{ __('contracts.show.required_party') }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </flux:card>
            @endif

            @if ($context)
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('contracts.show.workspace') }}</flux:heading>
                    <flux:text>{{ __('contracts.show.workspace_help') }}</flux:text>
                    <flux:text>{{ __('contracts.show.shared_evidence_help') }}</flux:text>
                    <div class="grid gap-2">
                        <flux:button :href="route('contexts.conversation', $context)" variant="primary">
                            {{ __('collaboration.tabs.conversation') }}
                        </flux:button>
                        <flux:button :href="route('contexts.timeline', $context)" variant="ghost">
                            {{ __('collaboration.tabs.timeline') }}
                        </flux:button>
                        <flux:button :href="route('contexts.contents.index', $context)" variant="ghost">
                            {{ __('collaboration.tabs.content') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endif

            @if ($contract->sourceProposalVersion)
                <flux:card class="space-y-2">
                    <flux:heading>{{ __('contracts.source_proposal') }}</flux:heading>
                    <flux:button :href="\App\Support\Deals\DealLinks::proposal($contract->sourceProposalVersion->proposal)" variant="ghost" class="w-full">
                        {{ $contract->sourceProposalVersion->proposal->title }}
                    </flux:button>
                </flux:card>
            @endif

            @if ($contract->relationship)
                <flux:card class="space-y-2">
                    <flux:heading>{{ __('contracts.source_relationship') }}</flux:heading>
                    <flux:button :href="route('relationships.show', $contract->relationship)" variant="ghost" class="w-full">
                        {{ $contract->relationship->title ?: 'REL-'.str_pad((string) $contract->relationship->id, 6, '0', STR_PAD_LEFT) }}
                    </flux:button>
                </flux:card>
            @endif
        </div>
    </div>

    <flux:callout>{{ __('contracts.show.no_fulfillment') }}</flux:callout>
</section>
