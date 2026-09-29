@props(['field', 'model'])

<div class="space-y-1">
    <x-app.calendar-date-input :model="$model" :label="$field['label']" />
    @if ($field['help'] ?? null)
        <flux:text class="text-xs text-zinc-500">{{ $field['help'] }}</flux:text>
    @endif
</div>
