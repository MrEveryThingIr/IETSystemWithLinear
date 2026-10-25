<div>
    @if($open)
        @php
            $locale = app()->getLocale();
            $hasField = $key !== null;
            $simple = ['words', 'kind', 'choices', 'place', 'fit', 'duplicate', 'collection'];
            $box = 'block w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-base sm:text-sm dark:border-zinc-700 dark:bg-zinc-900';
            $topicLabel = ($context['topic'] ?? '') !== '' ? __('composer.topics.'.$context['topic']) : null;
        @endphp
        <div class="fixed inset-0 z-[60] flex items-end justify-center bg-black/40 sm:items-start sm:p-6" x-on:keydown.escape.window="$wire.close()" x-on:click.self="$wire.close()" data-suggestion-modal>
            <div role="dialog" aria-modal="true" aria-label="{{ __('composer.suggest.title') }}" class="flex max-h-[90dvh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:mt-12 sm:max-w-lg sm:rounded-2xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3 border-b p-4">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold">{{ __('composer.suggest.title') }}</h2>
                        @if($fieldLabel)<p class="text-sm text-zinc-600 dark:text-zinc-400" dir="auto">{{ __('composer.suggest.about', ['field' => $fieldLabel]) }}</p>@endif
                    </div>
                    <button type="button" wire:click="close" class="grid size-10 shrink-0 place-items-center rounded-full hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-800" aria-label="{{ __('composer.picker.close') }}"><flux:icon.x-mark class="size-5" /></button>
                </div>
                <div class="space-y-4 overflow-y-auto p-4">
                    @if($step === 'done')
                        <p role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100" data-suggestion-done>{{ __('composer.suggest.thanks') }}</p>
                        <div class="flex flex-wrap justify-end gap-2"><flux:button size="sm" :href="route('intents.field-suggestions')" target="_blank">{{ __('composer.suggest.my') }}</flux:button><flux:button size="sm" variant="primary" wire:click="close">{{ __('composer.picker.close') }}</flux:button></div>
                    @elseif($step === 'choose')
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('composer.suggest.intro') }}</p>
                        <div class="grid gap-2" role="group" aria-label="{{ __('composer.suggest.choose') }}">
                            @foreach($simple as $name)
                                @continue(! $hasField && ! in_array($name, \App\Support\Market\FieldSuggestionTypes::WITHOUT_FIELD, true))
                                @continue($name === 'choices' && ($options ?? []) === [])
                                <button type="button" wire:click="pick('{{ $name }}')" class="flex min-h-11 items-center justify-between rounded-xl border px-4 py-2 text-start text-sm hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-800" data-suggest-group-choice="{{ $name }}">{{ __('composer.suggest.groups.'.$name) }}<flux:icon.chevron-right class="size-4 rtl:rotate-180" /></button>
                            @endforeach
                            @if($hasField)
                                <details class="rounded-xl border px-4 py-2 text-sm"><summary class="min-h-9 cursor-pointer py-1.5">{{ __('composer.suggest.more') }}</summary>
                                    <button type="button" wire:click="pick('display')" class="mt-1 flex min-h-10 w-full items-center rounded-lg px-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800">{{ __('composer.suggest.groups.display') }}</button>
                                </details>
                            @endif
                        </div>
                    @else
                        <button type="button" wire:click="back" class="text-sm text-zinc-500 underline-offset-2 hover:underline">&larr; {{ __('composer.suggest.groups.'.$group) }}</button>
                        @php($types = \App\Support\Market\FieldSuggestionTypes::GROUPS[$group])
                        @if(count($types) > 1)
                            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('composer.suggest.choose') }}">
                                @foreach($types as $candidate)<x-market.chip :active="$type === $candidate" wire:click="setType('{{ $candidate }}')" wire:key="type-{{ $candidate }}">{{ __('composer.suggest.types.'.$candidate) }}</x-market.chip>@endforeach
                            </div>
                        @endif
                        @if($key && in_array($type, ['label', 'description', 'aliases', 'unit', 'data_type', 'options', 'example'], true))
                            <p class="rounded-lg bg-zinc-50 p-3 text-xs text-zinc-600 dark:bg-zinc-800/50 dark:text-zinc-300" dir="auto">{{ __('composer.suggest.current', ['value' => trim(\App\Support\Market\IntentDetailCatalog::label($key, null, $context['topic'] ?? null).' · '.(\App\Support\Market\IntentDetailCatalog::all()[$key]['type'] ?? '').(\App\Support\Market\IntentDetailCatalog::unitLabel(\App\Support\Market\IntentDetailCatalog::all()[$key]['unit'] ?? null) ? ' · '.\App\Support\Market\IntentDetailCatalog::unitLabel(\App\Support\Market\IntentDetailCatalog::all()[$key]['unit'] ?? null) : ''))]) }}</p>
                        @endif
                        @if(in_array($type, ['label', 'description', 'aliases', 'example'], true))
                            <flux:input wire:model="data.text" :label="__('composer.suggest.forms.'.$type)" dir="auto" />
                        @elseif($type === 'unit')
                            <flux:input wire:model="data.unit" :label="__('composer.suggest.forms.unit')" dir="auto" />
                        @elseif($type === 'data_type')
                            <flux:select wire:model="data.type" :label="__('composer.suggest.forms.data_type')"><option value="">{{ __('composer.select') }}</option>@foreach(['text', 'number', 'choice', 'boolean', 'date', 'year', 'long_text', 'link'] as $kind)<option value="{{ $kind }}">{{ __('composer.hints.kind.'.$kind) }}</option>@endforeach</flux:select>
                            <flux:input wire:model="data.unit" :label="__('composer.suggest.forms.unit')" dir="auto" />
                        @elseif($type === 'options')
                            <fieldset class="space-y-2"><legend class="text-sm font-medium">{{ __('composer.suggest.forms.options_remove') }}</legend>
                                @foreach($options as $option)<flux:checkbox wire:model="data.remove" :value="$option" :label="\App\Support\Market\IntentDetailCatalog::optionLabel($option)" />@endforeach</fieldset>
                            <flux:input wire:model="data.add" :label="__('composer.suggest.forms.options_add')" dir="auto" />
                        @elseif($type === 'group')
                            <flux:select wire:model.live="data.action" :label="__('composer.suggest.forms.group_action')">@foreach(['add', 'remove', 'move', 'new'] as $action)<option value="{{ $action }}">{{ __('composer.suggest.group_actions.'.$action) }}</option>@endforeach</flux:select>
                            @if(($data['action'] ?? 'add') === 'new')
                                <flux:input wire:model="data.group_label" :label="__('composer.suggest.forms.group_new_label')" dir="auto" />
                                <flux:select wire:model="data.parent_key" :label="__('composer.suggest.forms.group_parent')"><option value="">{{ __('composer.select') }}</option>@foreach($taxonomy as $domain => $branchGroups)<option value="{{ $domain }}">{{ __('composer.domains.'.$domain) }}</option>@endforeach</flux:select>
                            @else
                                <flux:select wire:model="data.group_key" :label="__('composer.suggest.forms.group_pick')"><option value="">{{ __('composer.select') }}</option>@foreach($taxonomy as $domain => $branchGroups)<optgroup label="{{ __('composer.domains.'.$domain) }}">@foreach($branchGroups as $branchGroup)<option value="{{ $branchGroup['key'] }}">{{ $branchGroup['label'] }}</option>@endforeach</optgroup>@endforeach</flux:select>
                                @if(($data['action'] ?? '') === 'move')<flux:select wire:model="data.to_group_key" :label="__('composer.suggest.forms.group_to')"><option value="">{{ __('composer.select') }}</option>@foreach($taxonomy as $domain => $branchGroups)<optgroup label="{{ __('composer.domains.'.$domain) }}">@foreach($branchGroups as $branchGroup)<option value="{{ $branchGroup['key'] }}">{{ $branchGroup['label'] }}</option>@endforeach</optgroup>@endforeach</flux:select>@endif
                            @endif
                        @elseif($type === 'applicability')
                            <fieldset class="space-y-2"><legend class="text-sm font-medium">{{ __('composer.suggest.forms.effect') }}</legend>
                                @foreach(['hide', 'only_need', 'only_offer'] as $effect)<label class="flex min-h-10 items-center gap-2 text-sm"><input type="radio" wire:model.live="data.effect" value="{{ $effect }}"> {{ __('composer.suggest.effects.'.$effect) }}</label>@endforeach
                                <details><summary class="min-h-9 cursor-pointer py-1.5 text-sm text-zinc-600 dark:text-zinc-400">{{ __('composer.suggest.more') }}</summary>
                                    <label class="flex min-h-10 items-center gap-2 text-sm"><input type="radio" wire:model.live="data.effect" value="show_when"> {{ __('composer.suggest.effects.show_when') }}</label>
                                    @if(($data['effect'] ?? '') === 'show_when')
                                        <flux:select wire:model="data.when_key" :label="__('composer.suggest.forms.when_field')"><option value="">{{ __('composer.select') }}</option>@foreach($fields as $candidate)<option value="{{ $candidate }}">{{ \App\Support\Market\IntentDetailCatalog::label($candidate) }}</option>@endforeach</flux:select>
                                        <flux:input wire:model="data.when_values" :label="__('composer.suggest.forms.when_values')" dir="auto" />
                                    @endif
                                </details></fieldset>
                            @if($topicLabel)<flux:checkbox wire:model="data.scoped" :label="__('composer.suggest.forms.scope_topic', ['topic' => $topicLabel])" />@endif
                            @if(($context['intention'] ?? '') !== '')<flux:checkbox wire:model="data.this_side" :label="__('composer.suggest.forms.scope_side', ['side' => __('intents.kinds.'.$context['intention'])])" />@endif
                        @elseif($type === 'duplicate')
                            <flux:select wire:model="data.other_key" :label="__('composer.suggest.forms.duplicate_of')"><option value="">{{ __('composer.select') }}</option>@foreach($fields as $candidate)@continue($candidate === $key)<option value="{{ $candidate }}">{{ \App\Support\Market\IntentDetailCatalog::label($candidate) }}</option>@endforeach</flux:select>
                        @elseif(in_array($type, ['validation', 'input_control'], true))
                            <flux:select wire:model="data.where" :label="__('composer.suggest.forms.where')">@foreach(['validation', 'presentation', 'input'] as $where)<option value="{{ $where }}">{{ __('composer.suggest.where.'.$where) }}</option>@endforeach</flux:select>
                            <flux:textarea wire:model="data.message" :label="__('composer.suggest.forms.message')" rows="3" dir="auto" />
                        @elseif($type === 'template')
                            <flux:input wire:model="data.name" :label="__('composer.suggest.forms.template_name')" dir="auto" />
                            <fieldset class="space-y-2"><legend class="text-sm font-medium">{{ __('composer.suggest.forms.template_fields') }}</legend>
                                @forelse($context['selected'] ?? [] as $selectedKey)<flux:checkbox wire:model="data.fields" :value="$selectedKey" :label="\App\Support\Market\IntentDetailCatalog::label($selectedKey)" wire:key="tf-{{ $selectedKey }}" />@empty<p class="text-sm text-zinc-500">{{ __('composer.suggest.forms.template_empty') }}</p>@endforelse</fieldset>
                            <flux:select wire:model="data.parent_key" :label="__('composer.suggest.forms.group_parent')"><option value="">{{ __('composer.select') }}</option>@foreach($taxonomy as $domain => $branchGroups)<option value="{{ $domain }}">{{ __('composer.domains.'.$domain) }}</option>@endforeach</flux:select>
                        @endif
                        <flux:textarea wire:model="explanation" :label="__('composer.suggest.forms.explanation')" rows="2" maxlength="1000" dir="auto" />
                        @error('field')<p role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        <div class="flex justify-end gap-2"><flux:button size="sm" variant="ghost" wire:click="close">{{ __('field_review.cancel') }}</flux:button><flux:button size="sm" variant="primary" wire:click="submit" data-suggestion-submit>{{ __('composer.suggest.submit') }}</flux:button></div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
