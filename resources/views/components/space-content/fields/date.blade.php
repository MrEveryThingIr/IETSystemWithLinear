@props(['field', 'model'])

<div class="space-y-1">
    <flux:input wire:model="{{ $model }}" type="date" :label="$field['label']" />
    @if ($field['help'] ?? null)
        <flux:text class="text-xs text-zinc-500">{{ $field['help'] }}</flux:text>
    @endif
</div>
