@props(['field', 'model'])

<div class="space-y-1">
    <flux:select wire:model="{{ $model }}" :label="$field['label']">
        <option value="">{{ __('ui.content.choose_option') }}</option>
        @foreach ($field['options'] as $option)
            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
        @endforeach
    </flux:select>
    @if ($field['help'] ?? null)
        <flux:text class="text-xs text-zinc-500">{{ $field['help'] }}</flux:text>
    @endif
</div>
