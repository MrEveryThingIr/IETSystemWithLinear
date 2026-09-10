<section class="mx-auto max-w-4xl space-y-6 px-4 sm:px-0">
    <x-app.page-header :title="$group->name.' agreements'" description="Create immutable versions, review them, and choose when an approved version becomes active.">
        <x-slot:actions>
            <flux:button :href="route('groups.show', $group)" variant="ghost">Back to group</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <flux:callout icon="information-circle">
        Draft → proposed → approved → active. Activating a version supersedes the currently active version while preserving its history.
    </flux:callout>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">Create a group agreement</flux:heading>
            <flux:text>The initial text is saved as version 1. It does not affect applicants until you approve and activate it.</flux:text>
        </div>
        <form wire:submit="create" class="space-y-3">
            <flux:input wire:model="name" label="Agreement name" />
            <flux:textarea wire:model="content" label="Version 1 terms" rows="6" />
            <flux:checkbox wire:model="required" label="Applicants must accept the active version before membership" />
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto">Create agreement</flux:button>
        </form>
    </flux:card>

    @forelse ($agreements as $agreement)
        <flux:card class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ $agreement->name }}</flux:heading>
                    <flux:text>{{ $agreement->required_for_admission ? 'Required for admission' : 'Optional agreement' }}</flux:text>
                </div>
                <flux:button wire:click="startRevision({{ $agreement->id }})" size="sm" variant="ghost">Create new version</flux:button>
            </div>

            <div class="space-y-4">
                @foreach ($agreement->versions as $version)
                    <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading>Version {{ $version->version }}</flux:heading>
                                <flux:badge :color="match ($version->status) { 'active' => 'green', 'rejected' => 'red', 'scheduled' => 'blue', 'approved' => 'indigo', default => 'zinc' }">
                                    {{ str($version->status)->replace('_', ' ')->title() }}
                                </flux:badge>
                            </div>
                            @if ($version->effective_from)
                                <flux:text class="text-sm">Effective {{ $version->effective_from->format('M j, Y H:i') }}</flux:text>
                            @endif
                        </div>

                        <div class="whitespace-pre-wrap break-words rounded-lg bg-zinc-50 p-3 text-sm leading-6 dark:bg-zinc-800">{{ $version->content }}</div>

                        @if ($version->rationale)
                            <flux:text><span class="font-medium">Revision rationale:</span> {{ $version->rationale }}</flux:text>
                        @endif
                        @if ($version->decision_note)
                            <flux:text><span class="font-medium">Decision note:</span> {{ $version->decision_note }}</flux:text>
                        @endif

                        @if ($version->status === 'draft')
                            <flux:button wire:click="propose({{ $version->id }})" size="sm" variant="primary">Submit for approval</flux:button>
                        @elseif ($version->status === 'proposed')
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <flux:button wire:click="approve({{ $version->id }})" size="sm" variant="primary">Approve</flux:button>
                                <flux:button wire:click="requestClarification({{ $version->id }})" size="sm">Request clarification</flux:button>
                                <flux:button wire:click="reject({{ $version->id }})" size="sm" variant="danger">Reject</flux:button>
                            </div>
                        @elseif (in_array($version->status, ['approved', 'scheduled'], true))
                            <div class="space-y-3">
                                <flux:button wire:click="activate({{ $version->id }})" size="sm" variant="primary">Make active now</flux:button>
                                @if ($version->status === 'approved')
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                        <flux:input wire:model="effectiveFrom" type="datetime-local" label="Activation time" />
                                        <flux:input wire:model="effectiveUntil" type="datetime-local" label="End time (optional)" />
                                        <flux:button wire:click="schedule({{ $version->id }})" size="sm">Schedule</flux:button>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($revisingAgreementId === $agreement->id)
                <form wire:submit="revise({{ $agreement->id }})" class="space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800">
                    <flux:heading>Create the next version</flux:heading>
                    <flux:textarea wire:model="revisionContent" label="Revised terms" rows="6" />
                    <flux:textarea wire:model="revisionRationale" label="Why is this version needed?" rows="3" />
                    <flux:checkbox wire:model="reacceptanceRequired" label="Existing members must accept this version when activated" />
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <flux:button type="submit" variant="primary">Save draft version</flux:button>
                        <flux:button wire:click="cancelRevision" variant="ghost">Cancel</flux:button>
                    </div>
                </form>
            @endif
        </flux:card>
    @empty
        <x-app.empty-state title="No group agreements" description="Create the first version above. Applicants will see it only after it becomes active." />
    @endforelse

    @if ($agreements->isNotEmpty())
        <flux:card class="space-y-2">
            <flux:heading>Review notes</flux:heading>
            <flux:text>This note is used when requesting clarification or rejecting a proposed version.</flux:text>
            <flux:textarea wire:model="decisionNote" label="Decision or clarification note" rows="3" />
        </flux:card>
    @endif
</section>
