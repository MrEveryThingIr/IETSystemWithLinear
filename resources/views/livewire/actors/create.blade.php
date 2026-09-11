<section class="space-y-6">
    <x-app.page-header :title="__('ui.actors.create')" :description="__('ui.actors.accountless_help')" />
    <form wire:submit="save" class="max-w-xl space-y-6">
        <flux:callout icon="shield-check" :heading="__('ui.actors.identity_protected')">
            {{ __('ui.actors.identity_protected_help') }}
        </flux:callout>
        <div class="flex flex-wrap gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('ui.actors.create_accountless') }}</flux:button>
            <flux:button :href="route('actors.index')" variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
        </div>
    </form>
</section>
