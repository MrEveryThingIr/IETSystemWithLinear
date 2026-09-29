<section class="mx-auto max-w-5xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('contracts.create.title')" :description="__('contracts.create.help')" />

    @if ($proposal)
        <flux:callout>
            {{ __('contracts.create.proposal_source', ['title' => $proposal->title]) }}
        </flux:callout>
    @elseif ($relationship)
        <flux:callout>
            {{ __('contracts.create.relationship_source', ['title' => $relationship->title ?: 'REL-'.str_pad((string) $relationship->id, 6, '0', STR_PAD_LEFT)]) }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        @if (! $proposal)
            <flux:card class="space-y-4">
                <flux:input wire:model="title" :label="__('contracts.create.contract_title')" maxlength="180" />
                <flux:input wire:model="creatorRole" :label="__('contracts.create.creator_role')" maxlength="80" />
                <flux:textarea wire:model="partyLines" :label="__('contracts.create.parties')" rows="6" />
                <flux:text size="sm">{{ __('contracts.create.parties_help') }}</flux:text>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:textarea wire:model="summary" :label="__('contracts.create.summary')" rows="3" />
                <flux:textarea wire:model="terms" :label="__('contracts.create.terms')" rows="12" />
                <flux:textarea wire:model="notes" :label="__('contracts.create.notes')" rows="4" />
            </flux:card>
        @else
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ $proposal->title }}</flux:heading>
                <flux:text>{{ __('contracts.boundary') }}</flux:text>
                @php($sourceVersion = $proposal->currentVersionRecord())
                @if ($sourceVersion)
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                            {{ __('proposals.show.current_version', ['version' => $sourceVersion->version]) }}
                        </div>
                        <div class="mt-2 whitespace-pre-wrap" dir="auto">{{ $sourceVersion->termsRevision->payload['terms'] ?? '' }}</div>
                    </div>
                @endif
            </flux:card>
        @endif

        <flux:card class="space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('contracts.service.title') }}</flux:heading>
                    <flux:text>{{ __('contracts.service.help') }}</flux:text>
                </div>
                <flux:checkbox wire:model.live="serviceWorkflow" :label="__('contracts.service.enable')" />
            </div>

            @if ($serviceWorkflow)
                <flux:callout>
                    {{ __('contracts.service.acceptance_boundary') }}
                </flux:callout>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="serviceEmployerUsername" :label="__('contracts.service.employer')" maxlength="255" />
                    <flux:input wire:model="serviceWorkerUsername" :label="__('contracts.service.worker')" maxlength="255" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="serviceTitle" :label="__('contracts.service.service_title')" maxlength="180" />
                    <flux:select wire:model="serviceKind" :label="__('contracts.service.kind')">
                        <flux:select.option value="service">{{ __('commitments.kind.service') }}</flux:select.option>
                        <flux:select.option value="work">{{ __('commitments.kind.work') }}</flux:select.option>
                        <flux:select.option value="attendance">{{ __('commitments.kind.attendance') }}</flux:select.option>
                        <flux:select.option value="deliverable">{{ __('commitments.kind.deliverable') }}</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <flux:input wire:model="serviceTotalQuantity" :label="__('contracts.service.total_quantity')" />
                    <flux:input wire:model="serviceQuantityPerOccurrence" :label="__('contracts.service.quantity_per_occurrence')" />
                    <flux:input wire:model="serviceUnit" :label="__('contracts.service.unit')" maxlength="40" />
                    <flux:input wire:model="serviceUnitRate" :label="__('contracts.service.unit_rate')" inputmode="decimal" />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <flux:select wire:model="serviceMonetaryUnit" :label="__('contracts.service.monetary_unit')">
                        @foreach ($monetaryUnits as $code => $meta)
                            <flux:select.option :value="$code">{{ $code }} · {{ $meta['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="serviceSettlementCycle" :label="__('contracts.service.settlement_cycle')">
                        <flux:select.option value="per_fulfillment">{{ __('contracts.service.cycles.per_fulfillment') }}</flux:select.option>
                        <flux:select.option value="weekly">{{ __('contracts.service.cycles.weekly') }}</flux:select.option>
                        <flux:select.option value="monthly">{{ __('contracts.service.cycles.monthly') }}</flux:select.option>
                        <flux:select.option value="contract_end">{{ __('contracts.service.cycles.contract_end') }}</flux:select.option>
                    </flux:select>

                    <flux:input wire:model="servicePaymentDueDays" type="number" min="0" max="3650" :label="__('contracts.service.payment_due_days')" />
                </div>

                <div class="border-t border-zinc-200 pt-5 dark:border-zinc-800">
                    <div class="mb-4">
                        <flux:heading>{{ __('contracts.service.schedule_title') }}</flux:heading>
                        <flux:text>{{ __('contracts.service.schedule_help') }}</flux:text>
                    </div>

                    <div class="grid gap-4 md:grid-cols-4">
                        <flux:select wire:model.live="serviceFrequency" :label="__('planner.create.frequency')">
                            <flux:select.option value="once">{{ __('planner.frequency.once') }}</flux:select.option>
                            <flux:select.option value="daily">{{ __('planner.frequency.daily') }}</flux:select.option>
                            <flux:select.option value="weekly">{{ __('planner.frequency.weekly') }}</flux:select.option>
                            <flux:select.option value="selected_dates">{{ __('planner.frequency.selected_dates') }}</flux:select.option>
                        </flux:select>

                        <x-app.calendar-date-input model="serviceStartsOn" :label="__('planner.create.starts_on')" />
                        <flux:input wire:model="serviceStartTime" type="time" :label="__('planner.create.start_time')" />
                        <flux:input wire:model="serviceDurationMinutes" type="number" min="1" max="10080" :label="__('planner.create.duration')" />
                    </div>

                    @if ($serviceFrequency === 'weekly')
                        <div class="mt-4">
                            <div class="mb-2 text-sm font-medium">{{ __('planner.create.weekdays') }}</div>
                            <div class="flex flex-wrap gap-3">
                                @foreach ($weekdayOrder as $weekday)
                                    <flux:checkbox
                                        wire:model="serviceWeekdays"
                                        :value="$weekday"
                                        :label="__('planner.weekdays.'.$weekday)"
                                    />
                                @endforeach
                            </div>
                        </div>
                    @elseif ($serviceFrequency === 'selected_dates')
                        <div class="mt-4">
                            <flux:textarea wire:model="serviceSelectedDates" :label="__('planner.create.selected_dates')" rows="2" />
                            <flux:text size="sm">{{ __('planner.create.selected_dates_help') }}</flux:text>
                        </div>
                    @endif

                    <div class="mt-4 grid gap-4 md:grid-cols-4">
                        <flux:input wire:model="serviceInterval" type="number" min="1" max="365" :label="__('planner.create.interval')" />
                        <x-app.calendar-date-input model="serviceEndsOn" :label="__('planner.create.ends_on')" />
                        <flux:input wire:model="serviceOccurrenceLimit" type="number" min="1" max="10000" :label="__('planner.create.occurrence_limit')" />
                        <flux:input wire:model="serviceReminderOffsets" :label="__('planner.create.reminders')" />
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="serviceWindowBeforeMinutes" type="number" min="0" max="10080" :label="__('planner.create.early_window')" />
                        <flux:input wire:model="serviceWindowAfterMinutes" type="number" min="0" max="10080" :label="__('planner.create.late_window')" />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <flux:checkbox wire:model="serviceAutoCreatePlan" :label="__('contracts.service.auto_plan')" />
                    <flux:checkbox wire:model="serviceAutoRecognizeObligation" :label="__('contracts.service.auto_obligation')" />
                </div>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-app.calendar-datetime-input model="effectiveAt" :label="__('contracts.create.effective_at')" :timezone="$timezone" />
                <flux:input wire:model="timezone" :label="__('contracts.create.timezone')" maxlength="64" />
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">
                {{ __('contracts.create.submit') }}
            </flux:button>
        </div>
    </form>
</section>
