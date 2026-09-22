<section class="space-y-6 rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold">{{ __('ui.profile_sharing.title') }}</h2>
            <p class="mt-1 max-w-3xl text-sm text-zinc-500">{{ __('ui.profile_sharing.help') }}</p>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:items-end">
            @unless ($composerOpen)
                <flux:button wire:click="openComposer" size="sm" variant="ghost" class="w-full sm:w-auto">
                    {{ __('ui.profile_sharing.create') }}
                </flux:button>
            @endunless
            <div class="rounded-xl bg-zinc-100 px-4 py-3 text-center dark:bg-zinc-800">
            <div class="text-2xl font-semibold">{{ $completeness['percent'] }}%</div>
                <div class="text-xs text-zinc-500">{{ __('ui.profile_sharing.completeness') }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
        <h3 class="font-medium">{{ __('ui.profile_sharing.requirements') }}</h3>
        <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile_sharing.requirements_help') }}</p>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($completeness['items'] as $item)
                <div class="flex items-center gap-2 text-sm">
                    <span aria-hidden="true" class="{{ $item['satisfied'] ? 'text-emerald-600' : 'text-zinc-400' }}">
                        {{ $item['satisfied'] ? '✓' : '○' }}
                    </span>
                    <span>{{ $item['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if ($composerOpen)
    <form wire:submit="create" class="min-w-0 space-y-5 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
        <div>
            <h3 class="font-medium">{{ __('ui.profile_sharing.share_items') }}</h3>
            <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile_sharing.share_help') }}</p>
        </div>

        <flux:input wire:model="recipientProfileId" :label="__('ui.profile_sharing.recipient')" :description="__('ui.profile_sharing.recipient_help')" maxlength="500" />
        <flux:input wire:model="purpose" :label="__('ui.profile_sharing.purpose')" maxlength="180" />

        <div class="space-y-2">
            <label for="profile-share-expiry" class="text-sm font-medium">{{ __('ui.profile_sharing.expiry') }}</label>
            <select id="profile-share-expiry" wire:model="expiryDays" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:focus:ring-zinc-800">
                <option value="never">{{ __('ui.profile_sharing.never') }}</option>
                @foreach ([7, 30, 90] as $days)
                    <option value="{{ $days }}">{{ __('ui.profile_sharing.days', ['days' => $days]) }}</option>
                @endforeach
            </select>
        </div>

        @if ($availableItems === [])
            <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">{{ __('ui.profile_sharing.empty') }}</p>
        @else
            <fieldset class="space-y-3">
                <legend class="text-sm font-medium">{{ __('ui.profile_sharing.choose') }}</legend>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($availableItems as $item)
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800" wire:key="shareable-{{ md5($item['key']) }}">
                            <input type="checkbox" wire:model="selectedItems" value="{{ $item['key'] }}" class="mt-1 rounded border-zinc-300">
                            <span class="min-w-0">
                                <span class="block font-medium">{{ $item['label'] }}</span>
                                <span class="block truncate text-xs text-zinc-500">{{ $item['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        @error('selectedItems')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button type="button" wire:click="cancelComposer" variant="ghost" class="w-full sm:w-auto">{{ __('ui.common.cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" :disabled="$availableItems === []" wire:loading.attr="disabled" wire:target="create" class="w-full sm:w-auto">
                {{ __('ui.profile_sharing.create') }}
            </flux:button>
        </div>
    </form>
    @endif

    <div class="space-y-3">
        <div>
            <h3 class="font-medium">{{ __('ui.profile_sharing.grants') }}</h3>
            <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile_sharing.link_help') }}</p>
        </div>

        @forelse ($grants as $grant)
            <article class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" wire:key="grant-{{ $grant->uuid }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-medium">{{ __('ui.profile_sharing.recipient_label', ['name' => $grant->grantee->profile?->display_name ?: $grant->grantee->user?->username ?: '#'.$grant->grantee_actor_id]) }}</p>
                        @if ($grant->purpose)
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $grant->purpose }}</p>
                        @endif
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ trans_choice('ui.profile_sharing.item_count', $grant->items->count(), ['count' => $grant->items->count()]) }}
                            ·
                            @if ($grant->revoked_at)
                                {{ __('ui.profile_sharing.revoked') }}
                            @elseif ($grant->expires_at && $grant->expires_at->isPast())
                                {{ __('ui.profile_sharing.expired') }}
                            @else
                                {{ __('ui.profile_sharing.active') }}
                            @endif
                        </p>
                        @if ($grant->isActive())
                            <code class="mt-2 block break-all text-xs text-zinc-500">{{ route('profiles.shares.show', $grant) }}</code>
                        @endif
                    </div>
                    @if ($grant->isActive())
                        <flux:button wire:click="revoke('{{ $grant->uuid }}')" wire:confirm="{{ __('ui.profile_sharing.revoke_confirm') }}" size="sm" variant="danger" class="w-full shrink-0 sm:w-auto">
                            {{ __('ui.profile_sharing.revoke') }}
                        </flux:button>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-sm text-zinc-500">{{ __('ui.profile_sharing.no_grants') }}</p>
        @endforelse
    </div>
</section>
