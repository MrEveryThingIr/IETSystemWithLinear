<section class="mx-auto max-w-3xl space-y-6">
    <x-app.page-header title="Group admission" :description="$admission->group->name.' · '.str($admission->status)->replace('_', ' ')->title()" />

    @error('admission')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror
    @error('agreements')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @can('update', $admission)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Your application</flux:heading>
                <flux:text>Application to join {{ $admission->group->name }}.</flux:text>
            </div>

            @if (in_array($admission->status, ['draft', 'clarification_required'], true))
                @if ($admission->status === 'clarification_required')
                    <flux:callout variant="warning">The reviewers requested clarification. Read their note in the history below, then reply and resubmit.</flux:callout>
                @endif
                <flux:textarea wire:model="note" label="Message to group reviewers" rows="4" />
                <div class="flex flex-col gap-2 sm:flex-row">
                    <flux:button wire:click="submit" variant="primary">{{ $admission->status === 'draft' ? 'Submit admission' : 'Reply and resubmit' }}</flux:button>
                    <flux:button wire:click="cancel" variant="ghost">Cancel application</flux:button>
                </div>
            @elseif ($admission->status === 'submitted')
                <flux:callout>Your application has been submitted. A group reviewer can now start review, request clarification, or reject it.</flux:callout>
                <flux:button wire:click="cancel" variant="ghost" size="sm">Withdraw application</flux:button>
            @elseif ($admission->status === 'under_review')
                <flux:callout>Your application is being reviewed. You will see the next decision and any reviewer note in the history below.</flux:callout>
            @elseif ($admission->status === 'approved')
                <flux:callout variant="success">Your application is approved. Accept any required agreement below; a reviewer can then finalize your membership.</flux:callout>
            @elseif ($admission->status === 'finalized')
                <flux:callout variant="success">Admission completed. Your group membership is active.</flux:callout>
                <flux:button :href="route('groups.show', $admission->group)" variant="primary">Open group</flux:button>
            @elseif ($admission->status === 'rejected')
                <flux:callout variant="danger">The group declined this application. Review the decision note in the history below.</flux:callout>
            @else
                <flux:callout>This application has been cancelled.</flux:callout>
            @endif
        </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">Required agreements</flux:heading>
            <flux:text>Only active agreements marked as required for admission appear here. Every listed version must be accepted before membership is finalized.</flux:text>
        </div>
        @forelse ($versions as $version)
            <div class="flex flex-col gap-3 border-b border-zinc-200 pb-4 last:border-0 last:pb-0 dark:border-zinc-700 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-2">
                    <flux:heading>{{ $version->agreement->name }} · version {{ $version->version }}</flux:heading>
                    <div class="whitespace-pre-wrap break-words text-sm leading-6">{{ $version->content }}</div>
                </div>
                @if ($acceptedVersionIds->contains($version->id))
                    <flux:badge color="green">Accepted</flux:badge>
                @elseif (in_array($admission->status, ['draft', 'submitted', 'clarification_required', 'under_review', 'approved'], true))
                    @can('update', $admission)
                        <flux:button wire:click="acceptVersion({{ $version->id }})" size="sm">Accept version</flux:button>
                    @else
                        <flux:badge>Awaiting applicant</flux:badge>
                    @endcan
                @endif
            </div>
        @empty
            <div class="space-y-3">
                <flux:text>No active agreements are currently required for this admission.</flux:text>
                @can('manageAgreements', $admission->group)
                    <flux:button :href="route('groups.agreements', $admission->group)" size="sm" variant="ghost">Manage group agreements</flux:button>
                @endcan
            </div>
        @endforelse
    </flux:card>

    @can('manageAdmissions', $admission->group)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Review {{ $admission->candidate->user?->username ?? 'applicant' }}</flux:heading>
                <flux:text>Read the application history below before choosing the next valid action.</flux:text>
            </div>

            @if (in_array($admission->status, ['submitted', 'under_review'], true))
                <flux:textarea wire:model="note" label="Reviewer note" rows="4" />
            @endif

            @if ($admission->status === 'submitted')
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <flux:button wire:click="review('under_review')" variant="primary">Start review</flux:button>
                    <flux:button wire:click="review('clarification_required')">Request clarification</flux:button>
                    <flux:button wire:click="review('rejected')" variant="danger">Reject</flux:button>
                </div>
            @elseif ($admission->status === 'under_review')
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <flux:button wire:click="review('approved')" variant="primary">Approve</flux:button>
                    <flux:button wire:click="review('clarification_required')">Request clarification</flux:button>
                    <flux:button wire:click="review('rejected')" variant="danger">Reject</flux:button>
                </div>
            @elseif ($admission->status === 'approved')
                <flux:button wire:click="finalize" variant="primary" class="w-full">Finalize membership</flux:button>
            @else
                <flux:callout>No reviewer action is available while this admission is {{ str($admission->status)->replace('_', ' ') }}.</flux:callout>
            @endif
        </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">Application history</flux:heading>
            <flux:text>Applicant messages, reviewer notes, agreement acceptances, and decisions are preserved here.</flux:text>
        </div>
        <div class="space-y-4">
            @forelse ($events as $event)
                <div class="border-l-2 border-zinc-200 pl-4 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ str($event->event)->replace(['admission.', 'agreement.', '_'], ['', '', ' '])->title() }}</span>
                        <flux:text class="text-sm">{{ $event->created_at->format('M j, Y H:i') }}</flux:text>
                    </div>
                    <flux:text class="text-sm">By {{ $event->actor?->user?->username ?? 'System' }}</flux:text>
                    @if ($event->note)
                        <div class="mt-2 whitespace-pre-wrap break-words rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">{{ $event->note }}</div>
                    @endif
                </div>
            @empty
                <flux:text>No application activity has been recorded yet.</flux:text>
            @endforelse
        </div>
    </flux:card>
</section>
