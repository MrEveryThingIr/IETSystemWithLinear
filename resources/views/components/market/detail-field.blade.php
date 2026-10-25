@props(['index', 'detail'])
@php
    $key = $detail['canonical_key'];
    $label = __('composer.details.'.$key);
    $unit = \App\Support\Market\IntentDetailCatalog::unitLabel($detail['unit'] ?? null);
    $labelWithUnit = $unit && $detail['type'] !== 'amount' ? $label.' ('.$unit.')' : $label;
    $model = 'details.'.$index.'.value';
@endphp
<div class="min-w-0" data-composer-field="{{ $key }}">
    @if($detail['type'] === 'choice')
        <flux:select wire:model.live="{{ $model }}" :label="$labelWithUnit">
            <option value="">{{ __('composer.select') }}</option>
            @foreach($detail['options'] ?? [] as $option)<option value="{{ $option }}">{{ \App\Support\Market\IntentDetailCatalog::optionLabel($option) }}</option>@endforeach
        </flux:select>
    @elseif($detail['type'] === 'boolean')
        <flux:checkbox wire:model.live="{{ $model }}" :label="$label" />
    @elseif($detail['type'] === 'number' || $detail['type'] === 'amount')
        <flux:input wire:model="{{ $model }}" :label="$labelWithUnit" inputmode="decimal" :description="$detail['type'] === 'amount' ? __('composer.information_money') : null" />
    @else
        <flux:input wire:model="{{ $model }}" :label="$labelWithUnit" dir="auto" />
    @endif
</div>
