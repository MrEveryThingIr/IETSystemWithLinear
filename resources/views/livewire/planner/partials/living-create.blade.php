<details class="rounded-xl border p-4">
    <summary class="cursor-pointer font-semibold">{{ __('planner.new') }}</summary>
    <form wire:submit="createActivity" class="mt-4 grid gap-3 sm:grid-cols-2" data-create-activity>
        <flux:input wire:model="newTitle" :label="__('living_planner.name')" required />
        <flux:select wire:model="newFrequency" :label="__('planner.create.frequency')">@foreach(['once','daily','weekly','monthly'] as $frequency)<option value="{{ $frequency }}">{{ __('planner.frequency.'.$frequency) }}</option>@endforeach</flux:select>
        <x-app.calendar-date-input model="revisionDate" :label="__('living_planner.starts_on')" />
        <flux:input type="time" wire:model="revisionTime" :label="__('living_planner.start_time')" />
        <flux:input type="number" min="1" max="10080" wire:model="revisionMinutes" :label="__('living_planner.duration')" />
        <flux:select wire:model="policyStyle" :label="__('living_planner.policy')">@foreach(['hard','flexible','deadline'] as $style)<option value="{{ $style }}">{{ __('living_planner.'.$style) }}</option>@endforeach</flux:select>
        <flux:button type="submit" variant="primary">{{ __('living_planner.save') }}</flux:button>
    </form>
</details>
