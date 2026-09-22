@php
    $accountUser = auth()->user();
    $accountActor = $accountUser?->actor;
    $displayName = $accountActor?->profile?->display_name ?: $accountUser?->username;
@endphp

<div x-data>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" icon:trailing="chevron-down" :aria-label="__('ui.navigation.account_menu')" class="gap-2">
            @if ($accountActor)
                <x-app.actor-avatar :actor="$accountActor" size="xs" :alt="$accountUser->username" />
            @endif
            <span class="hidden max-w-40 truncate sm:block">{{ $accountUser->username }}</span>
        </flux:button>
        <flux:menu class="w-64 max-w-[calc(100vw-2rem)]">
            <div class="flex items-center gap-3 px-2 py-2">
                @if ($accountActor)
                    <x-app.actor-avatar :actor="$accountActor" size="sm" :alt="$accountUser->username" />
                @endif
                <div class="min-w-0">
                    <flux:text class="truncate font-medium text-zinc-900 dark:text-white">{{ $displayName }}</flux:text>
                    <flux:text size="sm" class="truncate">@{{ $accountUser->username }}</flux:text>
                    <flux:text size="sm" class="break-all">{{ $accountUser->email }}</flux:text>
                </div>
            </div>
            <flux:menu.separator />
            <flux:menu.item :href="route('profile.edit')" icon="user-circle">{{ __('ui.navigation.profile') }}</flux:menu.item>
            <flux:menu.separator />
            <flux:menu.radio.group x-model="$flux.appearance" :aria-label="__('ui.appearance.label')">
                <flux:menu.radio value="system" icon="computer-desktop">{{ __('ui.appearance.system') }}</flux:menu.radio>
                <flux:menu.radio value="light" icon="sun">{{ __('ui.appearance.light') }}</flux:menu.radio>
                <flux:menu.radio value="dark" icon="moon">{{ __('ui.appearance.dark') }}</flux:menu.radio>
            </flux:menu.radio.group>
            <flux:menu.separator />
            <flux:menu.item type="submit" form="app-logout" icon="arrow-right-start-on-rectangle">{{ __('ui.auth.logout') }}</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
    <form id="app-logout" method="POST" action="{{ route('logout') }}">
        @csrf
    </form>
</div>
