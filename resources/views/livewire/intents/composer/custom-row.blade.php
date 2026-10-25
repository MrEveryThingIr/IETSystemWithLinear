{{-- A person's own field: they name it, choose its kind, and may reuse or suggest it. --}}
<div class="space-y-3">
    <flux:input wire:model="details.{{ $index }}.label" :label="__('composer.detail_name')" dir="auto" />
    @if(in_array($detail['type'], ['long_text', 'place'], true))
        <flux:textarea wire:model="details.{{ $index }}.value" :label="__('composer.value')" dir="auto" />
    @elseif($detail['type'] === 'boolean')
        <flux:select wire:model="details.{{ $index }}.value" :label="__('composer.value')"><option value="">{{ __('composer.select') }}</option><option value="1">{{ __('composer.yes') }}</option><option value="0">{{ __('composer.no') }}</option></flux:select>
    @elseif($detail['type'] === 'date')
        <x-app.calendar-date-input :model="'details.'.$index.'.value'" :label="__('composer.value')" />
    @elseif($detail['type'] === 'date_range')
        <div class="grid gap-3 sm:grid-cols-2"><x-app.calendar-date-input :model="'details.'.$index.'.value.start'" :label="__('composer.starts_on')" /><x-app.calendar-date-input :model="'details.'.$index.'.value.end'" :label="__('composer.ends_on')" /></div>
    @elseif($detail['type'] === 'multiple_choice')
        @foreach($detail['options'] ?? [] as $option)<flux:checkbox wire:model="details.{{ $index }}.value" :value="$option" :label="\App\Support\Market\IntentDetailCatalog::optionLabel($option)" />@endforeach
    @elseif($detail['type'] === 'choice' && ! empty($detail['options']))
        <flux:select wire:model="details.{{ $index }}.value" :label="__('composer.value')"><option value="">{{ __('composer.select') }}</option>@foreach($detail['options'] as $option)<option value="{{ $option }}">{{ \App\Support\Market\IntentDetailCatalog::optionLabel($option) }}</option>@endforeach</flux:select>
    @elseif($detail['type'] === 'reference')
        <p class="text-sm">{{ __('composer.reference_help') }}</p>
        <flux:select wire:model.live="details.{{ $index }}.value.type" :label="__('composer.reference_type')"><option value="">{{ __('composer.select') }}</option><option value="content">{{ __('ui.content.tab') }}</option><option value="group">{{ __('ui.groups.title') }}</option><option value="catalog">{{ __('business.catalog.title') }}</option></flux:select>
        <flux:input wire:model.live.debounce.400ms="referenceSearch" :label="__('business.clients.search')" />
        <flux:select wire:model="details.{{ $index }}.value.id" :label="__('composer.reference_item')"><option value="">{{ __('composer.select') }}</option>@foreach($referenceChoices[$detail['value']['type'] ?? 'content'] ?? [] as $reference)<option value="{{ $reference['id'] }}">{{ $reference['label'] }}</option>@endforeach</flux:select>
    @else
        <flux:input wire:model="details.{{ $index }}.value" :label="__('composer.value')" :type="$detail['type'] === 'time' ? 'time' : 'text'" dir="auto" />
    @endif
    @if(in_array($detail['type'], ['choice', 'multiple_choice'], true))<flux:input wire:model.live.debounce.300ms="details.{{ $index }}.optionsText" :label="__('composer.choices_help')" />@endif
    @if($detail['type'] === 'quantity')<flux:input wire:model="details.{{ $index }}.unit" :label="__('composer.unit')" />@endif
    @if($detail['type'] === 'amount')<p class="text-sm text-zinc-500">{{ __('composer.information_money') }}</p>@endif
</div>
