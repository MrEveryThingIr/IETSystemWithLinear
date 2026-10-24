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

                @if (! $plan && $commitment->serviceTerm?->auto_create_plan)
                    <flux:callout>{{ __('commitments.show.auto_plan_expected') }}</flux:callout>
                @elseif (! $plan && $canManage)
                    <fieldset class="space-y-2" data-plan-choice>
                        <legend class="mb-2 text-sm font-medium">{{ __('work.plans.choice') }}</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach (['new', 'template', 'existing', 'none'] as $option)
                                <label @class([
                                    'flex min-h-11 cursor-pointer gap-3 rounded-xl border p-3 text-sm',
                                    'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' => $planChoice === $option,
                                    'border-zinc-200 dark:border-zinc-700' => $planChoice !== $option,
                                ])>
                                    <input type="radio" wire:model.live="planChoice" value="{{ $option }}" class="mt-1">
                                    <span class="min-w-0">
                                        <span class="block font-medium">{{ __('work.plans.choices.'.$option.'.title') }}</span>
                                        <span class="block text-zinc-500">{{ __('work.plans.choices.'.$option.'.help') }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    @if ($planChoice === 'new')
                        <form wire:submit="createPlan" class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model="planFrequency" :label="__('commitments.planner.frequency')">
                                    <flux:select.option value="once">{{ __('commitments.planner.once') }}</flux:select.option>
                                    <flux:select.option value="daily">{{ __('commitments.planner.daily') }}</flux:select.option>
                                    <flux:select.option value="selected_dates">{{ __('commitments.planner.selected_dates') }}</flux:select.option>
                                </flux:select>
                                <x-app.calendar-date-input model="planStartsOn" :label="__('commitments.planner.starts_on')" />
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
                    @elseif ($planChoice === 'template')
                        @if ($templates->isEmpty())
                            <flux:text>{{ __('work.templates.none') }}</flux:text>
                        @else
                            <form wire:submit="applyTemplate" class="space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <flux:select wire:model="templateUuid" :label="__('work.templates.choose')">
                                        <flux:select.option value="">—</flux:select.option>
                                        @foreach ($templates as $template)
                                            <flux:select.option :value="$template->uuid">{{ $template->name }} · {{ __('work.templates.frequency.'.$template->frequency->value) }} · {{ $template->start_time }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <x-app.calendar-date-input model="planStartsOn" :label="__('commitments.planner.starts_on')" />
                                </div>
                                <flux:text class="text-xs">{{ __('work.templates.applies_shape') }}</flux:text>
                                <flux:button type="submit" variant="primary">{{ __('work.templates.apply') }}</flux:button>
                            </form>
                        @endif
                    @elseif ($planChoice === 'existing')
                        @if ($existingPlans->isEmpty())
                            <flux:text>{{ __('work.plans.no_existing') }}</flux:text>
                        @else
                            <form wire:submit="bindExisting" class="space-y-4">
                                <flux:select wire:model="existingPlanUuid" :label="__('work.plans.existing')">
                                    <flux:select.option value="">—</flux:select.option>
                                    @foreach ($existingPlans as $existing)
                                        <flux:select.option :value="$existing->uuid">{{ $existing->title }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error('existingPlanUuid') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                                <flux:text class="text-xs">{{ __('work.plans.existing_help') }}</flux:text>
                                <flux:button type="submit" variant="primary">{{ __('work.plans.connect') }}</flux:button>
                            </form>
                        @endif
                    @else
                        <flux:text>{{ __('work.plans.choices.none.later') }}</flux:text>
                    @endif
                @elseif (! $plan)
                    <flux:text>{{ __('commitments.show.no_plan') }}</flux:text>
                @else
                    <div class="space-y-2">
                        @foreach ($plan->occurrences->sortBy('scheduled_start_at') as $occurrence)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                <span>
                                    <x-app.local-datetime :value="$occurrence->scheduled_start_at" />
                                    → <x-app.local-time :value="$occurrence->scheduled_end_at" />
                                </span>
                                <flux:badge>{{ __('planner.occurrence_phase.'.$occurrence->executionPhase()) }}</flux:badge>
                            </div>
                        @endforeach
                    </div>
                    @if ($canManage)
                        <details class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-save-template>
                            <summary class="cursor-pointer py-1 font-medium">{{ __('work.templates.save_title') }}</summary>
                            <form wire:submit="saveTemplate" class="mt-3 space-y-3">
                                <flux:text class="text-xs">{{ __('work.templates.save_help') }}</flux:text>
                                <flux:input wire:model="templateName" :label="__('work.templates.name')" :placeholder="$plan->title" maxlength="160" />
                                @if ($templateOwners->count() > 1)
                                    <flux:select wire:model="templateOwner" :label="__('work.templates.owner')">
                                        @foreach ($templateOwners as $party)
                                            <flux:select.option :value="$party['party']->key()">{{ $party['label'] }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                                @error('template') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                                <flux:button type="submit" size="sm" variant="primary">{{ __('work.templates.save') }}</flux:button>
                            </form>
                        </details>
                    @endif
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
                                        {{ $occurrence->actual_start_at
                                            ? \App\Support\TemporalCalendar::dateTimeLabelWithEquivalent($occurrence->actual_start_at, request()->user())
                                            : '—' }}
                                        →
                                        {{ $occurrence->actual_end_at
                                            ? \App\Support\TemporalCalendar::timeLabel($occurrence->actual_end_at, request()->user())
                                            : '…' }}
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
                                    <x-app.local-datetime :value="$fulfillment->submitted_at" />
                                    · {{ $fulfillment->submitter->user?->username }}
                                </div>
                            </div>
                            <flux:badge>{{ __('commitments.fulfillment_status.'.$fulfillment->status->value) }}</flux:badge>
                        </div>

                        @if ($fulfillment->actual_start_at && $fulfillment->actual_end_at)
                            <div class="text-sm text-zinc-600 dark:text-zinc-300">
                                <x-app.local-datetime :value="$fulfillment->actual_start_at" />
                                → <x-app.local-time :value="$fulfillment->actual_end_at" />
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

                        @if ($fulfillment->status === \App\FulfillmentStatus::Accepted && ($work['isParty'] || ($work['entries'][$fulfillment->id] ?? null)))
                            @php($entry = $work['entries'][$fulfillment->id] ?? null)
                            <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800" data-work-books>
                                <flux:heading size="sm">{{ __('work.books.title') }}</flux:heading>
                                @if ($entry)
                                    <div class="flex flex-wrap items-center gap-2 text-sm">
                                        <flux:badge :color="$entry->status->value === 'confirmed' ? 'green' : 'amber'">{{ __('work.books.status.'.$entry->status->value) }}</flux:badge>
                                        <span>{{ \App\Support\Bookkeeping\Toman::format($entry->amount) }}</span>
                                        @can('view', $entry->ledger)
                                            <a href="{{ route('bookkeeping.books.show', $entry->ledger) }}" class="inline-block py-1 underline [overflow-wrap:anywhere]" dir="auto">{{ $entry->ledger->name }}</a>
                                        @endcan
                                    </div>
                                @elseif (($work['amounts'][$fulfillment->id] ?? null) === null)
                                    <flux:text class="text-sm">{{ __('work.books.no_amount') }}</flux:text>
                                @elseif ($work['books']->isEmpty())
                                    <flux:text class="text-sm">{{ __('work.books.no_shared_book') }}</flux:text>
                                    <flux:button wire:click="createBook" size="sm" variant="ghost">{{ __('work.books.create') }}</flux:button>
                                @else
                                    <flux:text class="text-sm">{{ __('work.books.propose_help', ['amount' => \App\Support\Bookkeeping\Toman::format($work['amounts'][$fulfillment->id])]) }}</flux:text>
                                    <div class="flex flex-wrap items-end gap-2">
                                        <div class="min-w-0 flex-1">
                                            <flux:select wire:model="bookChoice.{{ $fulfillment->id }}" :label="__('work.books.book')">
                                                <flux:select.option value="">—</flux:select.option>
                                                @foreach ($work['books'] as $book)
                                                    <flux:select.option :value="$book->uuid">{{ $book->name }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        <flux:button wire:click="proposeEntry({{ $fulfillment->id }})" size="sm" variant="primary">{{ __('work.books.propose') }}</flux:button>
                                    </div>
                                    @error('bookChoice.'.$fulfillment->id) <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                                @endif
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
                @if ($commitment->serviceTerm)
                    <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900">
                        <div class="text-xs text-zinc-500">{{ __('contracts.service.active_title') }}</div>
                        <div class="mt-1 font-medium">
                            {{ \App\Support\MoneyAmount::format(
                                $commitment->serviceTerm->unit_rate_minor,
                                $commitment->serviceTerm->monetaryUnit->exponent,
                            ) }}
                            {{ $commitment->serviceTerm->monetaryUnit->code }}
                            / {{ $commitment->serviceTerm->unit }}
                        </div>
                    </div>
                @endif
                <flux:button :href="route('contracts.show', $commitment->contractVersion->contract)" variant="ghost" class="w-full">
                    {{ $commitment->contractVersion->contract->title }}
                </flux:button>
            </flux:card>

            <flux:callout>{{ __('commitments.show.financial_boundary') }}</flux:callout>
        </div>
    </div>
</section>
