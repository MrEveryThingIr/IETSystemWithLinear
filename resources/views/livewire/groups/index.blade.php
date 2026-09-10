<section class="space-y-8">
    <x-app.page-header title="Groups" description="Your memberships, received invitations, and invitation activity in one place.">
        <x-slot:actions><flux:button :href="route('groups.create')" variant="primary" icon="plus">Create Group</flux:button></x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success" class="break-all">{{ session('status') }}</flux:callout>
    @endif

    <div class="space-y-4">
        <flux:heading size="lg">Your groups</flux:heading>
        @if ($memberships->isEmpty())
            <x-app.empty-state title="No active memberships" description="Open a group invitation to start an admission." />
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($memberships as $membership)
                    <flux:card class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <flux:heading size="lg">{{ $membership->group->name }}</flux:heading>
                            <flux:badge>{{ $roles[$membership->group_id] }}</flux:badge>
                        </div>
                        <flux:text>{{ $membership->group->description ?: 'No description yet.' }}</flux:text>
                        <flux:button :href="route('groups.show', $membership->group)" class="w-full sm:w-auto" size="sm" variant="primary">Open group</flux:button>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    @if ($receivedInvitations->isNotEmpty())
        <div class="space-y-4">
            <flux:heading size="lg">Invitations you received</flux:heading>
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($receivedInvitations as $admission)
                    <flux:card class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1">
                            <flux:heading>{{ $admission->group->name }}</flux:heading>
                            <flux:text>
                                Invited by {{ $admission->sourceInvitation->inviter->user?->username ?? 'a group member' }}
                            </flux:text>
                            <flux:badge>{{ str($admission->status)->replace('_', ' ')->title() }}</flux:badge>
                        </div>
                        <flux:button :href="route('admissions.show', $admission)" size="sm">View admission</flux:button>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif

    @if ($sentInvitations->isNotEmpty())
        <div class="space-y-4">
            <flux:heading size="lg">People you invited</flux:heading>
            <div class="space-y-3">
                @foreach ($sentInvitations as $invitation)
                    <flux:card class="space-y-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <flux:heading>{{ $invitation->group->name }}</flux:heading>
                                <flux:text>{{ $invitation->email ?: 'Reusable private link' }} · {{ $invitation->acceptances_count }} accepted</flux:text>
                            </div>
                            <flux:button :href="route('groups.invitations', $invitation->group)" size="sm" variant="ghost">Manage</flux:button>
                        </div>
                        @if ($invitation->admissions->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach ($invitation->admissions as $admission)
                                    <flux:badge>{{ $admission->candidate->user?->username ?? 'Unknown account' }} · {{ str($admission->status)->replace('_', ' ')->title() }}</flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif
</section>
