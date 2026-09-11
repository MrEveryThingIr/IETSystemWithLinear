<div x-data>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" icon="user-circle" icon:trailing="chevron-down" :aria-label="__('ui.navigation.account_menu')">
            <span class="hidden max-w-40 truncate sm:block">{{ auth()->user()->username }}</span>
        </flux:button>
        <flux:menu class="w-64 max-w-[calc(100vw-2rem)]">
            <div class="px-2 py-2">
                <flux:text class="truncate font-medium text-zinc-900 dark:text-white">{{ auth()->user()->username }}</flux:text>
                <flux:text size="sm" class="break-all">{{ auth()->user()->email }}</flux:text>
            </div>
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
