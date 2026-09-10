<section class="mx-auto max-w-3xl space-y-6">
    <x-app.page-header title="Group admission" :description="$admission->group->name.' · '.str($admission->status)->replace('_', ' ')->title()" />

    @error('admission')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @can('update', $admission)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Your application</flux:heading>
                <flux:text>Add a message for the group reviewers, then submit when you are ready.</flux:text>
            </div>
            <flux:textarea wire:model="note" label="Message" rows="3" />
            @if (in_array($admission->status, ['draft', 'clarification_required'], true))
                <div class="flex flex-col gap-2 sm:flex-row">
                    <flux:button wire:click="submit" variant="primary">Submit admission</flux:button>
                    <flux:button wire:click="cancel" variant="ghost">Cancel application</flux:button>
                </div>
            @elseif (in_array($admission->status, ['submitted', 'under_review'], true))
                <flux:callout>Your application is with the group reviewers.</flux:callout>
            @else
                <flux:callout>Your application is {{ str($admission->status)->replace('_', ' ') }}.</flux:callout>
            @endif
        </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">Required agreements</flux:heading>
            <flux:text>Accept every active required agreement before the admission can be finalized.</flux:text>
        </div>
        @forelse ($versions as $version)
            <div class="flex flex-col gap-3 border-b border-zinc-200 pb-4 last:border-0 last:pb-0 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading>{{ $version->agreement->name }} v{{ $version->version }}</flux:heading>
                    <flux:text>{{ $version->content }}</flux:text>
                </div>
                @if ($acceptedVersionIds->contains($version->id))
                    <flux:badge color="green">Accepted</flux:badge>
                @else
                    @can('update', $admission)
                        <flux:button wire:click="acceptVersion({{ $version->id }})" size="sm">Accept version</flux:button>
                    @else
                        <flux:badge>Awaiting candidate</flux:badge>
                    @endcan
                @endif
            </div>
        @empty
            <flux:text>No active agreements are required.</flux:text>
        @endforelse
    </flux:card>

    @can('manageAdmissions', $admission->group)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">Review application</flux:heading>
                <flux:text>Only actions valid for the current admission state are available.</flux:text>
            </div>
            <flux:textarea wire:model="note" label="Review note" rows="3" />

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
</section>
