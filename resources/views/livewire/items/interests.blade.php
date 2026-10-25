<section class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900" id="interests" aria-labelledby="interests-heading" data-interests>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex items-center gap-2">
                <flux:icon name="heart" class="size-5 text-zinc-500" />
                <flux:heading size="lg" level="2" id="interests-heading">{{ __('items.interests.title') }}</flux:heading>
                <flux:badge size="sm" data-interests-count>{{ $interests->count() }}</flux:badge>
            </div>
            <flux:text size="sm">{{ __('items.interests.help') }}</flux:text>
        </div>
        @unless ($formOpen)
            <flux:button variant="primary" size="sm" icon="plus" wire:click="add" class="self-start" data-interest-add>{{ __('items.interests.add') }}</flux:button>
        @endunless
    </header>

    @if ($notice)
        <div class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200" role="status" data-interest-notice>
            <flux:icon name="check-circle" class="size-4 shrink-0" />
            <span>{{ $notice }}</span>
        </div>
    @endif

    @if ($formOpen)
        <div class="space-y-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/60" data-interest-form wire:key="interest-form-{{ $editing ?? 'new' }}">
            <flux:heading size="md" level="3">{{ $editing ? __('items.interests.edit') : __('items.interests.add') }}</flux:heading>

            <div class="space-y-1">
                <flux:label>{{ __('items.interests.fields.category') }} <span class="text-red-600" aria-hidden="true">*</span></flux:label>
                <flux:text size="sm">{{ __('items.interests.fields.category_help') }}</flux:text>
                <livewire:categories.picker wire:model.live="conceptId" :owner="$ownerKey" :key="'interest-picker-'.($editing ?? 'new')" />
                <flux:error name="conceptId" />
            </div>

            <fieldset class="space-y-2" data-interest-purposes>
                <legend class="text-sm font-medium text-zinc-800 dark:text-white">{{ __('items.interests.fields.purposes') }} <span class="text-red-600" aria-hidden="true">*</span></legend>
                <flux:text size="sm">{{ __('items.interests.fields.purposes_help') }}</flux:text>
                <div class="flex flex-wrap gap-2 pt-1">
                    @foreach (\App\Models\Interest::PURPOSES as $purpose)
                        @php($on = in_array($purpose, $purposes, true))
                        <button type="button"
                            wire:click="togglePurpose('{{ $purpose }}')"
                            aria-pressed="{{ $on ? 'true' : 'false' }}"
                            data-purpose="{{ $purpose }}"
                            @class([
                                'inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 dark:focus-visible:ring-white',
                                'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $on,
                                'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-500 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200' => ! $on,
                            ])>
                            @if ($on)
                                <flux:icon name="check" class="size-4" />
                            @endif
                            {{ __('items.interests.purposes.'.$purpose) }}
                        </button>
                    @endforeach
                </div>
                <flux:error name="purposes" />
            </fieldset>

            <flux:radio.group wire:model="visibility" :label="__('items.interests.fields.visibility')" data-interest-visibility>
                <flux:radio value="private" :label="__('items.interests.visibility.private')" :description="__('items.interests.visibility.private_help')" />
                <flux:radio value="profile" :label="__('items.interests.visibility.profile')" :description="__('items.interests.visibility.profile_help')" />
            </flux:radio.group>

            <button type="button" wire:click="$toggle('showMore')" class="inline-flex items-center gap-1 text-sm font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-200" aria-expanded="{{ $showMore ? 'true' : 'false' }}">
                <flux:icon :name="$showMore ? 'chevron-up' : 'chevron-down'" class="size-4" />
                {{ $showMore ? __('items.fewer_details') : __('items.more_details') }}
            </button>
            @if ($showMore)
                <flux:textarea wire:model="note" rows="2" maxlength="500" :label="__('items.interests.fields.note')" :description="__('items.interests.fields.note_help')" />
            @endif

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="cancel">{{ __('ui.common.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save" data-interest-save>
                    <span wire:loading.remove wire:target="save">{{ __('items.save') }}</span>
                    <span wire:loading wire:target="save">{{ __('items.saving') }}</span>
                </flux:button>
            </div>
        </div>
    @endif

    <div class="space-y-2" data-interests-list>
        @forelse ($interests as $interest)
            <article wire:key="interest-{{ $interest->uuid }}" class="rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800" data-interest="{{ $interest->uuid }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="font-semibold [overflow-wrap:anywhere]" dir="auto">{{ $labels[$interest->concept_id] ?? '' }}</div>
                        <div class="flex flex-wrap gap-1.5 text-xs">
                            @foreach ($interest->purposes as $purpose)
                                <flux:badge size="sm" color="sky">{{ __('items.interests.purposes.'.$purpose) }}</flux:badge>
                            @endforeach
                            <flux:badge size="sm" color="zinc" :icon="$interest->visibility === 'private' ? 'lock-closed' : 'eye'">{{ __('items.interests.visibility.'.$interest->visibility) }}</flux:badge>
                        </div>
                        @if ($interest->note)
                            <p class="text-sm text-zinc-600 [overflow-wrap:anywhere] dark:text-zinc-300" dir="auto">{{ $interest->note }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $interest->uuid }}')" :aria-label="__('items.interests.edit_named', ['name' => $labels[$interest->concept_id] ?? ''])" data-interest-edit />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="remove('{{ $interest->uuid }}')" wire:confirm="{{ __('items.interests.remove_confirm') }}" :aria-label="__('items.interests.remove_named', ['name' => $labels[$interest->concept_id] ?? ''])" data-interest-remove />
                    </div>
                </div>
            </article>
        @empty
            @unless ($formOpen)
                <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center dark:border-zinc-700" data-interests-empty>
                    <flux:text class="mx-auto max-w-md">{{ __('items.interests.empty') }}</flux:text>
                    <flux:button class="mt-3" size="sm" icon="plus" wire:click="add">{{ __('items.interests.add') }}</flux:button>
                </div>
            @endunless
        @endforelse
    </div>
</section>
