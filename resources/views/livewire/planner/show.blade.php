<section class="mx-auto max-w-6xl space-y-6">
    <x-app.flash-message />
    <x-app.page-header :title="$plan->title" :description="$plan->description ?: __('planner.help')">
        <x-slot:actions>
            <flux:button :href="route('planner.index', ['context' => $plan->context->uuid])" variant="ghost">{{ __('planner.title') }}</flux:button>
            <flux:button :href="route('contexts.timeline', $plan->context)" variant="ghost">{{ __('planner.plan.timeline') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if ($plan->domainBlueprintVersion)
        <flux:callout>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('journeys.source') }}</div>
                    <div class="mt-1 font-medium">{{ __('journeys.source_version', ['name' => $plan->domainBlueprintVersion->blueprint->name, 'version' => $plan->domainBlueprintVersion->version]) }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($plan->domainBlueprintVersion->capabilities as $capability)
                        <flux:badge color="zinc">{{ __('journeys.capabilities.'.$capability) }}</flux:badge>
                    @endforeach
                </div>
            </div>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge>{{ __('planner.status.'.$plan->status->value) }}</flux:badge>
                        <span class="text-sm text-zinc-500">{{ $plan->timezone }}</span>
                    </div>
                    @if ($canManage)
                        <div class="flex flex-wrap gap-2">
                            @if ($plan->status === \App\PlanStatus::Active)
                                <flux:button wire:click="pause" size="sm" variant="ghost">{{ __('planner.plan.pause') }}</flux:button>
                                <flux:button wire:click="completePlan" size="sm" variant="ghost">{{ __('planner.plan.complete') }}</flux:button>
                                <flux:button wire:click="cancelPlan" size="sm" variant="danger">{{ __('planner.plan.cancel') }}</flux:button>
                            @elseif ($plan->status === \App\PlanStatus::Paused)
                                <flux:button wire:click="resume" size="sm" variant="primary">{{ __('planner.plan.resume') }}</flux:button>
                                <flux:button wire:click="completePlan" size="sm" variant="ghost">{{ __('planner.plan.complete') }}</flux:button>
                                <flux:button wire:click="cancelPlan" size="sm" variant="danger">{{ __('planner.plan.cancel') }}</flux:button>
                            @endif
                        </div>
                    @endif
                </div>
                @if ($originRelationship)
                    <div class="rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('planner.plan.origin') }}</div>
                        <a href="{{ route('relationships.show', $originRelationship) }}" class="mt-1 block font-medium hover:underline" dir="auto">{{ $originRelationship->title ?: $originRelationship->purposeConcept->displayLabel() }}</a>
                    </div>
                @endif
                @if ($originCommitment)
                    <div class="rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('planner.plan.origin') }}</div>
                        <a href="{{ route('commitments.show', $originCommitment) }}" class="mt-1 block font-medium hover:underline" dir="auto">{{ $originCommitment->title }}</a>
                    </div>
                @endif
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('planner.plan.occurrences') }}</flux:heading>
                <div class="space-y-3">
                    @forelse ($plan->occurrences->sortBy('scheduled_start_at') as $occurrence)
                        @php($windowState = $occurrence->windowState())
                        <article id="occurrence-{{ $occurrence->uuid }}" wire:key="plan-occurrence-{{ $occurrence->uuid }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:badge>{{ __('planner.window_state.'.$windowState->value) }}</flux:badge>
                                        <span class="text-xs text-zinc-500">{{ $occurrence->local_date->format('Y-m-d') }}</span>
                                    </div>
                                    <div class="text-sm">
                                        <span class="font-medium">{{ __('planner.plan.scheduled') }}:</span>
                                        {{ $occurrence->scheduled_start_at->setTimezone($plan->timezone)->format('Y-m-d H:i') }}
                                        → {{ $occurrence->scheduled_end_at->setTimezone($plan->timezone)->format('H:i') }}
                                    </div>
                                    @if ($occurrence->status === \App\PlanOccurrenceStatus::Scheduled)
                                        <div class="text-xs text-zinc-500">
                                            {{ __('planner.plan.execution_window') }}:
                                            {{ $occurrence->window_start_at->setTimezone($plan->timezone)->format('Y-m-d H:i') }}
                                            → {{ $occurrence->window_end_at->setTimezone($plan->timezone)->format('Y-m-d H:i') }}
                                        </div>
                                    @endif
                                    @if ($occurrence->actual_start_at || $occurrence->actual_end_at)
                                        <div class="text-sm text-zinc-600 dark:text-zinc-300">
                                            <span class="font-medium">{{ __('planner.plan.actual') }}:</span>
                                            {{ $occurrence->actual_start_at?->setTimezone($plan->timezone)->format('Y-m-d H:i') ?? '—' }}
                                            → {{ $occurrence->actual_end_at?->setTimezone($plan->timezone)->format('H:i') ?? '…' }}
                                        </div>
                                    @endif
                                    @error('execution.'.$occurrence->id)
                                        <div class="text-sm text-red-600 dark:text-red-400">{{ $message }}</div>
                                    @enderror
                                </div>
                                @if ($canParticipate)
                                    <div class="flex flex-wrap gap-2">
                                        @if ($plan->status === \App\PlanStatus::Active)
                                            @if ($occurrence->status === \App\PlanOccurrenceStatus::Scheduled)
                                                @if ($occurrence->canStart())
                                                    <flux:button wire:click="startOccurrence({{ $occurrence->id }})" size="sm" variant="primary">{{ __('planner.plan.start') }}</flux:button>
                                                @elseif ($windowState === \App\PlanOccurrenceWindowState::Upcoming)
                                                    <flux:button size="sm" variant="ghost" disabled>{{ __('planner.plan.wait_until_ready') }}</flux:button>
                                                @else
                                                    <flux:badge color="amber">{{ __('planner.plan.start_window_closed') }}</flux:badge>
                                                @endif
                                                <flux:button wire:click="skipOccurrence({{ $occurrence->id }})" size="sm" variant="ghost">{{ __('planner.plan.skip') }}</flux:button>
                                                <flux:button wire:click="cancelOccurrence({{ $occurrence->id }})" size="sm" variant="danger">{{ __('planner.plan.cancel_occurrence') }}</flux:button>
                                            @elseif ($occurrence->status === \App\PlanOccurrenceStatus::InProgress)
                                                <flux:button wire:click="completeOccurrence({{ $occurrence->id }})" size="sm" variant="primary">{{ __('planner.plan.finish') }}</flux:button>
                                                <flux:button wire:click="cancelOccurrence({{ $occurrence->id }})" size="sm" variant="danger">{{ __('planner.plan.cancel_occurrence') }}</flux:button>
                                            @endif
                                        @endif
                                        <flux:button wire:click="chooseEvidenceOccurrence({{ $occurrence->id }})" size="sm" variant="ghost">{{ __('planner.plan.attach_evidence') }}</flux:button>
                                    </div>
                                @endif
                            </div>
                            @if ($occurrence->assets->isNotEmpty() || $occurrence->evidenceReferences->isNotEmpty())
                                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                    @foreach ($occurrence->assets as $asset)
                                        <div class="break-all rounded-lg bg-zinc-50 p-3 text-xs dark:bg-zinc-900" wire:key="occurrence-asset-{{ $occurrence->id }}-{{ $asset->id }}">{{ $asset->original_filename }}</div>
                                    @endforeach
                                    @foreach ($occurrence->evidenceReferences as $reference)
                                        <a href="{{ route('content-evidence.show', $reference) }}" class="rounded-lg bg-zinc-50 p-3 text-xs hover:underline dark:bg-zinc-900" wire:key="occurrence-reference-{{ $occurrence->id }}-{{ $reference->id }}">{{ $reference->revision?->title ?: $reference->content?->activeRevision?->title ?: __('ui.content.untitled') }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @empty
                        <x-app.empty-state :title="__('planner.empty.list')" />
                    @endforelse
                </div>
            </flux:card>

            @if ($evidenceOccurrenceId !== null)
                <flux:card class="space-y-5">
                    <div>
                        <flux:heading size="lg">{{ __('planner.plan.select_evidence') }}</flux:heading>
                        <flux:text>{{ __('planner.plan.select_evidence_help') }}</flux:text>
                    </div>
                    @if ($availableAssets->isNotEmpty())
                        <div class="space-y-2">
                            <div class="text-sm font-medium">{{ __('planner.plan.existing_files') }}</div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($availableAssets as $asset)
                                    <label class="flex min-w-0 items-start gap-2 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                        <input type="checkbox" wire:model="assetIds" value="{{ $asset->id }}" class="mt-1">
                                        <span class="break-all">{{ $asset->original_filename }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($availableEvidenceReferences->isNotEmpty())
                        <div class="space-y-2">
                            <div class="text-sm font-medium">{{ __('planner.plan.existing_content_evidence') }}</div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($availableEvidenceReferences as $reference)
                                    <label class="flex min-w-0 items-start gap-2 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                                        <input type="checkbox" wire:model="evidenceReferenceIds" value="{{ $reference->id }}" class="mt-1">
                                        <span dir="auto">{{ $reference->revision?->title ?: $reference->content?->activeRevision?->title ?: __('ui.content.untitled') }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($canUploadEvidence)
                        <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                            <div class="text-sm font-medium">{{ __('planner.plan.upload_evidence') }}</div>
                            <div class="text-xs text-zinc-500">{{ __('planner.plan.upload_evidence_help') }}</div>
                            <input type="file" wire:model="evidenceUpload" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            @error('evidenceUpload') <div class="text-sm text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                            <flux:select wire:model="evidenceUploadRightsStatus" :label="__('media.rights_status')">
                                @foreach ($assetRightsStatuses as $status)
                                    <option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                    @if ($availableAssets->isEmpty() && $availableEvidenceReferences->isEmpty() && ! $canUploadEvidence)
                        <x-app.empty-state :title="__('planner.plan.no_evidence_available')" :description="__('planner.plan.no_evidence_available_help')" />
                    @endif
                    @error('evidence') <div class="text-sm text-red-600 dark:text-red-400">{{ $message }}</div> @enderror
                    <div class="flex justify-end">
                        <flux:button wire:click="attachEvidence" wire:loading.attr="disabled" wire:target="evidenceUpload,attachEvidence" variant="primary">{{ __('planner.plan.attach_evidence') }}</flux:button>
                    </div>
                </flux:card>
            @endif
        </div>
        <div class="space-y-6">
            <flux:card class="space-y-3">
                <flux:heading>{{ __('planner.plan.participants') }}</flux:heading>
                @foreach ($plan->participants as $participant)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <x-app.actor-identity :actor="$participant->actor" size="xs" />
                        <span class="text-zinc-500" dir="auto">{{ $participant->role }}</span>
                    </div>
                @endforeach
            </flux:card>
            <flux:card class="space-y-4">
                <flux:heading>{{ __('planner.plan.schedule_rules') }}</flux:heading>
                @foreach ($plan->scheduleRules as $rule)
                    <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ __('planner.frequency.'.$rule->frequency->value) }}</span>
                            <flux:badge size="sm">{{ $rule->status->value }}</flux:badge>
                        </div>
                        <div class="mt-2 text-zinc-500">{{ $rule->starts_on->format('Y-m-d') }} · {{ substr($rule->start_time, 0, 5) }} · {{ $rule->duration_minutes }} min</div>
                        @if ($rule->reminders->isNotEmpty())
                            <div class="mt-2 text-xs text-zinc-500">{{ __('planner.plan.reminders') }}: {{ $rule->reminders->pluck('minutes_before')->sort()->implode(', ') }} min</div>
                        @endif
                    </div>
                @endforeach
            </flux:card>
            <flux:callout>{{ __('planner.create.non_authority') }}</flux:callout>
        </div>
    </div>
</section>
