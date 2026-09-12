<section class="mx-auto max-w-3xl space-y-6">
    <x-app.page-header :title="__('ui.admission.title')" :description="$admission->group->name.' · '.__('ui.status.'.$admission->status)" />

    @php
        $pipeline = ['draft', 'submitted', 'under_review', 'approved', 'finalized'];
        $currentStep = array_search($admission->status, $pipeline, true);
    @endphp
    <nav aria-label="Admission progress" class="grid grid-cols-2 gap-2 sm:grid-cols-5">
        @foreach ($pipeline as $index => $status)
            <div class="rounded-xl border px-3 py-2 text-center text-sm {{ $currentStep !== false && $index <= $currentStep ? 'border-indigo-500 bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300' : 'border-zinc-200 text-zinc-500 dark:border-zinc-700' }}">
                <span class="block text-xs opacity-70">{{ $index + 1 }}</span>
                {{ __('ui.status.'.$status) }}
            </div>
        @endforeach
    </nav>

    @error('admission')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror
    @error('agreements')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @can('update', $admission)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('ui.admission.your_application') }}</flux:heading>
                <flux:text>{{ __('ui.admission.application_to_join', ['group' => $admission->group->name]) }}</flux:text>
            </div>

            @if (in_array($admission->status, ['draft', 'clarification_required'], true))
                @if ($admission->status === 'clarification_required')
                    <flux:callout variant="warning">{{ __('ui.admission.clarification_help') }}</flux:callout>
                @endif
                <flux:textarea wire:model="note" :label="__('ui.admission.message_to_reviewers')" rows="4" />
                <div class="flex flex-col gap-2 sm:flex-row">
                    <flux:button wire:click="submit" variant="primary">{{ $admission->status === 'draft' ? __('ui.admission.submit') : __('ui.admission.reply_submit') }}</flux:button>
                    <flux:button wire:click="cancel" variant="ghost">{{ __('ui.admission.cancel') }}</flux:button>
                </div>
            @elseif ($admission->status === 'submitted')
                <flux:callout>{{ __('ui.admission.submitted_help') }}</flux:callout>
                <flux:button wire:click="cancel" variant="ghost" size="sm">{{ __('ui.admission.withdraw') }}</flux:button>
            @elseif ($admission->status === 'under_review')
                <flux:callout>{{ __('ui.admission.reviewing_help') }}</flux:callout>
            @elseif ($admission->status === 'approved')
                <flux:callout variant="success">{{ __('ui.admission.approved_help') }}</flux:callout>
            @elseif ($admission->status === 'finalized')
                <flux:callout variant="success">{{ __('ui.admission.finalized_help') }}</flux:callout>
                <flux:button :href="route('groups.show', $admission->group)" variant="primary">{{ __('ui.groups.open') }}</flux:button>
            @elseif ($admission->status === 'rejected')
                <flux:callout variant="danger">{{ __('ui.admission.rejected_help') }}</flux:callout>
            @else
                <flux:callout>{{ __('ui.admission.cancelled_help') }}</flux:callout>
            @endif
        </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('ui.admission.required_agreements') }}</flux:heading>
            <flux:text>{{ __('ui.admission.required_help') }}</flux:text>
        </div>
        @forelse ($versions as $version)
            <div class="flex flex-col gap-3 border-b border-zinc-200 pb-4 last:border-0 last:pb-0 dark:border-zinc-700 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-2">
                    <flux:heading>{{ $version->agreement->name }} · {{ __('ui.common.version', ['version' => $version->version]) }}</flux:heading>
                    <details class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                        <summary class="cursor-pointer font-medium">{{ __('ui.common.version', ['version' => $version->version]) }}</summary>
                        <div class="mt-3 whitespace-pre-wrap break-words text-sm leading-6" dir="auto">{{ $version->content }}</div>
                    </details>
                </div>
                @if ($acceptedVersionIds->contains($version->id))
                    <flux:badge color="green">{{ __('ui.admission.accepted') }}</flux:badge>
                @elseif (in_array($admission->status, ['draft', 'submitted', 'clarification_required', 'under_review', 'approved'], true))
                    @can('update', $admission)
                        <flux:button wire:click="acceptVersion({{ $version->id }})" size="sm">{{ __('ui.admission.accept_version') }}</flux:button>
                    @else
                        <flux:badge>{{ __('ui.admission.awaiting_applicant') }}</flux:badge>
                    @endcan
                @endif
            </div>
        @empty
            <div class="space-y-3">
                <flux:text>{{ __('ui.admission.no_required_agreements') }}</flux:text>
                @can('manageAgreements', $admission->group)
                    <flux:button :href="route('groups.agreements', $admission->group)" size="sm" variant="ghost">{{ __('ui.groups.agreements') }}</flux:button>
                @endcan
            </div>
        @endforelse
    </flux:card>

    @can('manageAdmissions', $admission->group)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('ui.admission.review_application', ['username' => $admission->candidate->user?->username ?? __('ui.common.unknown_account')]) }}</flux:heading>
                <flux:text>{{ __('ui.admission.review_help') }}</flux:text>
            </div>

            @if (in_array($admission->status, ['submitted', 'under_review'], true))
                <flux:textarea wire:model="note" :label="__('ui.admission.reviewer_note')" rows="4" />
            @endif

            @if ($admission->status === 'submitted')
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <flux:button wire:click="review('under_review')" variant="primary">{{ __('ui.admission.start_review') }}</flux:button>
                    <flux:button wire:click="review('clarification_required')">{{ __('ui.admission.request_clarification') }}</flux:button>
                    <flux:button wire:click="review('rejected')" variant="danger">{{ __('ui.admission.reject') }}</flux:button>
                </div>
            @elseif ($admission->status === 'under_review')
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <flux:button wire:click="review('approved')" variant="primary">{{ __('ui.admission.approve') }}</flux:button>
                    <flux:button wire:click="review('clarification_required')">{{ __('ui.admission.request_clarification') }}</flux:button>
                    <flux:button wire:click="review('rejected')" variant="danger">{{ __('ui.admission.reject') }}</flux:button>
                </div>
            @elseif ($admission->status === 'approved')
                <flux:button wire:click="finalize" variant="primary" class="w-full">{{ __('ui.admission.finalize') }}</flux:button>
            @else
                <flux:callout>{{ __('ui.admission.no_reviewer_action', ['status' => __('ui.status.'.$admission->status)]) }}</flux:callout>
            @endif
        </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('ui.admission.history') }}</flux:heading>
            <flux:text>{{ __('ui.admission.history_help') }}</flux:text>
        </div>
        <div class="space-y-4">
            @forelse ($events as $event)
                <div class="border-l-2 border-zinc-200 pl-4 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ __('ui.events.'.str_replace('.', '_', $event->event)) }}</span>
                        <flux:text class="text-sm">{{ $event->created_at->timezone($admission->group->timezone ?: 'UTC')->translatedFormat('M j, Y H:i') }} {{ $admission->group->timezone ?: 'UTC' }}</flux:text>
                    </div>
                    <flux:text class="text-sm">{{ __('ui.admission.by', ['username' => $event->actor?->user?->username ?? __('ui.common.system')]) }}</flux:text>
                    @if ($event->note)
                        <div class="mt-2 whitespace-pre-wrap break-words rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">{{ $event->note }}</div>
                    @endif
                </div>
            @empty
                <flux:text>{{ __('ui.admission.no_history') }}</flux:text>
            @endforelse
        </div>
    </flux:card>
</section>
