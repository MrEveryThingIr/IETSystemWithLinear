<section class="rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold">{{ __('ui.profile.account_email.title') }}</h2>
            <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('ui.profile.account_email.help') }}</p>
        </div>

        @unless ($editorOpen)
            <flux:button wire:click="openEditor" size="sm" variant="ghost" class="w-full shrink-0 sm:w-auto">
                {{ __('ui.profile.account_email.change') }}
            </flux:button>
        @endunless
    </div>

    <div class="mt-5 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">
            {{ __('ui.profile.account_email.current') }}
        </div>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <span class="break-all font-semibold">{{ $user->email }}</span>
            @if($user->hasVerifiedEmail())
                <flux:badge color="green">{{ __('ui.profile.account_email.verified') }}</flux:badge>
            @endif
        </div>
    </div>

    @if($editorOpen)
        <form wire:submit="save" class="mt-5 space-y-4">
            <flux:input wire:model="email" type="email" :label="__('ui.profile.account_email.new_email')" autocomplete="email" />
            <flux:input wire:model="currentPassword" type="password" :label="__('ui.profile.account_email.current_password')" autocomplete="current-password" />
            <p class="text-xs leading-5 text-zinc-500">{{ __('ui.profile.account_email.security_help') }}</p>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button type="button" wire:click="cancelEditor" variant="ghost" class="w-full sm:w-auto">
                    {{ __('ui.common.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save" class="w-full sm:w-auto">
                    {{ __('ui.profile.account_email.save') }}
                </flux:button>
            </div>
        </form>
    @endif
</section>
