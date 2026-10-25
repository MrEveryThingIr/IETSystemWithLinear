@php
    $picker = $profile['picker'];
    $side = in_array($intention, ['need', 'person'], true) ? 'need' : 'label';
    $termName = fn (string $block): string => (string) __('composer.terms.'.$block.'.'.($side === 'need' && in_array($block, ['price', 'place'], true) ? 'need' : 'label'));
    $row = 'flex min-h-11 w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-start hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-800';
    $kindOf = fn (string $key): string => (string) __('composer.domains.'.(\App\Support\Market\IntentDetailCatalog::all()[$key]['domain'] ?? 'general'));
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
                    <button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="match-{{ $key }}"><span>{{ \App\Support\Market\IntentDetailCatalog::label($key, null, $topic ?: null) }}</span><span class="text-xs text-zinc-500">{{ $kindOf($key) }}</span></button>
                @endforeach
                @foreach($picker['saved'] as $saved)
                    <button type="button" wire:click="applyTemplate('{{ $saved->uuid }}')" class="{{ $row }}" wire:key="saved-match-{{ $saved->uuid }}"><span dir="auto">{{ $saved->name }}</span><span class="text-xs text-zinc-500">{{ __('composer.saved_kinds.'.$saved->kind) }}</span></button>
                @endforeach
                @if(count($picker['terms']) + count($picker['matches']) + count($picker['saved']) === 0)<p class="px-3 text-sm text-zinc-600 dark:text-zinc-400">{{ __('composer.picker.none') }}</p>@endif
                <button type="button" wire:click="createCustom" class="{{ $row }} border border-dashed"><span dir="auto">{{ __('composer.picker.create_named', ['name' => $picker['query']]) }}</span><flux:icon.plus class="size-4" /></button>
            @else
                @if(count($picker['recommendedFields']) || count($picker['recommendedTerms']))
                    <section aria-label="{{ __('composer.picker.recommended') }}">
                        <h3 class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('composer.picker.recommended') }}</h3>
                        @foreach($picker['recommendedTerms'] as $block)<button type="button" wire:click="addTerm('{{ $block }}')" class="{{ $row }}"><span>{{ $termName($block) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                        @foreach($picker['recommendedFields'] as $key)<button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="rec-{{ $key }}"><span>{{ \App\Support\Market\IntentDetailCatalog::label($key, null, $topic ?: null) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                    </section>
                @endif
                @if(count($picker['recent']))
                    <section aria-label="{{ __('composer.picker.recent') }}">
                        <h3 class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('composer.picker.recent') }}</h3>
                        @foreach($picker['recent'] as $key)<button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="recent-{{ $key }}"><span>{{ \App\Support\Market\IntentDetailCatalog::label($key, null, $topic ?: null) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                    </section>
                @endif
                @if($picker['saved']->isNotEmpty())
                    <section aria-label="{{ __('composer.picker.saved') }}">
                        <h3 class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('composer.picker.saved') }}</h3>
                        @foreach($picker['saved'] as $saved)
                            <div class="flex items-center gap-1" wire:key="saved-{{ $saved->uuid }}">
                                @if($renaming === $saved->uuid)
                                    <flux:input wire:model="renameTo" wire:keydown.enter="saveRename" :aria-label="__('composer.rename')" class="flex-1" />
                                    <flux:button size="sm" wire:click="saveRename">{{ __('composer.rename') }}</flux:button>
                                @else
                                    <button type="button" wire:click="applyTemplate('{{ $saved->uuid }}')" class="{{ $row }}"><span dir="auto">{{ $saved->name }} <span class="text-xs text-zinc-500">· {{ __('composer.saved_kinds.'.$saved->kind) }}</span></span><flux:icon.plus class="size-4 text-zinc-400" /></button>
                                    <flux:button size="sm" variant="ghost" wire:click="startRename('{{ $saved->uuid }}')">{{ __('composer.rename') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="deleteTemplate('{{ $saved->uuid }}')" wire:confirm="{{ __('composer.delete_confirm') }}">{{ __('composer.delete') }}</flux:button>
                                @endif
                            </div>
                        @endforeach
                    </section>
                @endif
                @if(count($picker['terms']))
                    <section aria-label="{{ __('composer.picker.terms') }}">
                        <h3 class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('composer.picker.terms') }}</h3>
                        @foreach($picker['terms'] as $block)<button type="button" wire:click="addTerm('{{ $block }}')" class="{{ $row }}" wire:key="term-{{ $block }}"><span>{{ $termName($block) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach
                    </section>
                @endif
                <section aria-label="{{ __('composer.picker.library') }}">
                    <h3 class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('composer.picker.library') }}</h3>
                    @foreach($picker['groups'] as $domain => $keys)
                        <details class="rounded-lg" wire:key="domain-{{ $domain }}">
                            <summary class="{{ $row }} cursor-pointer font-medium"><span>{{ __('composer.domains.'.$domain) }}</span><span class="text-xs text-zinc-500">{{ count($keys) }}</span></summary>
                            <div class="ms-3 border-s ps-2">@foreach($keys as $key)<button type="button" wire:click="addField('{{ $key }}')" class="{{ $row }}" wire:key="lib-{{ $key }}"><span>{{ \App\Support\Market\IntentDetailCatalog::label($key, null, $topic ?: null) }}</span><flux:icon.plus class="size-4 text-zinc-400" /></button>@endforeach</div>
                        </details>
                    @endforeach
                </section>
                <section class="space-y-2 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/40" aria-label="{{ __('composer.picker.custom') }}">
                    <h3 class="text-sm font-semibold">{{ __('composer.picker.custom') }}</h3>
                    <div class="flex gap-2"><flux:input wire:model="customLabel" wire:keydown.enter="createCustom" :aria-label="__('composer.detail_name')" :placeholder="__('composer.detail_name')" class="flex-1" dir="auto" /><flux:button wire:click="createCustom">{{ __('composer.add') }}</flux:button></div>
                </section>
                @if(count($details))
                    <section class="space-y-2 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/40" aria-label="{{ __('composer.save_group') }}">
                        <div class="flex gap-2"><flux:input wire:model="groupName" :placeholder="__('composer.group_name')" :aria-label="__('composer.group_name')" class="flex-1" dir="auto" /><flux:button wire:click="saveTemplate('group')">{{ __('composer.save_group') }}</flux:button></div>
                        <div class="flex gap-2"><flux:input wire:model="templateName" :placeholder="__('composer.template_name')" :aria-label="__('composer.template_name')" class="flex-1" dir="auto" /><flux:button wire:click="saveTemplate('composition')">{{ __('composer.save_template') }}</flux:button></div>
                    </section>
                @endif
            @endif
        </div>
    </div>
</div>
