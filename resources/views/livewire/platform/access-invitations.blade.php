<section class="mx-auto max-w-5xl space-y-6">
    <x-app.page-header :title="__('access.admin.title')" :description="__('access.admin.help')" />

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @if ($createdInvitationUrl)
        <flux:callout variant="success" class="space-y-2" x-data="{ copied: false }">
            <p class="font-medium">{{ __('access.admin.copy_now') }}</p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input x-ref="accessUrl" readonly value="{{ $createdInvitationUrl }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 font-mono text-xs dark:border-zinc-700 dark:bg-zinc-900" />
                <flux:button type="button" x-on:click="navigator.clipboard.writeText($refs.accessUrl.value).then(() => copied = true)" variant="primary">
                    {{ __('access.admin.copy') }}
                </flux:button>
            </div>
            <span x-show="copied" role="status" class="text-sm">{{ __('access.admin.copied') }}</span>
        </flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('access.admin.create') }}</flux:heading>
            <flux:text>{{ __('access.admin.create_help') }}</flux:text>
        </div>
        <form wire:submit="create" class="grid gap-4 md:grid-cols-[1fr_9rem_9rem_auto] md:items-end">
            <flux:input wire:model.live="email" type="email" :label="__('access.admin.email')" />
            <flux:input wire:model="maxUses" type="number" min="1" max="1000" :disabled="filled($email)" :label="__('access.admin.max_uses')" />
            <flux:input wire:model="expiresInDays" type="number" min="1" max="90" :label="__('access.admin.expires')" />
            <flux:button type="submit" variant="primary">{{ __('access.admin.create_button') }}</flux:button>
        </form>
    </flux:card>

    <div class="space-y-3">
        @forelse ($invitations as $invitation)
            <flux:card class="space-y-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1">
                        <div class="flex flex-wrap gap-2">
                            <flux:badge :color="$invitation->state() === 'available' ? 'green' : 'zinc'">{{ __('access.states.'.$invitation->state()) }}</flux:badge>
                            @if ($invitation->maskedEmail())<flux:badge>{{ $invitation->maskedEmail() }}</flux:badge>@endif
                            <flux:badge>{{ $invitation->uses_count }}/{{ $invitation->max_uses }}</flux:badge>
                        </div>
                        <flux:text class="text-sm">{{ __('access.admin.expires_label') }} @if ($invitation->expires_at)<x-app.local-datetime :value="$invitation->expires_at" />@else — @endif</flux:text>
                    </div>
                    @if ($invitation->revoked_at === null && $invitation->state() === 'available')
                        <flux:button wire:click="revoke({{ $invitation->id }})" variant="danger" size="sm">{{ __('access.admin.revoke') }}</flux:button>
                    @endif
                </div>

                @if ($invitation->acceptances->isNotEmpty())
                    <div class="border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                        {{ __('access.admin.registered_count', ['count' => $invitation->acceptances->count()]) }}
                    </div>
                @endif
            </flux:card>
        @empty
            <x-app.empty-state :title="__('access.admin.none')" :description="__('access.admin.none_help')" />
        @endforelse
        {{ $invitations->links() }}
    </div>
</section>
