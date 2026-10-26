<div>
    @if ($open)
        <flux:card class="space-y-5" data-terms-composer>
            <div>
                <flux:heading>{{ __('deals.composer.title') }}</flux:heading>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ $fromOrigin ? __('deals.composer.from_origin') : __('deals.composer.from_version', ['version' => $baseVersion]) }}
                </p>
            </div>

            @error('draft') <flux:callout variant="danger">{{ $message }}</flux:callout> @enderror

            {{-- Core terms you switch on, like the Intent Composer --}}
            @foreach ($shown as $block)
                <div wire:key="block-{{ $block }}" class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">{{ __('deals.composer.blocks.'.$block) }}</span>
                        <button type="button" wire:click="hideBlock('{{ $block }}')" class="text-xs text-zinc-500 underline" aria-label="{{ __('deals.composer.remove') }}">{{ __('deals.composer.remove') }}</button>
                    </div>
                    @switch($block)
                        @case('price')
                            <div class="grid gap-2 sm:grid-cols-4">
                                <flux:input inputmode="decimal" wire:model="draft.core.price_min" :label="__('deals.change.price_min')" />
                                <flux:input inputmode="decimal" wire:model="draft.core.price_max" :label="__('deals.change.price_max')" />
                                <flux:input wire:model="draft.core.currency" :label="__('deals.change.price_currency')" maxlength="5" />
                                <flux:input wire:model="draft.core.basis" :label="__('deals.change.price_basis')" />
                            </div>
                            @break
                        @case('place')
                            <flux:input wire:model="draft.core.place" :label="__('deals.change.place')" />
                            @break
                        @case('time')
                            <div class="grid gap-2 sm:grid-cols-2">
                                <x-app.calendar-date-input model="draft.core.starts_on" :label="__('deals.change.time_starts_on')" />
                                <x-app.calendar-date-input model="draft.core.ends_on" :label="__('deals.change.time_ends_on')" />
                            </div>
                            @break
                        @case('quantity')
                            <div class="grid gap-2 sm:grid-cols-2">
                                <flux:input inputmode="decimal" wire:model="draft.core.quantity" :label="__('deals.change.quantity_value')" />
                                <flux:input wire:model="draft.core.unit" :label="__('deals.change.quantity_unit')" />
                            </div>
                            @break
                        @case('route')
                            <div class="grid gap-2 sm:grid-cols-2">
                                <flux:input wire:model="draft.core.from" :label="__('deals.change.route_from')" />
                                <flux:input wire:model="draft.core.to" :label="__('deals.change.route_to')" />
                            </div>
                            @break
                        @case('recurrence')
                            <div class="grid gap-2 sm:grid-cols-3">
                                <flux:input inputmode="numeric" wire:model="draft.recurrence.every" :label="__('deals.composer.every')" />
                                <flux:select wire:model="draft.recurrence.unit" :label="__('deals.composer.unit')">
                                    @foreach (\App\Support\Deals\DealTerms::RECURRENCE_UNITS as $unit)
                                        <option value="{{ $unit }}">{{ __('deals.recurrence_units.'.$unit) }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input inputmode="numeric" wire:model="draft.recurrence.count" :label="__('deals.composer.times')" />
                            </div>
                            @break
                    @endswitch
                </div>
            @endforeach

            @if ($missingBlocks)
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-zinc-500">{{ __('deals.composer.add_term') }}</span>
                    @foreach ($missingBlocks as $block)
                        <flux:button size="xs" wire:click="showBlock('{{ $block }}')" wire:key="add-{{ $block }}">+ {{ __('deals.composer.blocks.'.$block) }}</flux:button>
                    @endforeach
                </div>
            @endif

            {{-- Typed fields from the shared field library --}}
            <div class="space-y-3">
                <div class="text-sm font-medium">{{ __('deals.composer.details') }}</div>
                @forelse ($draft['fields'] as $i => $f)
                    @php
                        $path = 'fields.'.$f['key'];
                        $was = $base[$path]['value'] ?? null;
                        $now = \App\Support\Deals\DealTerms::displayValue($f);
                        $spec = \App\Support\Market\IntentDetailCatalog::all()[$f['key']] ?? [];
                    @endphp
                    <div wire:key="field-{{ $f['key'] }}" class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="text-sm" dir="auto">{{ $f['label'] }}@if ($f['unit'] !== '' && in_array($f['type'], ['number', 'amount'], true)) <span class="text-zinc-500">({{ \App\Support\Market\IntentDetailCatalog::unitLabel($f['unit']) }})</span>@endif</div>
                                @switch($f['type'])
                                    @case('boolean')
                                        <flux:select wire:model="draft.fields.{{ $i }}.value" aria-label="{{ $f['label'] }}">
                                            <option value="true">{{ __('composer.yes') }}</option>
                                            <option value="false">{{ __('composer.no') }}</option>
                                        </flux:select>
                                        @break
                                    @case('choice')
                                        <flux:select wire:model="draft.fields.{{ $i }}.value" aria-label="{{ $f['label'] }}">
                                            <option value="">—</option>
                                            @foreach (\App\Support\Market\IntentDetailCatalog::selectableOptions($f['key'], $f['value']) as $option)
                                                <option value="{{ $option }}">{{ \App\Support\Market\IntentDetailCatalog::optionLabel($option) }}</option>
                                            @endforeach
                                        </flux:select>
                                        @break
                                    @case('date')
                                        <x-app.calendar-date-input model="draft.fields.{{ $i }}.value" :label="$f['label']" />
                                        @break
                                    @case('long_text')
                                        <flux:textarea wire:model="draft.fields.{{ $i }}.value" rows="2" aria-label="{{ $f['label'] }}" />
                                        @break
                                    @case('number')
                                    @case('amount')
                                    @case('year')
                                        <flux:input inputmode="decimal" wire:model="draft.fields.{{ $i }}.value" aria-label="{{ $f['label'] }}" />
                                        @break
                                    @default
                                        <flux:input wire:model="draft.fields.{{ $i }}.value" aria-label="{{ $f['label'] }}" />
                                @endswitch
                                @if ($was === null && $baseVersion)
                                    <div class="text-xs text-emerald-600">{{ __('deals.composer.new') }}</div>
                                @elseif ($was !== null && $was !== $now)
                                    <div class="text-xs text-amber-600" dir="auto">{{ __('deals.composer.was', ['value' => $was]) }}</div>
                                @endif
                            </div>
                            <button type="button" wire:click="removeField({{ $i }})" class="text-xs text-zinc-500 underline" aria-label="{{ __('deals.composer.remove') }}">{{ __('deals.composer.remove') }}</button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('deals.composer.no_details') }}</p>
                @endforelse

                @if ($picking)
                    <div class="space-y-2 rounded-xl border border-dashed border-zinc-300 p-3 dark:border-zinc-600">
                        <flux:input wire:model.live.debounce.250ms="search" :placeholder="__('deals.composer.search')" autofocus />
                        <div class="flex flex-wrap gap-2">
                            @foreach ($library as $item)
                                <flux:button size="xs" wire:click="addField('{{ $item['key'] }}')" wire:key="lib-{{ $item['key'] }}">{{ $item['label'] }}</flux:button>
                            @endforeach
                            @if (trim($search) !== '')
                                <flux:button size="xs" variant="primary" wire:click="addCustom">{{ __('deals.composer.add_custom', ['label' => $search]) }}</flux:button>
                            @endif
                        </div>
                    </div>
                @else
                    <flux:button size="sm" wire:click="$set('picking', true)">+ {{ __('deals.composer.add_detail') }}</flux:button>
                @endif
            </div>

            <flux:textarea wire:model="draft.notes" rows="3" :label="__('deals.composer.notes')" :placeholder="__('deals.composer.notes_placeholder')" />
            <flux:input wire:model="versionNote" :label="__('deals.composer.version_note')" maxlength="1000" />
            <p class="text-xs text-zinc-500">{{ __('deals.propose_note') }}</p>

            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="propose" variant="primary" wire:loading.attr="disabled" wire:target="propose">{{ __('deals.propose_send') }}</flux:button>
                <flux:button wire:click="close" variant="ghost">{{ __('studio.cancel') }}</flux:button>
            </div>
        </flux:card>
    @endif
</div>
