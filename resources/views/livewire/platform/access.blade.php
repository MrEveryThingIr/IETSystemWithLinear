<section class="space-y-8">
    <x-app.page-header title="Platform access" description="Request platform-level capabilities and review pending requests." />

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="lg">Create groups</flux:heading>
                <flux:text>Creating a new group is a platform capability. Existing group membership permissions remain separate.</flux:text>
            </div>
            @if ($canCreateGroups)
                <flux:badge color="green">Granted</flux:badge>
            @elseif ($myRequests->firstWhere('status', 'pending'))
                <flux:badge color="amber">Pending review</flux:badge>
            @else
                <flux:badge>Not granted</flux:badge>
            @endif
        </div>

        @unless ($canCreateGroups || $myRequests->firstWhere('status', 'pending'))
            <flux:textarea wire:model="requestReason" label="Reason (optional)" rows="3" placeholder="Tell the platform administrator why you need to create groups." />
            <flux:button wire:click="requestGroupCreation" wire:loading.attr="disabled" wire:target="requestGroupCreation" variant="primary">
                Request group-creation access
            </flux:button>
        @endunless
    </flux:card>

    @if ($myRequests->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">Your requests</flux:heading>
            @foreach ($myRequests as $accessRequest)
                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <flux:text class="font-medium">{{ str($accessRequest->role->value)->replace('_', ' ')->title() }}</flux:text>
                        <flux:badge>{{ ucfirst($accessRequest->status) }}</flux:badge>
                    </div>
                    @if ($accessRequest->reason)
                        <flux:text>{{ $accessRequest->reason }}</flux:text>
                    @endif
                    @if ($accessRequest->review_note)
                        <flux:text>Reviewer note: {{ $accessRequest->review_note }}</flux:text>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif

    @if ($canManagePlatformAccess)
        <div class="space-y-3">
            <flux:heading size="lg">Pending platform-access requests</flux:heading>
            @forelse ($pendingRequests as $accessRequest)
                <flux:card class="space-y-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <flux:heading>{{ $accessRequest->user->username }}</flux:heading>
                            <flux:text>{{ $accessRequest->user->email }} · {{ str($accessRequest->role->value)->replace('_', ' ')->title() }}</flux:text>
                        </div>
                        <flux:badge>Pending</flux:badge>
                    </div>
                    @if ($accessRequest->reason)
                        <flux:text>{{ $accessRequest->reason }}</flux:text>
                    @endif
                    <flux:textarea wire:model="reviewNotes.{{ $accessRequest->id }}" label="Reviewer note (optional)" rows="2" />
                    <div class="flex gap-2">
                        <flux:button wire:click="review({{ $accessRequest->id }}, true)" variant="primary">Approve</flux:button>
                        <flux:button wire:click="review({{ $accessRequest->id }}, false)" variant="ghost">Reject</flux:button>
                    </div>
                </flux:card>
            @empty
                <x-app.empty-state title="No pending requests" description="New platform-access requests will appear here." />
            @endforelse
        </div>
    @endif
</section>
