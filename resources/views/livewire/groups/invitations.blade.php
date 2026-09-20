<section class="mx-auto max-w-4xl space-y-6">
    <x-app.page-header :title="__('ui.invitations.title', ['group' => $group->name])" :description="__('ui.invitations.workspace_help')">
        <x-slot:actions>
            <flux:button :href="route('groups.show', $group)" variant="ghost">{{ __('ui.common.back_to_group') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @if ($createdInvitationUrl)
        <flux:callout variant="success" class="space-y-2" x-data="{ copied: false }">
            <p class="font-medium">{{ __('ui.invitations.copy_now') }}</p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input x-ref="invitationUrl" x-on:focus="$event.target.select()" readonly value="{{ $createdInvitationUrl }}" aria-label="{{ __('ui.invitations.private_link') }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 font-mono text-xs text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100" />
                <button type="button" x-on:click="$refs.invitationUrl.select(); if (navigator.clipboard?.writeText) { navigator.clipboard.writeText($refs.invitationUrl.value).then(() => copied = true).catch(() => copied = document.execCommand('copy')); } else { copied = document.execCommand('copy'); }" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">{{ __('ui.invitations.copy_link') }}</button>
            </div>
            <span x-show="copied" role="status" class="text-sm">{{ __('ui.invitations.link_copied') }}</span>
        </flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('ui.invitations.create') }}</flux:heading>
            <flux:text>{{ __('ui.invitations.create_help') }}</flux:text>
        </div>
        <form wire:submit="create" class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_10rem_auto] sm:items-end">
            <flux:input wire:model="email" type="email" :label="__('ui.invitations.target_email')" />
            <flux:input wire:model="maxUses" type="number" :label="__('ui.invitations.maximum_uses')" min="1" />
            <flux:button type="submit" variant="primary">{{ __('ui.invitations.create_button') }}</flux:button>
        </form>
    </flux:card>

    <div class="space-y-4">
        @forelse ($invitations as $invitation)
            <flux:card class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge :color="$invitation->revoked_at ? 'red' : ($invitation->expires_at?->isPast() ? 'amber' : 'green')">
                                {{ $invitation->revoked_at ? __('ui.common.revoked') : ($invitation->expires_at?->isPast() ? __('ui.common.expired') : __('ui.common.active')) }}
                            </flux:badge>
                            <flux:text>{{ __('ui.invitations.uses', ['used' => $invitation->acceptances_count, 'maximum' => $invitation->max_uses ?? '∞']) }}</flux:text>
                            @if ($invitation->email)<flux:badge>{{ $invitation->maskedEmail() }}</flux:badge>@endif
                        </div>
                        <flux:text class="text-sm">{{ __('ui.invitations.secret_not_recoverable') }}</flux:text>
                        <flux:text class="text-sm">{{ __('ui.invitations.created_expires', ['created' => $invitation->created_at->diffForHumans(), 'expires' => $invitation->expires_at?->diffForHumans() ?? __('ui.invitations.never')]) }}</flux:text>
                    </div>
                    @if (! $invitation->revoked_at)
                        <flux:button wire:click="revoke({{ $invitation->id }})" variant="danger" size="sm">{{ __('ui.invitations.revoke') }}</flux:button>
                    @endif
                </div>

                @if ($invitation->admissions->isNotEmpty())
                    <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                        <p class="mb-2 text-sm font-medium">{{ __('ui.invitations.invitees') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($invitation->admissions as $admission)
                                <a href="{{ route('admissions.show', $admission) }}" class="rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <flux:badge>
                                        {{ $admission->candidate->user?->username ?? __('ui.common.unknown_account') }} &middot; {{ __('ui.status.'.$admission->status) }}
                                    </flux:badge>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </flux:card>
        @empty
            <x-app.empty-state :title="__('ui.invitations.none')" :description="__('ui.invitations.none_help')" />
        @endforelse
        {{ $invitations->links() }}
    </div>
</section>
