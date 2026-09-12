<section class="space-y-6">
    <x-app.page-header :title="__('ui.groups.create')" :description="__('ui.groups.create_intro')" />

    <form wire:submit="save" class="max-w-xl space-y-6">
        <flux:input wire:model="name" :label="__('ui.groups.name')" :placeholder="__('ui.groups.name_placeholder')" autofocus />
        <flux:textarea wire:model="description" :label="__('ui.groups.description')" :description="__('ui.groups.description_help')" rows="5" />
        <flux:select wire:model="timezone" :label="__('ui.groups.timezone')" :description="__('ui.groups.timezone_help')" searchable>
            @foreach ($timezones as $timezoneOption)
                <option value="{{ $timezoneOption }}">{{ $timezoneOption }}</option>
            @endforeach
        </flux:select>
        <div class="flex flex-wrap gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('ui.groups.create') }}</flux:button>
            <flux:button :href="route('groups.index')" variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
        </div>
    </form>
</section>
