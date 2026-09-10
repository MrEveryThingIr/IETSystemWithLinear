<section class="mx-auto max-w-4xl space-y-6">
    <x-app.page-header :title="$group->name.' invitations'" description="Create, monitor, and revoke invitations from one permanent workspace.">
        <x-slot:actions>
            <flux:button :href="route('groups.show', $group)" variant="ghost">Back to group</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">Create an invitation</flux:heading>
            <flux:text>Reserve it for one email address, or leave the email empty to create a reusable private link.</flux:text>
        </div>
        <form wire:submit="create" class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_10rem_auto] sm:items-end">
            <flux:input wire:model="email" type="email" label="Target email (optional)" />
            <flux:input wire:model="maxUses" type="number" label="Maximum uses" min="1" />
            <flux:button type="submit" variant="primary">Create invitation</flux:button>
        </form>
    </flux:card>

    <div class="space-y-4">
        @forelse ($invitations as $invitation)
            <flux:card class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge :color="$invitation->revoked_at ? 'red' : ($invitation->expires_at?->isPast() ? 'amber' : 'green')">
                                {{ $invitation->revoked_at ? 'Revoked' : ($invitation->expires_at?->isPast() ? 'Expired' : 'Active') }}
                            </flux:badge>
                            <flux:text>{{ $invitation->acceptances_count }}/{{ $invitation->max_uses ?? '∞' }} uses</flux:text>
                            @if ($invitation->email)<flux:badge>{{ $invitation->email }}</flux:badge>@endif
                        </div>
                        <a href="{{ route('invitations.show', $invitation->token) }}" class="block break-all text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                            {{ route('invitations.show', $invitation->token) }}
                        </a>
                        <flux:text class="text-sm">Created {{ $invitation->created_at->diffForHumans() }} · expires {{ $invitation->expires_at?->diffForHumans() ?? 'never' }}</flux:text>
                    </div>
                    @if (! $invitation->revoked_at)
                        <flux:button wire:click="revoke({{ $invitation->id }})" variant="danger" size="sm">Revoke</flux:button>
                    @endif
                </div>

                @if ($invitation->admissions->isNotEmpty())
                    <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                        <p class="mb-2 text-sm font-medium">Invitees</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($invitation->admissions as $admission)
                                <a href="{{ route('admissions.show', $admission) }}" class="rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <flux:badge>
                                        {{ $admission->candidate->user?->username ?? 'Unknown account' }} · {{ str($admission->status)->replace('_', ' ')->title() }}
                                    </flux:badge>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </flux:card>
        @empty
            <x-app.empty-state title="No invitations yet" description="Create the first invitation for this group." />
        @endforelse
    </div>
</section>
