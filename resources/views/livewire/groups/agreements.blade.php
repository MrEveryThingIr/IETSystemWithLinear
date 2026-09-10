<section class="mx-auto max-w-3xl space-y-6 px-4 sm:px-0">
    <x-app.page-header :title="$group->name.' agreements'" />
    <flux:card class="space-y-3">
        <form wire:submit="create" class="space-y-3">
            <flux:input wire:model="name" label="Agreement name" />
            <flux:textarea wire:model="content" label="Initial version" rows="5" />
            <flux:checkbox wire:model="required" label="Required for admission" />
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto">Create agreement</flux:button>
        </form>
    </flux:card>
    @foreach($agreements as $agreement)
        <flux:card class="space-y-4">
            <flux:heading>{{ $agreement->name }}</flux:heading>
            @foreach($agreement->versions as $version)
                <div class="space-y-2 border-b pb-3">
                    <flux:text>v{{ $version->version }} · {{ $version->status }}</flux:text>
                    @if($version->status === 'draft')<flux:button wire:click="propose({{ $version->id }})" size="sm">Submit proposal</flux:button>@endif
                    @if($version->status === 'proposed')<div class="flex flex-col gap-2 sm:flex-row"><flux:button wire:click="approve({{ $version->id }})" size="sm">Approve</flux:button><flux:button wire:click="requestClarification({{ $version->id }})" size="sm">Return for clarification</flux:button><flux:button wire:click="reject({{ $version->id }})" size="sm">Reject</flux:button></div>@endif
                    @if($version->status === 'approved')<flux:button wire:click="schedule({{ $version->id }})" size="sm">Schedule future activation</flux:button>@endif
                </div>
            @endforeach
            <div class="space-y-2"><flux:textarea wire:model="content" label="Revision content" rows="3" /><flux:textarea wire:model="rationale" label="Rationale" rows="2" /><flux:checkbox wire:model="reacceptanceRequired" label="Require members to accept this version again" /><flux:button wire:click="revise({{ $agreement->id }})" class="w-full sm:w-auto">Create revision</flux:button></div>
        </flux:card>
    @endforeach
    <flux:card class="space-y-2"><flux:textarea wire:model="decisionNote" label="Decision or clarification note" rows="2" /><flux:input wire:model="effectiveFrom" type="datetime-local" label="Effective from" /><flux:input wire:model="effectiveUntil" type="datetime-local" label="Effective until (optional)" /></flux:card>
</section>
