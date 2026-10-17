<div class="space-y-6" data-share-panel>
    @if ($status)
        <flux:callout variant="success" data-share-status>{{ $status }}</flux:callout>
    @endif

    @if (! $isPublished)
        <flux:callout variant="info" data-share-not-published>{{ __('public_content.share.not_published') }}</flux:callout>
    @elseif (! $canShare && ! $canManage)
        <flux:callout variant="info">{{ __('public_content.share.not_allowed') }}</flux:callout>
    @endif

    {{-- Anyone on the web --}}
    <section class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-share-public>
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <flux:icon name="globe-alt" class="mt-0.5 size-5 shrink-0 text-zinc-500" />
                <div class="min-w-0">
                    <h3 class="font-medium">{{ __('public_content.share.public_title') }}</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $indexEnabled ? __('public_content.share.public_help') : __('public_content.share.public_help_unlisted') }}</p>
                </div>
            </div>
            <flux:badge size="sm" :color="$isPublic ? 'green' : 'zinc'" data-share-public-state="{{ $isPublic ? 'public' : 'private' }}">
                {{ $isPublic ? __('public_content.share.public_on') : __('public_content.share.public_off') }}
            </flux:badge>
        </div>

        @if ($isPublic)
            <div x-data="{ copied: false, url: @js($publicUrl) }" class="flex items-center gap-2">
                <flux:input readonly :value="$publicUrl" class="min-w-0 flex-1" :aria-label="__('public_content.share.public_link')" data-share-public-url />
                <flux:button size="sm" x-on:click="(navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).catch(() => { const i = $root.querySelector('input'); i.select(); document.execCommand('copy') }); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-show="! copied">{{ __('public_content.share.copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('public_content.share.copied') }}</span>
                </flux:button>
                <flux:button size="sm" variant="ghost" :href="$publicUrl" target="_blank" icon="arrow-top-right-on-square" :aria-label="__('public_content.share.open')" />
            </div>
        @endif

        <div class="flex justify-end">
            @if ($isPublic && $canManage)
                <flux:button size="sm" variant="ghost" wire:click="setPublic(false)" data-share-make-private>{{ __('public_content.share.make_private') }}</flux:button>
            @elseif (! $isPublic && $canShare)
                <flux:button size="sm" variant="primary" wire:click="setPublic(true)" data-share-make-public>{{ __('public_content.share.make_public') }}</flux:button>
            @endif
        </div>
    </section>

    {{-- Invitation links --}}
    <section class="space-y-4" data-share-invitations>
        <div>
            <h3 class="font-medium">{{ __('public_content.share.invitations_title') }}</h3>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('public_content.share.invitations_help') }}</p>
        </div>

        @if ($canShare)
            <form wire:submit="createInvitation" class="space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60" data-share-invite-form>
                <flux:input wire:model="inviteeName" :label="__('public_content.invite.invitee_name')" :placeholder="__('public_content.invite.invitee_placeholder')" maxlength="120" required />
                <flux:textarea wire:model="inviteMessage" :label="__('public_content.invite.message')" :placeholder="__('public_content.invite.message_placeholder')" rows="2" maxlength="1000" />
                <flux:select wire:model="expiresInDays" :label="__('public_content.invite.expires')">
                    @foreach ($expiryOptions as $days)
                        <option value="{{ $days }}">{{ __('public_content.invite.expires_options.'.$days) }}</option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" size="sm" wire:loading.attr="disabled" wire:target="createInvitation">{{ __('public_content.invite.create') }}</flux:button>
                </div>
            </form>
        @endif

        @if ($canManage)
            @forelse ($invitations as $invitation)
                @php
                    $state = $invitation->isRevoked() ? 'revoked' : ($invitation->isExpired() ? 'expired' : 'active');
                    $link = $state === 'active' ? $invitation->url() : null;
                @endphp
                <div wire:key="invitation-{{ $invitation->uuid }}" class="space-y-2 rounded-xl border p-3 {{ $createdInvitationUuid === $invitation->uuid ? 'border-green-400 dark:border-green-700' : 'border-zinc-200 dark:border-zinc-700' }}" data-share-invitation="{{ $state }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate font-medium" dir="auto">{{ $invitation->invitee_name }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ trans_choice('public_content.share.views', $invitation->view_count, ['count' => $invitation->view_count]) }}
                                @if ($invitation->last_viewed_at)
                                    · {{ __('public_content.share.last_viewed') }} <x-app.local-datetime :value="$invitation->last_viewed_at" />
                                @endif
                                ·
                                @if ($invitation->expires_at)
                                    {{ __('public_content.share.until') }} <x-app.local-date :value="$invitation->expires_at->copy()->setTimezone($timezone)" />
                                @else
                                    {{ __('public_content.share.never_expires') }}
                                @endif
                            </div>
                        </div>
                        <flux:badge size="sm" :color="$state === 'active' ? 'green' : 'zinc'">{{ __('public_content.share.status.'.$state) }}</flux:badge>
                    </div>
                    @if ($link)
                        <div x-data="{ copied: false, url: @js($link) }" class="flex items-center gap-2">
                            <flux:input readonly :value="$link" size="sm" class="min-w-0 flex-1" data-share-invitation-url />
                            <flux:button size="sm" x-on:click="(navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).catch(() => { const i = $root.querySelector('input'); i.select(); document.execCommand('copy') }); copied = true; setTimeout(() => copied = false, 2000)">
                                <span x-show="! copied">{{ __('public_content.share.copy') }}</span>
                                <span x-show="copied" x-cloak>{{ __('public_content.share.copied') }}</span>
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="revoke('{{ $invitation->uuid }}')" wire:confirm="{{ __('public_content.share.revoke_confirm') }}" data-share-revoke>{{ __('public_content.share.revoke') }}</flux:button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-zinc-500">{{ __('public_content.share.none') }}</p>
            @endforelse
        @endif
    </section>
</div>
