<div>
    @if ($open)
        <flux:card class="space-y-5" data-agreement-composer>
            <div>
                <flux:heading>{{ __('deals.composer.title') }}</flux:heading>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ $fromOrigin ? __('deals.composer.from_origin') : __('deals.composer.from_version', ['version' => $baseVersion]) }}
                </p>
            </div>

            @error('draft') <flux:callout variant="danger">{{ $message }}</flux:callout> @enderror

            <flux:input wire:model="draft.title" :label="__('deals.composer.agreement_title')" maxlength="180" />
            <flux:textarea wire:model="draft.summary" rows="2" :label="__('deals.composer.summary')" :placeholder="__('deals.composer.summary_placeholder')" />

            @if ($partyNames)
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-zinc-500">{{ __('deals.composer.parties') }}</span>
                    @foreach ($partyNames as $name)<flux:badge size="sm" color="zinc">{{ $name }}</flux:badge>@endforeach
                </div>
            @endif

            {{-- Ordered terms: each one edits, moves and goes away on its own while this is a draft --}}
            <ol class="space-y-3">
                @foreach ($draft['items'] as $position => $id)
                    @php
                        $isClause = str_starts_with($id, 'clause:');
                        $ci = $isClause ? array_search(substr($id, 7), array_column($draft['clauses'], 'key'), true) : null;
                        $clause = $ci !== null && $ci !== false ? $draft['clauses'][$ci] : null;
                        if ($isClause && ! $clause) { continue; }
                        $heading = $isClause ? __('deals.clauses.'.$clause['kind'].'.title') : __('deals.doc.sections.'.$id);
                        $number = $numbers[$id] ?? ['no' => (string) ($position + 1), 'heading' => true];
                    @endphp
                    <li wire:key="item-{{ $id }}" class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-agreement-item="{{ $id }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium"><span class="text-zinc-400">{{ $number['no'] }}</span> @if ($number['heading']){{ $heading }}@else<span class="text-zinc-400" data-clause-continued>↳</span>@endif</span>
                            <span class="flex items-center gap-1">
                                <button type="button" wire:click="move('{{ $id }}', -1)" @disabled($position === 0) class="rounded px-2 py-1 text-xs hover:bg-zinc-100 disabled:opacity-30 dark:hover:bg-zinc-800" aria-label="{{ __('deals.composer.up') }}">↑</button>
                                <button type="button" wire:click="move('{{ $id }}', 1)" @disabled($position === count($draft['items']) - 1) class="rounded px-2 py-1 text-xs hover:bg-zinc-100 disabled:opacity-30 dark:hover:bg-zinc-800" aria-label="{{ __('deals.composer.down') }}">↓</button>
                                <button type="button" wire:click="removeItem('{{ $id }}')" class="rounded px-2 py-1 text-xs text-zinc-500 underline" aria-label="{{ __('deals.composer.remove') }}">{{ __('deals.composer.remove') }}</button>
                            </span>
                        </div>

                        @if ($isClause)
                            <flux:input wire:model="draft.clauses.{{ $ci }}.title" :placeholder="$heading" aria-label="{{ __('deals.composer.clause_title') }}" maxlength="120" />
                            <flux:textarea wire:model="draft.clauses.{{ $ci }}.text" rows="3" :placeholder="__('deals.clauses.'.$clause['kind'].'.hint')" aria-label="{{ $heading }}" />
                        @else
                            @switch($id)
                                @case('price')
                                    @if ($legacyPrice !== '')
                                        <flux:callout variant="warning" data-legacy-currency>{{ __('deals.composer.legacy_currency', ['price' => $legacyPrice]) }}</flux:callout>
                                    @endif
                                    <div class="grid gap-2 sm:grid-cols-4">
                                        <flux:input inputmode="decimal" wire:model="draft.core.price_min" :label="__('deals.change.price_min')" />
                                        <flux:input inputmode="decimal" wire:model="draft.core.price_max" :label="__('deals.change.price_max')" />
                                        <flux:select wire:model="draft.core.currency" :label="__('deals.change.price_currency')" data-currency>
                                            @foreach ($currencies as $code)
                                                <option value="{{ $code }}">{{ $code }} · {{ __('deals.currencies.'.$code) }}</option>
                                            @endforeach
                                        </flux:select>
                                        <flux:select wire:model.live="draft.core.basis_choice" :label="__('deals.change.price_basis')" data-basis>
                                            <option value="">—</option>
                                            @foreach ($bases as $unit)
                                                <option value="{{ $unit }}">{{ __('deals.bases.'.$unit) }}</option>
                                            @endforeach
                                            <option value="__other">{{ __('deals.bases.other') }}</option>
                                        </flux:select>
                                    </div>
                                    @if (($draft['core']['basis_choice'] ?? '') === '__other')
                                        <flux:input wire:model="draft.core.basis_custom" :label="__('deals.composer.basis_custom')" maxlength="40" />
                                    @endif
                                    @break
                                @case('payment')
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <flux:select wire:model.live="draft.payment.mode" :label="__('deals.composer.payment_rule')">
                                            <option value="">—</option>
                                            @foreach (\App\Support\Deals\DealTerms::PAYMENT_MODES as $mode)
                                                <option value="{{ $mode }}">{{ __('deals.payment_modes.'.$mode) }}</option>
                                            @endforeach
                                        </flux:select>
                                        @if (($draft['payment']['mode'] ?? '') === 'per_period')
                                            <flux:select wire:model="draft.payment.period" :label="__('deals.composer.unit')">
                                                @foreach (['week', 'month'] as $unit)
                                                    <option value="{{ $unit }}">{{ __('deals.recurrence_units.'.$unit) }}</option>
                                                @endforeach
                                            </flux:select>
                                        @endif
                                    </div>
                                    <flux:input wire:model="draft.payment.note" :label="__('deals.composer.payment_note')" maxlength="500" />
                                    @break
                                @case('place')
                                    <flux:input wire:model="draft.core.place" :label="__('deals.change.place')" />
                                    @break
                                @case('time')
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <x-app.calendar-date-input model="draft.core.starts_on" :label="__('deals.change.time_starts_on')" />
                                        <x-app.calendar-date-input model="draft.core.ends_on" :label="__('deals.change.time_ends_on')" />
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-3" data-working-hours>
                                        <flux:input type="time" wire:model="draft.core.daily_from" :label="__('deals.composer.hours_from')" />
                                        <flux:input type="time" wire:model="draft.core.daily_to" :label="__('deals.composer.hours_to')" />
                                        <flux:select wire:model="draft.core.timezone" :label="__('deals.composer.timezone')">
                                            @foreach ($timezones as $zone)
                                                <option value="{{ $zone }}">{{ $zone }}</option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <p class="text-xs text-zinc-500">{{ __('deals.composer.hours_help') }}</p>
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
                                    <p class="text-xs text-zinc-500">{{ __('deals.composer.recurrence_note') }}</p>
                                    @break
                                @case('completion')
                                    <flux:select wire:model="draft.completion.confirmed_by" :label="__('deals.composer.completion_rule')">
                                        <option value="">—</option>
                                        @foreach (\App\Support\Deals\DealTerms::COMPLETION_CONFIRMERS as $by)
                                            <option value="{{ $by }}">{{ __('deals.completion_by.'.$by) }}</option>
                                        @endforeach
                                    </flux:select>
                                    @break
                                @case('notes')
                                    <flux:textarea wire:model="draft.notes" rows="3" aria-label="{{ __('deals.composer.notes') }}" :placeholder="__('deals.composer.notes_placeholder')" />
                                    @break
                                @case('fields')
                                    <div class="space-y-3">
                                        @foreach ($draft['fields'] as $i => $f)
                                            @php
                                                $was = $base['fields.'.$f['key']]['value'] ?? null;
                                                $now = \App\Support\Deals\DealTerms::displayValue($f);
                                            @endphp
                                            <div wire:key="field-{{ $f['key'] }}" class="flex items-start gap-2">
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
                                                <button type="button" wire:click="removeField({{ $i }})" class="text-xs text-zinc-500 underline">{{ __('deals.composer.remove') }}</button>
                                            </div>
                                        @endforeach

                                        @if ($picking)
                                            <div class="space-y-2 rounded-xl border border-dashed border-zinc-300 p-3 dark:border-zinc-600">
                                                <flux:input wire:model.live.debounce.250ms="search" :placeholder="__('deals.composer.search')" />
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
                                            <flux:button size="xs" wire:click="$set('picking', true)">+ {{ __('deals.composer.add_detail') }}</flux:button>
                                        @endif
                                    </div>
                                    @break
                            @endswitch
                        @endif
                    </li>
                @endforeach
            </ol>

            {{-- + Add term: a small, categorized library --}}
            @if ($libraryOpen)
                <div class="space-y-3 rounded-xl border border-dashed border-zinc-300 p-3 dark:border-zinc-600" data-clause-library>
                    @foreach ($categories as $category => $ids)
                        <div>
                            <div class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('deals.composer.categories.'.$category) }}</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($ids as $id)
                                    @if (str_starts_with($id, 'clause:') || ! in_array($id, $present, true))
                                        <flux:button size="xs" wire:click="addItem('{{ $id }}')" wire:key="add-{{ $id }}">+ {{ str_starts_with($id, 'clause:') ? __('deals.clauses.'.substr($id, 7).'.title') : __('deals.doc.sections.'.$id) }}</flux:button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:button wire:click="$set('libraryOpen', true)" size="sm">+ {{ __('deals.composer.add_term') }}</flux:button>
            @endif

            <flux:input wire:model="versionNote" :label="__('deals.composer.version_note')" maxlength="1000" />
            <p class="text-xs text-zinc-500">{{ __('deals.composer.send_note') }}</p>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button wire:click="send" variant="primary" wire:loading.attr="disabled" wire:target="send">{{ __('deals.composer.send_review') }}</flux:button>
                <flux:button wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">{{ __('deals.composer.save_draft') }}</flux:button>
                <flux:button wire:click="close" variant="ghost">{{ __('studio.cancel') }}</flux:button>
                @if ($savedAt !== '')
                    <flux:button wire:click="discardDraft" variant="ghost" size="sm" wire:confirm="{{ __('deals.composer.discard_confirm') }}">{{ __('deals.composer.discard') }}</flux:button>
                    <span class="text-xs text-zinc-500">{{ __('deals.composer.saved') }}</span>
                @endif
            </div>
        </flux:card>
    @endif
</div>
