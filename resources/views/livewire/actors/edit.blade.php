<section class="space-y-6">
    <x-app.page-header :title="__('ui.actors.edit')" :description="__('ui.actors.accountless_help')" />
    <form wire:submit="save" class="max-w-xl space-y-6">
        <flux:select wire:model="userId" :label="__('ui.actors.association')" :description="__('ui.actors.association_help')">
            <flux:select.option value="">{{ __('ui.actors.no_account') }}</flux:select.option>
            @foreach ($users as $user)
                <flux:select.option :value="$user->id">{{ $user->username }} ({{ $user->email }})</flux:select.option>
            @endforeach
        </flux:select>
        <div class="flex flex-wrap gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('ui.actors.save') }}</flux:button>
            <flux:button :href="route('actors.index')" variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
        </div>
    </form>
</section>
