<section class="mx-auto max-w-4xl space-y-6" data-capabilities-page>
    <header class="space-y-2">
        <flux:heading size="xl">{{ __('capabilities.title') }}</flux:heading>
        <flux:text>{{ __('capabilities.help') }}</flux:text>
    </header>

    <x-app.flash-message />

    <div class="flex flex-wrap items-end justify-between gap-3">
        @if ($parties->count() > 1)
            <div class="min-w-0 sm:w-72">
                <flux:select wire:model.live="ownerKey" :label="__('capabilities.whose')" data-capabilities-owner>
                    @foreach ($parties as $entry)
                        <option value="{{ $entry['party']->key() }}">{{ $entry['label'] }}</option>
                    @endforeach
                </flux:select>
            </div>
        @endif
        <flux:button variant="primary" icon="plus" wire:click="add" data-capability-add>{{ __('capabilities.add') }}</flux:button>
    </div>

    @if ($formOpen)
        <flux:card class="space-y-4" data-capability-form wire:key="capability-form-{{ $editing ?? 'new' }}-{{ $ownerKey }}">
            <flux:heading size="lg">{{ $editing ? __('capabilities.edit') : __('capabilities.add') }}</flux:heading>

            <div class="space-y-1">
                <flux:label>{{ __('capabilities.fields.category') }}</flux:label>
                <flux:text size="sm">{{ __('capabilities.fields.category_help') }}</flux:text>
                <livewire:categories.picker wire:model="conceptId" :owner="$ownerKey" :kinds="['skill', 'service']" :key="'capability-picker-'.$ownerKey.'-'.($editing ?? 'new')" />
                <flux:error name="conceptId" />
            </div>

            <flux:input wire:model="title" maxlength="160" :label="__('capabilities.fields.title')" :description="__('capabilities.fields.title_help')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="level" :label="__('capabilities.fields.level')" :description="__('capabilities.fields.level_help')">
                    <option value="">{{ __('capabilities.levels.none') }}</option>
                    @foreach (\App\Models\Capability::LEVELS as $levelKey)
                        <option value="{{ $levelKey }}">{{ __('capabilities.levels.'.$levelKey) }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="yearsExperience" type="number" min="0" max="80" inputmode="numeric" :label="__('capabilities.fields.years')" />
            </div>

            <div class="space-y-2" x-data="{ value: $wire.entangle('confidence') }" data-capability-confidence>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <flux:label>{{ __('capabilities.fields.confidence') }}</flux:label>
                    <span class="text-sm font-medium tabular-nums" x-text="value === null ? @js(__('capabilities.confidence_unset')) : value + '%'"></span>
                </div>
                <input type="range" min="0" max="100" step="5" class="w-full accent-zinc-900 dark:accent-white" x-bind:value="value ?? 50" x-on:input="value = parseInt($event.target.value)" aria-label="{{ __('capabilities.fields.confidence') }}">
                <flux:text size="sm">{{ __('capabilities.fields.confidence_help') }}</flux:text>
                <button type="button" x-show="value !== null" x-on:click="value = null" class="text-xs text-zinc-500 underline-offset-4 hover:underline">{{ __('capabilities.confidence_clear') }}</button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="visibility" :label="__('capabilities.fields.visibility')" :description="__('capabilities.fields.visibility_help')">
                    @foreach (\App\Models\Capability::VISIBILITIES as $visibilityKey)
                        <option value="{{ $visibilityKey }}">{{ __('capabilities.visibility.'.$visibilityKey) }}</option>
                    @endforeach
                </flux:select>
                @if ($memberships->isNotEmpty())
                    <flux:select wire:model="membershipId" :label="__('capabilities.fields.business')" :description="__('capabilities.fields.business_help')">
                        <option value="">{{ __('capabilities.fields.business_none') }}</option>
                        @foreach ($memberships as $membership)
                            <option value="{{ $membership->id }}">{{ $membership->business?->name }}</option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            <flux:textarea wire:model="description" rows="3" maxlength="2000" :label="__('capabilities.fields.description')" :description="__('capabilities.fields.description_help')" />
            <flux:checkbox wire:model="isPrimary" :label="__('capabilities.fields.primary')" />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button variant="ghost" wire:click="cancel">{{ __('ui.common.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save" data-capability-save>{{ __('capabilities.save') }}</flux:button>
            </div>
        </flux:card>
    @endif

    <div class="space-y-3" data-capabilities-list>
        @forelse ($capabilities as $capability)
            <article wire:key="capability-{{ $capability->uuid }}" class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 {{ $capability->status === 'archived' ? 'opacity-60' : '' }}" data-capability="{{ $capability->uuid }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1 space-y-1">
                        @if (! empty($areas[$capability->concept_id]))
                            <div class="text-xs text-zinc-500 [overflow-wrap:anywhere]" dir="auto">{{ $areas[$capability->concept_id] }}</div>
                        @endif
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold [overflow-wrap:anywhere]" dir="auto">{{ filled($capability->title) ? $capability->title : ($labels[$capability->concept_id] ?? '') }}</span>
                            @if ($capability->is_primary)
                                <flux:badge size="sm" color="amber" icon="star">{{ __('capabilities.primary') }}</flux:badge>
                            @endif
                        </div>
                        @if (filled($capability->title))
                            <div class="text-sm text-zinc-500 [overflow-wrap:anywhere]" dir="auto">{{ $labels[$capability->concept_id] ?? '' }}</div>
                        @endif
                        <div class="flex flex-wrap gap-1.5 pt-1 text-xs">
                            @if ($capability->level)
                                <flux:badge size="sm">{{ __('capabilities.levels.'.$capability->level) }}</flux:badge>
                            @endif
                            @if ($capability->years_experience !== null)
                                <flux:badge size="sm">{{ trans_choice('capabilities.years', $capability->years_experience, ['count' => $capability->years_experience]) }}</flux:badge>
                            @endif
                            @if ($capability->self_confidence_percent !== null)
                                <flux:badge size="sm" color="sky">{{ __('capabilities.confidence_badge', ['percent' => $capability->self_confidence_percent]) }}</flux:badge>
                            @endif
                            <flux:badge size="sm" color="zinc">{{ __('capabilities.visibility.'.$capability->visibility) }}</flux:badge>
                            @if ($capability->businessMembership?->business)
                                <flux:badge size="sm" color="violet" icon="briefcase">{{ $capability->businessMembership->business->name }}</flux:badge>
                            @endif
                        </div>
                        @if ($capability->description)
                            <p class="pt-1 text-sm text-zinc-600 [overflow-wrap:anywhere] dark:text-zinc-300" dir="auto">{{ $capability->description }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-1">
                        @if ($capability->status === 'archived')
                            <flux:button size="sm" variant="ghost" wire:click="restore('{{ $capability->uuid }}')">{{ __('capabilities.restore') }}</flux:button>
                        @else
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit('{{ $capability->uuid }}')" :aria-label="__('capabilities.edit')" data-capability-edit />
                            <flux:button size="sm" variant="ghost" icon="archive-box" wire:click="archive('{{ $capability->uuid }}')" wire:confirm="{{ __('capabilities.archive_confirm') }}" :aria-label="__('capabilities.archive')" />
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <x-app.empty-state :title="__('capabilities.empty')" :description="__('capabilities.empty_help')" />
        @endforelse
    </div>

    @if ($archivedCount > 0)
        <flux:checkbox wire:model.live="showArchived" :label="trans_choice('capabilities.show_archived', $archivedCount, ['count' => $archivedCount])" />
    @endif
</section>
