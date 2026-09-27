<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="$contract->title" :description="__('contracts.show.help')">
        <x-slot:actions>
            <flux:badge>{{ __('contracts.status.'.$contract->status->value) }}</flux:badge>
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
                                {{ AppSupportMoneyAmount::format($displayServiceTerms->unit_rate_minor, $displayServiceTerms->monetaryUnit->exponent) }}
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

            @if ($financialSummaries->isNotEmpty())
                <flux:card class="space-y-5">
                    <div>
                        <flux:heading size="lg">{{ __('financial.summary.title') }}</flux:heading>
                        <flux:text>{{ __('financial.summary.help') }}</flux:text>
                    </div>

                    @foreach ($financialSummaries as $financial)
                        @php($summary = $financial['summary'])
                        @php($unit = $financial['unit'])
                        <div wire:key="financial-summary-{{ $unit->uuid }}" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-semibold">{{ $unit->code }}</div>
                                <flux:badge color="zinc">{{ $summary['obligation_count'] }} {{ __('financial.obligations') }}</flux:badge>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                                <div>
                                    <div class="text-xs text-zinc-500">{{ __('financial.earned') }}</div>
                                    <div class="font-semibold">{{ \App\Support\MoneyAmount::format($summary['earned_minor'], $unit->exponent) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-zinc-500">{{ __('financial.awaiting_confirmation') }}</div>
                                    <div class="font-semibold">{{ \App\Support\MoneyAmount::format($summary['pending_minor'], $unit->exponent) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-zinc-500">{{ __('financial.paid') }}</div>
                                    <div class="font-semibold">{{ \App\Support\MoneyAmount::format($summary['paid_minor'], $unit->exponent) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-zinc-500">{{ __('financial.available_to_pay') }}</div>
                                    <div class="font-semibold">{{ \App\Support\MoneyAmount::format($summary['available_minor'], $unit->exponent) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-zinc-500">{{ __('financial.outstanding') }}</div>
                                    <div class="font-semibold">{{ \App\Support\MoneyAmount::format($summary['outstanding_minor'], $unit->exponent) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-zinc-500">{{ __('financial.disputed') }}</div>
                                    <div class="font-semibold">{{ \App\Support\MoneyAmount::format($summary['disputed_minor'], $unit->exponent) }}</div>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs text-zinc-500">
                                <span>{{ __('financial.scheduled') }}: {{ $summary['scheduled_count'] }}</span>
                                <span>·</span>
                                <span>{{ __('financial.worked') }}: {{ $summary['worked_count'] }}</span>
                                <span>·</span>
                                <span>{{ __('financial.accepted') }}: {{ $summary['accepted_count'] }}</span>
                            </div>
                            <div class="border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                <div class="mb-2">
                                    <div class="text-sm font-medium">{{ __('financial.summary.daily_title') }}</div>
                                    <div class="text-xs text-zinc-500">{{ __('financial.summary.daily_help') }}</div>
                                </div>
                                <div class="max-h-72 overflow-y-auto">
                                    <div class="min-w-[620px] divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                                        @foreach ($financial['daily'] as $daily)
                                            <div class="grid grid-cols-5 gap-3 py-2">
                                                <div><x-app.local-date :value="$daily['date']" /></div>
                                                <div>
                                                    <span class="text-xs text-zinc-500">{{ __('financial.earned') }}</span>
                                                    <div>{{ \App\Support\MoneyAmount::format($daily['earned_minor'], $unit->exponent) }}</div>
                                                </div>
                                                <div>
                                                    <span class="text-xs text-zinc-500">{{ __('financial.awaiting_confirmation') }}</span>
                                                    <div>{{ \App\Support\MoneyAmount::format($daily['pending_minor'], $unit->exponent) }}</div>
                                                </div>
                                                <div>
                                                    <span class="text-xs text-zinc-500">{{ __('financial.paid') }}</span>
                                                    <div>{{ \App\Support\MoneyAmount::format($daily['paid_minor'], $unit->exponent) }}</div>
                                                </div>
                                                <div>
                                                    <span class="text-xs text-zinc-500">{{ __('financial.outstanding') }}</span>
                                                    <div>{{ \App\Support\MoneyAmount::format($daily['outstanding_minor'], $unit->exponent) }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="space-y-2">
                        @foreach ($financialObligations as $obligation)
                            <a
                                href="{{ route('financial-obligations.show', $obligation) }}"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                            >
                                <span class="text-sm">
                                    {{ $obligation->fulfillment->commitment->title }}
                                    · {{ $obligation->creditor->user?->username }}
                                    ← {{ $obligation->debtor->user?->username }}
                                </span>
                                <span class="font-medium">
                                    {{ \App\Support\MoneyAmount::format($obligation->amount_minor, $obligation->monetaryUnit->exponent) }}
                                    {{ $obligation->monetaryUnit->code }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            @if ($payableUnits->isNotEmpty())
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('financial.settlement_batch.title') }}</flux:heading>
                        <flux:text>{{ __('financial.settlement_batch.help') }}</flux:text>
                    </div>

                    <form wire:submit="proposeCashSettlement" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <flux:input wire:model="settlementAmount" :label="__('financial.settlement_batch.amount')" inputmode="decimal" />
                            <flux:select wire:model="settlementUnitCode" :label="__('financial.settlement_batch.unit')">
                                @foreach ($payableUnits as $payableUnit)
                                    <flux:select.option :value="$payableUnit->code">{{ $payableUnit->code }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <x-app.calendar-datetime-input
                                model="settlementPaidAt"
                                :label="__('financial.settlement.paid_at')"
                                :timezone="$timezone"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="settlementReference" :label="__('financial.settlement.reference')" maxlength="255" />
                            <flux:input wire:model="settlementNote" :label="__('financial.settlement.note')" maxlength="5000" />
                        </div>

                        <flux:button type="submit" variant="primary">
                            {{ __('financial.settlement_batch.record') }}
                        </flux:button>
                    </form>

                    <flux:callout>{{ __('financial.settlement_batch.cash_only') }}</flux:callout>
                </flux:card>
            @endif

            @if ($settlementBatches->isNotEmpty())
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('financial.settlement_batch.history') }}</flux:heading>

                    @foreach ($settlementBatches as $batch)
                        @php($batchStatus = $batch->derivedStatus())
                        <article wire:key="contract-settlement-batch-{{ $batch->uuid }}" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="text-lg font-semibold">
                                        {{ \App\Support\MoneyAmount::format($batch->amount_minor, $batch->monetaryUnit->exponent) }}
                                        {{ $batch->monetaryUnit->code }}
                                    </div>
                                    <div class="mt-1 text-xs text-zinc-500">
                                        <x-app.local-datetime :value="$batch->paid_at" />
                                        · {{ $batch->debtor->user?->username }} → {{ $batch->creditor->user?->username }}
                                    </div>
                                </div>
                                <flux:badge>{{ __('financial.settlement_batch.status.'.$batchStatus) }}</flux:badge>
                            </div>

                            <div class="text-xs text-zinc-500">
                                {{ __('financial.settlement_batch.allocations', ['count' => $batch->settlements->count()]) }}
                            </div>

                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($batch->settlements as $allocation)
                                    <a
                                        href="{{ route('financial-obligations.show', $allocation->obligation) }}"
                                        class="rounded-lg bg-zinc-50 p-3 text-sm hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                                    >
                                        <div class="font-medium" dir="auto">{{ $allocation->obligation->fulfillment->commitment->title }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">
                                            {{ \App\Support\MoneyAmount::format($allocation->amount_minor, $batch->monetaryUnit->exponent) }}
                                            {{ $batch->monetaryUnit->code }}
                                            · {{ __('financial.settlement.'.$allocation->status->value) }}
                                        </div>
                                    </a>
                                @endforeach
                            </div>

                            @if ((int) $actor->id === (int) $batch->creditor_actor_id && $batch->pendingMinor() > 0)
                                <div class="space-y-3 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                                    <flux:input
                                        wire:model="batchRejectionNotes.{{ $batch->id }}"
                                        :label="__('financial.settlement_batch.rejection_reason')"
                                    />
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button wire:click="confirmSettlementBatch({{ $batch->id }})" size="sm" variant="primary">
                                            {{ __('financial.settlement_batch.confirm') }}
                                        </flux:button>
                                        <flux:button wire:click="rejectSettlementBatch({{ $batch->id }})" size="sm" variant="danger">
                                            {{ __('financial.settlement_batch.reject') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </flux:card>
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
                    <flux:button :href="route('proposals.show', $contract->sourceProposalVersion->proposal)" variant="ghost" class="w-full">
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
