<section class="mx-auto max-w-3xl space-y-6">
    <x-app.page-header :title="__('planning_baseline.identity.title')" :description="__('planning_baseline.identity.help')" />

    <flux:card class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('planning_baseline.identity.username') }}</div>
                <div class="mt-1 font-medium">{{ '@'.$user->username }}</div>
            </div>
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('planning_baseline.identity.email') }}</div>
                <div class="mt-1 break-all font-medium">{{ $user->email }}</div>
            </div>
        </div>

        <form wire:submit="save" class="space-y-4">
            <flux:input wire:model="displayName" :label="__('planning_baseline.identity.display_name')" maxlength="120" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                    {{ __('ui.common.save') }}
                </flux:button>
            </div>
        </form>
    </flux:card>

    <livewire:profile.temporal-preferences />
</section>
