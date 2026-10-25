@php
    $picker = $profile['picker'];
    $side = in_array($intention, ['need', 'person'], true) ? 'need' : 'label';
    $termName = fn (string $block): string => (string) __('composer.terms.'.$block.'.'.($side === 'need' && in_array($block, ['price', 'place'], true) ? 'need' : 'label'));
    $row = 'flex min-h-11 w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-start hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-800';
    $heading = 'mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500';
    $kindOf = fn (string $key): string => (string) __('composer.domains.'.(\App\Support\Market\IntentDetailCatalog::all()[$key]['domain'] ?? 'general'));
    $label = fn (string $key): string => \App\Support\Market\IntentDetailCatalog::label($key, null, $topic ?: null);
    $taken = array_values(array_filter(array_column($details, 'canonical_key')));
    $fieldName = fn (array $field): string => ! empty($field['canonical_key']) ? $label($field['canonical_key']) : (string) ($field['label'] ?? '');
    $context = ['topic' => $topic, 'intention' => $needSide ? 'need' : 'offer', 'arrangement' => $core['arrangement_kind'] ?? '', 'selected' => $taken];
@endphp
<div class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-start sm:p-6" wire:key="composer-picker" x-on:keydown.escape.window="$wire.closePicker()" x-on:click.self="$wire.closePicker()" data-composer-picker>
    <div role="dialog" aria-modal="true" aria-label="{{ __('composer.picker.title') }}" class="flex max-h-[88dvh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:mt-12 sm:max-w-xl sm:rounded-2xl dark:bg-zinc-900">
        <div class="flex items-center gap-2 border-b p-3">
            <flux:input wire:model.live.debounce.200ms="pickerSearch" type="search" :placeholder="__('composer.picker.search')" :aria-label="__('composer.picker.search')" class="flex-1" x-init="$nextTick(() => $el.querySelector('input')?.focus())" data-picker-search />
            <button type="button" wire:click="closePicker" class="grid size-11 shrink-0 place-items-center rounded-full hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-800" aria-label="{{ __('composer.picker.close') }}"><flux:icon.x-mark class="size-5" /></button>
        </div>
        <div class="space-y-5 overflow-y-auto p-3">
            @if($picker['query'] !== '')
                @foreach($picker['terms'] as $block)
                    <button type="button" wire:click="addTerm('{{ $block }}')" class="{{ $row }}"><span>{{ $termName($block) }}</span><span class="text-xs text-zinc-500">{{ __('composer.picker.terms') }}</span></button>
                @endforeach
                @foreach($picker['matches'] as $key)
                    <button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="match-{{ $key }}"><span>{{ $label($key) }}</span><span class="text-xs text-zinc-500">{{ $kindOf($key) }}</span></button>
                @endforeach
                @foreach($picker['saved'] as $saved)
                    <button type="button" wire:click="applyTemplate('{{ $saved->uuid }}')" class="{{ $row }}" wire:key="saved-match-{{ $saved->uuid }}"><span dir="auto">{{ $saved->name }}</span><span class="text-xs text-zinc-500">{{ __('composer.saved_kinds.'.$saved->kind) }}</span></button>
                @endforeach
                @if(count($picker['terms']) + count($picker['matches']) + count($picker['saved']) === 0)<p class="px-3 text-sm text-zinc-600 dark:text-zinc-400">{{ __('composer.picker.none') }}</p>@endif
                <button type="button" wire:click="createCustom" class="{{ $row }} border border-dashed"><span dir="auto">{{ __('composer.picker.create_named', ['name' => $picker['query']]) }}</span><flux:icon.plus class="size-4" /></button>
            @else
                @if(count($picker['recommendedFields']) || count($picker['recommendedTerms']))
                    <section aria-label="{{ __('composer.picker.recommended') }}">
                        <h3 class="{{ $heading }}">{{ __('composer.picker.recommended') }}</h3>
                        @foreach($picker['recommendedTerms'] as $block)<button type="button" wire:click="addTerm('{{ $block }}')" class="{{ $row }}"><span>{{ $termName($block) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                        @foreach($picker['recommendedFields'] as $key)<button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="rec-{{ $key }}"><span>{{ $label($key) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                    </section>
                @endif
                @if(count($picker['recent']))
                    <section aria-label="{{ __('composer.picker.recent') }}">
                        <h3 class="{{ $heading }}">{{ __('composer.picker.recent') }}</h3>
                        @foreach($picker['recent'] as $key)<button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="recent-{{ $key }}"><span>{{ $label($key) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                    </section>
                @endif
                @if($capabilities->isNotEmpty() && $intent === null)
                    <section aria-label="{{ __('composer.picker_abilities') }}">
                        <h3 class="{{ $heading }}">{{ __('composer.picker_abilities') }}</h3>
                        @foreach($capabilities as $capability)<button type="button" wire:click="useCapability('{{ $capability->uuid }}')" class="{{ $row }}" wire:key="ability-{{ $capability->uuid }}"><span dir="auto">{{ $capability->title ?: $capability->concept?->displayLabel(app()->getLocale()) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                    </section>
                @endif
                @php($myFields = $picker['saved']->where('kind', 'field'))
                @if($myFields->isNotEmpty())
                    <section aria-label="{{ __('composer.my_fields') }}" data-my-fields>
                        <h3 class="{{ $heading }}">{{ __('composer.my_fields') }}</h3>
                        @foreach($myFields as $saved)
                            <div class="flex items-center gap-1" wire:key="myfield-{{ $saved->uuid }}">
                                <button type="button" wire:click="applyTemplate('{{ $saved->uuid }}')" class="{{ $row }}"><span dir="auto">{{ $saved->name }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>
                                <flux:button size="sm" variant="ghost" wire:click="deleteTemplate('{{ $saved->uuid }}')" wire:confirm="{{ __('composer.delete_confirm') }}">{{ __('composer.delete') }}</flux:button>
                            </div>
                        @endforeach
                    </section>
                @endif
                @php($myGroups = $picker['saved']->where('kind', 'group'))
                @if($myGroups->isNotEmpty())
                    <section aria-label="{{ __('composer.my_groups') }}" data-my-groups>
                        <h3 class="{{ $heading }}">{{ __('composer.my_groups') }}</h3>
                        @foreach($myGroups->whereNull('parent_id') as $group)
                            @foreach([[$group, 0], ...$myGroups->where('parent_id', $group->id)->map(fn ($child) => [$child, 1])->values()->all()] as [$node, $level])
                                <div class="rounded-lg {{ $level ? 'ms-4 border-s ps-2' : '' }}" wire:key="mygroup-{{ $node->uuid }}">
                                    <div class="flex items-center gap-1">
                                        @if($renaming === $node->uuid)
                                            <flux:input wire:model="renameTo" wire:keydown.enter="saveRename" :aria-label="__('composer.rename')" class="flex-1" dir="auto" />
                                            <flux:button size="sm" wire:click="saveRename">{{ __('composer.rename') }}</flux:button>
                                        @else
                                            <button type="button" wire:click="applyTemplate('{{ $node->uuid }}')" class="{{ $row }}"><span dir="auto">{{ $node->name }} <span class="text-xs text-zinc-500">· {{ count($node->fields) }}</span></span><span class="text-xs text-zinc-500">{{ __('composer.add_group') }}</span></button>
                                            <flux:button size="sm" variant="ghost" wire:click="manageGroup('{{ $node->uuid }}')" aria-expanded="{{ $managingGroup === $node->uuid ? 'true' : 'false' }}">{{ __('composer.manage') }}</flux:button>
                                        @endif
                                    </div>
                                    @if($managingGroup === $node->uuid)
                                        <div class="space-y-1 pb-2 ps-3" data-group-manager>
                                            @foreach($node->fields as $position => $field)
                                                <div class="flex items-center gap-1 text-sm" wire:key="gf-{{ $node->uuid }}-{{ $position }}">
                                                    <span class="flex-1" dir="auto">{{ $fieldName($field) }}</span>
                                                    <button type="button" wire:click="moveInGroup('{{ $node->uuid }}', {{ $position }}, -1)" class="grid size-8 place-items-center rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('composer.up') }}"><flux:icon.chevron-up class="size-4" /></button>
                                                    <button type="button" wire:click="moveInGroup('{{ $node->uuid }}', {{ $position }}, 1)" class="grid size-8 place-items-center rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('composer.down') }}"><flux:icon.chevron-down class="size-4" /></button>
                                                    <button type="button" wire:click="removeFromGroup('{{ $node->uuid }}', {{ $position }})" class="grid size-8 place-items-center rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800" aria-label="{{ __('composer.remove') }}"><flux:icon.x-mark class="size-4" /></button>
                                                </div>
                                            @endforeach
                                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                                <flux:button size="sm" variant="ghost" wire:click="startRename('{{ $node->uuid }}')">{{ __('composer.rename') }}</flux:button>
                                                @if($level === 0 && $myGroups->where('parent_id', $node->id)->isEmpty())
                                                    <select wire:change="nestGroup('{{ $node->uuid }}', $event.target.value)" aria-label="{{ __('composer.group_inside') }}" class="rounded-lg border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900"><option value="">{{ __('composer.group_inside') }}</option>@foreach($myGroups->whereNull('parent_id')->where('id', '!=', $node->id) as $parent)<option value="{{ $parent->uuid }}">{{ $parent->name }}</option>@endforeach</select>
                                                @elseif($level === 1)
                                                    <flux:button size="sm" variant="ghost" wire:click="nestGroup('{{ $node->uuid }}')">{{ __('composer.group_top_level') }}</flux:button>
                                                @endif
                                                <flux:button size="sm" variant="ghost" wire:click="deleteTemplate('{{ $node->uuid }}')" wire:confirm="{{ __('composer.delete_confirm') }}">{{ __('composer.delete') }}</flux:button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </section>
                @endif
                @if(count($picker['terms']))
                    <section aria-label="{{ __('composer.picker.terms') }}">
                        <h3 class="{{ $heading }}">{{ __('composer.picker.terms') }}</h3>
                        @foreach($picker['terms'] as $block)<button type="button" wire:click="addTerm('{{ $block }}')" class="{{ $row }}" wire:key="term-{{ $block }}"><span>{{ $termName($block) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                    </section>
                @endif
                <section aria-label="{{ __('composer.picker.library') }}" data-library-tree>
                    <h3 class="{{ $heading }}">{{ __('composer.picker.library') }}</h3>
                    @foreach($picker['tree'] as $domain => $branch)
                        @php($domainFields = collect($branch['rest'])->merge(collect($branch['groups'])->flatMap(fn ($group) => $group['fields']))->unique()->diff($taken))
                        <details class="rounded-lg" wire:key="domain-{{ $domain }}">
                            <summary class="{{ $row }} cursor-pointer font-medium"><span>{{ __('composer.domains.'.$domain) }}</span><span class="text-xs text-zinc-500">{{ $domainFields->count() }}</span></summary>
                            <div class="ms-3 space-y-1 border-s ps-2">
                                @foreach($branch['groups'] as $group)
                                    @php($left = collect($group['fields'])->diff($taken))
                                    <details class="rounded-lg" wire:key="grp-{{ $group['key'] }}">
                                        <summary class="{{ $row }} cursor-pointer"><span>{{ $group['label'] }}</span><span class="text-xs text-zinc-500">{{ $left->count() }}</span></summary>
                                        <div class="ms-3 border-s ps-2">
                                            @foreach($left as $key)<button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="lib-{{ $group['key'] }}-{{ $key }}"><span>{{ $label($key) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                                            @foreach($group['children'] as $child)
                                                <p class="px-3 pt-2 text-xs font-semibold text-zinc-500">{{ $child['label'] }}</p>
                                                @foreach(collect($child['fields'])->diff($taken) as $key)<button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="lib-{{ $child['key'] }}-{{ $key }}"><span>{{ $label($key) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                                            @endforeach
                                            <button type="button" x-on:click="$dispatch('open-suggestion', {{ \Illuminate\Support\Js::from([...$context, 'key' => null, 'group' => $group['key']]) }})" class="px-3 py-1 text-xs text-zinc-500 underline-offset-2 hover:underline" data-suggest-group>{{ __('composer.suggest_improvement') }}</button>
                                        </div>
                                    </details>
                                @endforeach
                                @foreach(collect($branch['rest'])->diff($taken) as $key)<button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="lib-rest-{{ $key }}"><span>{{ $label($key) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                            </div>
                        </details>
                    @endforeach
                </section>
                <section class="space-y-2 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/40" aria-label="{{ __('composer.picker.custom') }}">
                    <h3 class="text-sm font-semibold">{{ __('composer.picker.custom') }}</h3>
                    <div class="flex gap-2"><flux:input wire:model="customLabel" wire:keydown.enter="createCustom" :aria-label="__('composer.detail_name')" :placeholder="__('composer.detail_name')" class="flex-1" dir="auto" /><flux:button wire:click="createCustom">{{ __('composer.add') }}</flux:button></div>
                </section>
                <p class="px-1 text-center"><button type="button" x-on:click="$dispatch('open-suggestion', {{ \Illuminate\Support\Js::from([...$context, 'key' => null, 'group' => null]) }})" class="text-xs text-zinc-500 underline-offset-2 hover:underline" data-suggest-library>{{ __('composer.library_suggest') }}</button></p>
            @endif
        </div>
    </div>
</div>
