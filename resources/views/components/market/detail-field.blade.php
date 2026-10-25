@props(['index', 'detail', 'need' => false, 'topic' => null])
@php
    $key = $detail['canonical_key'];
    $spec = \App\Support\Market\IntentDetailCatalog::all()[$key] ?? ['type' => $detail['type']];
    $type = (string) $spec['type'];
    $label = \App\Support\Market\IntentDetailCatalog::label($key, null, $topic ?: null);
    $unit = \App\Support\Market\IntentDetailCatalog::unitLabel($detail['unit'] ?? null);
    $labelWithUnit = $unit && $type !== 'amount' ? $label.' ('.$unit.')' : $label;
    $hint = \App\Support\Market\IntentDetailCatalog::hint($key, $topic ?: null);
    $ops = \App\Support\Market\DetailValue::operators($type, (bool) $need);
    $op = $detail['op'] ?? 'exact';
    $model = 'details.'.$index;
    $example = \App\Support\Market\IntentDetailCatalog::example($key, null, $topic ?: null);
    $box = 'block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900';
@endphp
<fieldset class="min-w-0 space-y-1.5" data-composer-field="{{ $key }}">
    @if($type === 'boolean')
        <flux:checkbox wire:model.live="{{ $model }}.value" :label="$label" />
    @else
        <legend class="text-sm font-medium">{{ $labelWithUnit }}</legend>
        <div class="flex flex-wrap items-center gap-2">
            @if(count($ops) > 1)
                <select wire:model.live="{{ $model }}.op" aria-label="{{ $label }}: {{ __('composer.ops.'.$op) }}" class="{{ $box }} !w-auto" data-op>
                    @foreach($ops as $candidate)<option value="{{ $candidate }}">{{ __('composer.ops.'.$candidate) }}</option>@endforeach
                </select>
            @endif
            @if($op === 'one_of')
                <div class="flex flex-wrap gap-x-4 gap-y-2">@foreach($detail['options'] ?? [] as $option)<flux:checkbox wire:model.live="{{ $model }}.values" :value="$option" :label="\App\Support\Market\IntentDetailCatalog::optionLabel($option)" />@endforeach</div>
            @elseif($type === 'choice')
                <select wire:model.live="{{ $model }}.value" aria-label="{{ $labelWithUnit }}" class="{{ $box }} !w-auto min-w-40">
                    <option value="">{{ __('composer.select') }}</option>
                    @foreach($detail['options'] ?? [] as $option)<option value="{{ $option }}">{{ \App\Support\Market\IntentDetailCatalog::optionLabel($option) }}</option>@endforeach
                </select>
            @elseif($type === 'date')
                @if($op === 'between')
                    <x-app.calendar-date-input :model="$model.'.min'" :label="__('composer.between_from')" /><x-app.calendar-date-input :model="$model.'.max'" :label="__('composer.between_to')" />
                @else
                    <x-app.calendar-date-input :model="$model.($op === 'max' ? '.max' : ($op === 'min' ? '.min' : '.value'))" :label="$label" />
                @endif
            @elseif(in_array($type, ['number', 'amount', 'year'], true))
                @if($op === 'between')
                    <input type="text" inputmode="decimal" wire:key="{{ $model }}-{{ $op }}-min" wire:model.live.blur="{{ $model }}.min" aria-label="{{ $label }}: {{ __('composer.between_from') }}" placeholder="{{ __('composer.between_from') }}" class="{{ $box }} !w-32">
                    <input type="text" inputmode="decimal" wire:key="{{ $model }}-{{ $op }}-max" wire:model.live.blur="{{ $model }}.max" aria-label="{{ $label }}: {{ __('composer.between_to') }}" placeholder="{{ __('composer.between_to') }}" class="{{ $box }} !w-32">
                @else
                    <input type="text" inputmode="decimal" wire:key="{{ $model }}-{{ $op }}-single" wire:model.live.blur="{{ $model }}.{{ $op === 'exact' ? 'value' : $op }}" aria-label="{{ $labelWithUnit }}" @if($example) placeholder="{{ $example }}" @endif class="{{ $box }} !w-44" @if(! empty($detail['error'])) aria-invalid="true" @endif>
                @endif
            @elseif($type === 'long_text')
                <textarea wire:model="{{ $model }}.value" aria-label="{{ $label }}" rows="2" dir="auto" class="{{ $box }}"></textarea>
            @else
                <input type="text" wire:model="{{ $model }}.value" aria-label="{{ $labelWithUnit }}" @if($example) placeholder="{{ $example }}" @endif dir="auto" class="{{ $box }} flex-1">
            @endif
        </div>
    @endif
    @if($hint['help'] || $hint['format'])
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ trim(($hint['help'] ?? '').' '.$hint['format']) }}@if($type === 'amount') · {{ __('composer.information_money') }}@endif</p>
    @endif
    @if(! empty($detail['error']))<p role="alert" class="text-xs text-red-600 dark:text-red-400">{{ $detail['error'] }}</p>@endif
    @if(! empty($detail['suggestion']))
        <button type="button" wire:click="applySuggestion({{ $index }})" class="inline-flex min-h-9 items-center rounded-full border border-dashed border-indigo-500 px-3 text-xs text-indigo-700 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:text-indigo-300 dark:hover:bg-indigo-950" data-interpret>{{ __('composer.interpret', ['meaning' => $detail['suggestion']['meaning']]) }}</button>
    @endif
    @if(! empty($detail['showNote']))
        <div class="space-y-1">
            <textarea wire:model.live.blur="{{ $model }}.note" rows="2" maxlength="280" aria-label="{{ __('composer.note_label') }}" placeholder="{{ __('composer.note_label') }}" dir="auto" class="{{ $box }}" data-field-note></textarea>
            <p class="text-xs text-zinc-500">{{ __('composer.note_hint') }}</p>
        </div>
    @else
        <button type="button" wire:click="toggleNote({{ $index }})" class="text-xs text-zinc-500 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-indigo-500" data-add-note>{{ __('composer.add_note') }}</button>
    @endif
</fieldset>
