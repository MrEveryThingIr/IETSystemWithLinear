<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="$commitment->title" :description="__('commitments.show.help')">
        <x-slot:actions>
            <flux:badge>{{ __('commitments.status.'.$commitment->status->value) }}</flux:badge>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>
        {{ __('commitments.show.governing_version', ['version' => $commitment->contractVersion->version]) }}
        ·
        <a href="{{ route('contracts.show', $commitment->contractVersion->contract) }}" class="font-medium underline">
            {{ $commitment->contractVersion->contract->title }}
        </a>
    </flux:callout>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('commitments.show.obligation') }}</flux:heading>

                @if ($commitment->description)
                    <div class="whitespace-pre-wrap" dir="auto">{{ $commitment->description }}</div>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('commitments.show.responsible') }}</div>
                        <x-app.actor-identity :actor="$commitment->obligor" size="sm" class="mt-2" />
                    </div>
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900">
                        <div class="text-xs uppercase tracking-wide text-zinc-500">{{ __('commitments.show.beneficiary') }}</div>
                        <x-app.actor-identity :actor="$commitment->beneficiary" size="sm" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="text-xs text-zinc-500">{{ __('commitments.show.required') }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $commitment->quantity }} {{ $commitment->unit }}</div>
                    </div>
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="text-xs text-zinc-500">{{ __('commitments.show.accepted') }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $acceptedQuantity }} {{ $commitment->unit }}</div>
                    </div>
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="text-xs text-zinc-500">{{ __('commitments.show.remaining') }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $remainingQuantity }} {{ $commitment->unit }}</div>
                    </div>
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('commitments.show.planner') }}</flux:heading>
                        <flux:text>{{ __('commitments.show.planner_help') }}</flux:text>
                    </div>
                    @if ($plan)
                        <flux:button :href="route('planner.show', $plan)" variant="ghost">
                            {{ __('commitments.show.open_plan') }}
                        </flux:button>
                    @endif
                </div>

                @if (! $plan && $canManage)
                    <form wire:submit="createPlan" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:select wire:model="planFrequency" :label="__('commitments.planner.frequency')">
                                <flux:select.option value="once">{{ __('commitments.planner.once') }}</flux:select.option>
                                <flux:select.option value="daily">Daily</flux:select.option>
                                <flux:select.option value="selected_dates">{{ __('commitments.planner.selected_dates') }}</flux:select.option>
                            </flux:select>
                            <flux:input wire:model="planStartsOn" type="date" :label="__('commitments.planner.starts_on')" />
                            <flux:input wire:model="planStartTime" type="time" :label="__('commitments.planner.start_time')" />
                            <flux:input wire:model="planDurationMinutes" type="number" min="1" :label="__('commitments.planner.duration')" />
                            @if ($planFrequency === 'daily')
                                <flux:input wire:model="planOccurrenceLimit" type="number" min="1" :label="__('planner.create.occurrence_limit')" />
                            @endif
                        </div>

                        @if ($planFrequency === 'selected_dates')
                            <flux:textarea
                                wire:model="planSelectedDates"
                                :label="__('commitments.planner.selected_dates_field')"
                                :description="__('commitments.planner.selected_dates_help')"
                                rows="3"
                            />
                        @endif

                        <flux:input wire:model="planReminderOffsets" :label="__('commitments.planner.reminders')" />
                        <flux:button type="submit" variant="primary">{{ __('commitments.show.create_plan') }}</flux:button>
                    </form>
                @elseif (! $plan)
                    <flux:text>{{ __('commitments.show.no_plan') }}</flux:text>
                @else
                    <div class="space-y-2">
                        @foreach ($plan->occurrences->sortBy('scheduled_start_at') as $occurrence)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                <span>
                                    {{ $occurrence->scheduled_start_at->setTimezone($plan->timezone)->format('Y-m-d H:i') }}
                                    → {{ $occurrence->scheduled_end_at->setTimezone($plan->timezone)->format('H:i') }}
                                </span>
                                <flux:badge>{{ __('planner.occurrence_status.'.$occurrence->status->value) }}</flux:badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>

            @if ($canSubmit && ! $isSatisfied)
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('commitments.show.submit') }}</flux:heading>
                        <flux:text>{{ __('commitments.show.submit_help') }}</flux:text>
                    </div>

                    <form wire:submit="submitFulfillment" class="space-y-4">
                        @if ($completedOccurrences->isNotEmpty())
                            <flux:select wire:model="fulfillmentOccurrenceUuid" :label="__('commitments.show.occurrence')">
                                <flux:select.option value="">{{ __('commitments.show.no_occurrence') }}</flux:select.option>
                                @foreach ($completedOccurrences as $occurrence)
                                    <flux:select.option :value="$occurrence->uuid">
                                        {{ $occurrence->actual_start_at?->setTimezone($timezone)->format('Y-m-d H:i') }}
                                        → {{ $occurrence->actual_end_at?->setTimezone($timezone)->format('H:i') }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        <flux:input wire:model="fulfillmentQuantity" :label="__('commitments.show.quantity')" />
                        <flux:textarea wire:model="fulfillmentNotes" :label="__('commitments.show.notes')" rows="3" />
                        <flux:button type="submit" variant="primary">{{ __('commitments.show.submit_action') }}</flux:button>
                    </form>
                </flux:card>
            @endif

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('commitments.show.fulfillments') }}</flux:heading>

                @forelse ($commitment->fulfillments as $fulfillment)
                    <article id="fulfillment-{{ $fulfillment->uuid }}" wire:key="fulfillment-{{ $fulfillment->uuid }}" class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold">{{ $fulfillment->quantity }} {{ $fulfillment->unit }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ $fulfillment->submitted_at->format('Y-m-d H:i') }}
                                    · {{ $fulfillment->submitter->user?->username }}
                                </div>
                            </div>
                            <flux:badge>{{ __('commitments.fulfillment_status.'.$fulfillment->status->value) }}</flux:badge>
                        </div>

                        @if ($fulfillment->actual_start_at && $fulfillment->actual_end_at)
                            <div class="text-sm text-zinc-600 dark:text-zinc-300">
                                {{ $fulfillment->actual_start_at->setTimezone($timezone)->format('Y-m-d H:i') }}
                                → {{ $fulfillment->actual_end_at->setTimezone($timezone)->format('H:i') }}
                                · {{ $fulfillment->duration_minutes }} min
                            </div>
                        @endif

                        @if ($fulfillment->notes)
                            <div class="whitespace-pre-wrap text-sm" dir="auto">{{ $fulfillment->notes }}</div>
                        @endif

                        @if ($fulfillment->assets->isNotEmpty() || $fulfillment->evidenceReferences->isNotEmpty())
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($fulfillment->assets as $asset)
                                    <div class="rounded-lg bg-zinc-50 p-3 text-xs dark:bg-zinc-900">{{ $asset->original_filename }}</div>
                                @endforeach
                                @foreach ($fulfillment->evidenceReferences as $reference)
                                    <a href="{{ route('content-evidence.show', $reference) }}" class="rounded-lg bg-zinc-50 p-3 text-xs hover:underline dark:bg-zinc-900">
                                        {{ $reference->revision->title }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @can('review', $fulfillment)
                            <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                                <flux:textarea wire:model="reviewNotes.{{ $fulfillment->id }}" :label="__('commitments.show.review_note')" rows="2" />
                                <div class="flex flex-wrap gap-2">
                                    <flux:button wire:click="review({{ $fulfillment->id }}, 'accepted')" size="sm" variant="primary">{{ __('commitments.show.accept') }}</flux:button>
                                    <flux:button wire:click="review({{ $fulfillment->id }}, 'clarification_requested')" size="sm" variant="ghost">{{ __('commitments.show.clarify') }}</flux:button>
                                    <flux:button wire:click="review({{ $fulfillment->id }}, 'rejected')" size="sm" variant="danger">{{ __('commitments.show.reject') }}</flux:button>
                                </div>
                            </div>
                        @endcan

                        @can('correct', $fulfillment)
                            <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                                <flux:heading size="sm">{{ __('commitments.show.correction') }}</flux:heading>
                                <flux:text>{{ __('commitments.show.correction_help') }}</flux:text>
                                <flux:input wire:model="correctionQuantities.{{ $fulfillment->id }}" :placeholder="$fulfillment->quantity" :label="__('commitments.show.quantity')" />
                                <flux:textarea wire:model="correctionNotes.{{ $fulfillment->id }}" :label="__('commitments.show.notes')" rows="2" />
                                <flux:button wire:click="correct({{ $fulfillment->id }})" size="sm" variant="primary">{{ __('commitments.show.correction') }}</flux:button>
                            </div>
                        @endcan

                        @can('openDispute', $fulfillment)
                            <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                                <flux:textarea wire:model="disputeReasons.{{ $fulfillment->id }}" :label="__('commitments.show.dispute_reason')" rows="2" />
                                <flux:button wire:click="dispute({{ $fulfillment->id }})" size="sm" variant="danger">{{ __('commitments.show.dispute') }}</flux:button>
                            </div>
                        @endcan

                        @if ($fulfillment->dispute && $fulfillment->dispute->status === AppFulfillmentDisputeStatus::Open)
                            @can('resolve', $fulfillment->dispute)
                                <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                                    <flux:input wire:model="resolutionNotes.{{ $fulfillment->dispute->id }}" :label="__('commitments.show.resolution_note')" />
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button wire:click="resolveDispute({{ $fulfillment->dispute->id }}, 'accepted')" size="sm" variant="primary">{{ __('commitments.show.resolve_accept') }}</flux:button>
                                        <flux:button wire:click="resolveDispute({{ $fulfillment->dispute->id }}, 'rejected')" size="sm" variant="danger">{{ __('commitments.show.resolve_reject') }}</flux:button>
                                    </div>
                                </div>
                            @endcan
                        @endif
                    </article>
                @empty
                    <x-app.empty-state :title="__('commitments.show.no_fulfillments')" />
                @endforelse
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-3">
                <flux:heading>{{ __('commitments.show.governing_version', ['version' => $commitment->contractVersion->version]) }}</flux:heading>
                <div class="text-sm">
                    {{ __('commitments.kind.'.$commitment->kind->value) }}
                    · {{ $commitment->quantity }} {{ $commitment->unit }}
                </div>
                <flux:button :href="route('contracts.show', $commitment->contractVersion->contract)" variant="ghost" class="w-full">
                    {{ $commitment->contractVersion->contract->title }}
                </flux:button>
            </flux:card>

            <flux:callout>{{ __('commitments.show.financial_boundary') }}</flux:callout>
        </div>
    </div>
</section>
