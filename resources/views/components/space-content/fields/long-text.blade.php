@props(['field', 'model'])

<div class="space-y-1">
    <flux:textarea wire:model="{{ $model }}" :label="$field['label']" rows="5" maxlength="20000" />
    @if ($field['help'] ?? null)
        <flux:text class="text-xs text-zinc-500">{{ $field['help'] }}</flux:text>
    @endif
</div>
