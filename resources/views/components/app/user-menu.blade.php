@php
    $accountUser = auth()->user();
    $accountActor = $accountUser?->actor;
    $displayName = $accountActor?->profile?->display_name ?: $accountUser?->username;
    $showUsername = filled($accountUser?->username) && $displayName !== $accountUser->username;
@endphp

<div x-data>
    <flux:dropdown position="bottom" align="end">
        <flux:button
            variant="ghost"
            icon:trailing="chevron-down"
            :aria-label="__('ui.navigation.account_menu')"
            class="max-w-56 gap-2"
        >
            @if ($accountActor)
                <x-app.actor-avatar :actor="$accountActor" size="xs" :alt="$displayName" />
            @endif
            <span class="hidden min-w-0 truncate sm:block">{{ $displayName }}</span>
        </flux:button>

        <flux:menu class="w-72 max-w-[calc(100vw-2rem)] sm:w-80">
            <div class="flex items-center gap-3 px-2 py-2.5">
                @if ($accountActor)
                    <x-app.actor-avatar :actor="$accountActor" size="sm" :alt="$displayName" />
                @endif

                <div class="min-w-0 flex-1">
                    <flux:text class="truncate font-medium text-zinc-900 dark:text-white" :title="$displayName">
                        {{ $displayName }}
                    </flux:text>

                    @if ($showUsername)
                        <flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400" :title="$accountUser->username">
                            @@{{ $accountUser->username }}
                        </flux:text>
                    @endif

                    <flux:text size="sm" class="truncate text-zinc-500 dark:text-zinc-400" :title="$accountUser->email">
                        {{ $accountUser->email }}
                    </flux:text>
                </div>
            </div>

            <flux:menu.separator />

            <flux:menu.item :href="route('profile.edit')" icon="user-circle">
                {{ __('ui.navigation.profile') }}
            </flux:menu.item>

            <flux:menu.separator />

            <div class="px-2 pb-1 pt-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                {{ __('ui.appearance.label') }}
            </div>
            <flux:menu.radio.group x-model="$flux.appearance" :aria-label="__('ui.appearance.label')">
                <flux:menu.radio value="system" icon="computer-desktop">{{ __('ui.appearance.system') }}</flux:menu.radio>
                <flux:menu.radio value="light" icon="sun">{{ __('ui.appearance.light') }}</flux:menu.radio>
                <flux:menu.radio value="dark" icon="moon">{{ __('ui.appearance.dark') }}</flux:menu.radio>
            </flux:menu.radio.group>

            <flux:menu.separator />

            <flux:menu.item type="submit" form="app-logout" icon="arrow-right-start-on-rectangle">
                {{ __('ui.auth.logout') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>

    <form id="app-logout" method="POST" action="{{ route('logout') }}">
        @csrf
    </form>
</div>
