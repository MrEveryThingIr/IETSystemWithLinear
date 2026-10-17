{{-- One schema field edited inline in the Studio canvas. --}}
@php
    $model = 'payload.'.$field['key'];
    $label = $field['label'].(($field['required'] ?? false) ? '' : ' · '.__('ui.common.optional'));
@endphp

<div class="space-y-1" data-studio-field="{{ $field['key'] }}">
    @switch($field['type'])
        @case('short_text')
            <flux:input wire:model.live.debounce.500ms="{{ $model }}" :label="$label" maxlength="500" dir="auto" />
            @break
        @case('long_text')
            <flux:textarea wire:model.live.debounce.500ms="{{ $model }}" :label="$label" rows="8" maxlength="20000" resize="vertical" dir="auto" />
            @break
        @case('number')
            <flux:input type="number" step="any" wire:model.live.debounce.500ms="{{ $model }}" :label="$label" />
            @break
        @case('date')
            <x-app.calendar-date-input :model="$model" :label="$label" />
            @break
        @case('boolean')
            <flux:checkbox wire:model.live="{{ $model }}" :label="$field['label']" />
            @break
        @case('select')
            <flux:select wire:model.live="{{ $model }}" :label="$label">
                <option value="">—</option>
                @foreach ($field['options'] ?? [] as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </flux:select>
            @break
    @endswitch
    @if ($field['help'] ?? null)
        <p class="text-xs text-zinc-500" dir="auto">{{ $field['help'] }}</p>
    @endif
</div>
