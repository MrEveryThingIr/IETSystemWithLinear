<section class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold">{{ __('ui.profile.semantics.title') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile.semantics.help') }}</p>
        </div>
        @unless ($composerOpen)
            <flux:button wire:click="openComposer" size="sm" variant="ghost" icon="plus" class="w-full shrink-0 sm:w-auto">
                {{ __('ui.profile.semantics.add') }}
            </flux:button>
        @endunless
    </div>

    @if ($composerOpen)
    <form wire:submit="add" class="grid min-w-0 gap-3 rounded-xl bg-zinc-50 p-4 lg:grid-cols-[minmax(0,1fr)_13rem_11rem_auto] lg:items-end dark:bg-zinc-950/50">
        <div class="relative space-y-2">
            <flux:input wire:model.live.debounce.250ms="conceptLabel" :label="__('ui.profile.semantics.concept')" maxlength="120" />
            @if ($conceptSuggestions !== [])
                <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                    @foreach ($conceptSuggestions as $suggestion)
                        <button
                            type="button"
                            wire:click="selectConceptSuggestion(@js($suggestion))"
                            class="block w-full px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            dir="auto"
                        >
                            {{ $suggestion }}
                        </button>
                    @endforeach
                </div>
            @elseif (mb_strlen(trim($conceptLabel)) >= 2)
                <p class="text-xs text-zinc-500">{{ __('ui.profile.intents.custom_concept_hint') }}</p>
            @endif
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="semantic-predicate">{{ __('ui.profile.semantics.relationship') }}</label>
            <select id="semantic-predicate" wire:model.live="predicate" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                <option value="has_skill">{{ __('ui.profile.semantics.types.has_skill') }}</option>
                <option value="interested_in">{{ __('ui.profile.semantics.types.interested_in') }}</option>
                <option value="wants_to_learn">{{ __('ui.profile.semantics.types.wants_to_learn') }}</option>
            </select>
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="semantic-visibility">{{ __('ui.profile.item_visibility') }}</label>
            <select id="semantic-visibility" wire:model="semanticVisibility" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                <option value="inherited">{{ __('ui.profile.item_visibility_options.inherited') }}</option>
                <option value="private">{{ __('ui.profile.item_visibility_options.private') }}</option>
            </select>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
            <flux:button type="submit" variant="primary" class="w-full">{{ __('ui.profile.semantics.add') }}</flux:button>
            <flux:button type="button" wire:click="cancelComposer" variant="ghost" class="w-full">{{ __('ui.common.cancel') }}</flux:button>
        </div>

        @if ($predicate === 'has_skill')
            <div class="space-y-3 rounded-xl border border-zinc-200 p-4 lg:col-span-4 dark:border-zinc-800">
                <div>
                    <label class="text-sm font-medium" for="skill-proficiency">{{ __('ui.profile.semantics.proficiency') }}</label>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('ui.profile.semantics.proficiency_help') }}</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input
                        id="skill-proficiency"
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                        wire:model.live="proficiencyPercent"
                        class="w-full"
                    >
                    <div class="w-full sm:w-28">
                        <flux:input wire:model.live="proficiencyPercent" type="number" min="0" max="100" suffix="%" />
                    </div>
                </div>
            </div>
        @endif
    </form>
    @endif

    @if ($assertions->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($assertions as $assertion)
                <article class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" wire:key="profile-semantic-{{ $assertion->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium" dir="auto">{{ $assertion->concept->displayLabel() }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('ui.profile.semantics.types.'.$assertion->predicate->value) }}</p>
                        </div>
                        <flux:button wire:click="remove({{ $assertion->id }})" wire:confirm="{{ __('ui.profile.semantics.remove_confirm') }}" size="xs" variant="ghost">
                            {{ __('ui.common.remove') }}
                        </flux:button>
                    </div>
                    @if ($assertion->predicate->value === 'has_skill')
                        @php
                            $skillPercent = AppSupportProfileProfileScale::percentFromWeight($assertion->weight);
                        @endphp
                        <div class="mt-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/50">
                            @if ($editingProficiencyAssertionId === $assertion->id)
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                        <input
                                            type="range"
                                            min="0"
                                            max="100"
                                            step="1"
                                            wire:model.live="editingProficiencyPercent"
                                            class="w-full"
                                            aria-label="{{ __('ui.profile.semantics.proficiency') }}"
                                        >
                                        <div class="w-full sm:w-28">
                                            <flux:input wire:model.live="editingProficiencyPercent" type="number" min="0" max="100" suffix="%" />
                                        </div>
                                    </div>
                                    @error('editingProficiencyPercent')
                                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <flux:button wire:click="saveProficiency" size="xs" variant="primary" class="w-full sm:w-auto">{{ __('ui.common.save') }}</flux:button>
                                        <flux:button wire:click="cancelProficiencyEditor" size="xs" variant="ghost" class="w-full sm:w-auto">{{ __('ui.common.cancel') }}</flux:button>
                                    </div>
                                </div>
                            @else
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="text-xs font-medium text-zinc-500">{{ __('ui.profile.semantics.proficiency') }}</p>
                                        @if ($skillPercent !== null)
                                            <p class="mt-1 text-sm font-semibold">
                                                {{ $skillPercent }}%
                                                <span class="font-normal text-zinc-500">· {{ __('ui.profile.semantics.proficiency_levels.'.AppSupportProfileProfileScale::skillLevelKey($skillPercent)) }}</span>
                                            </p>
                                        @else
                                            <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile.semantics.proficiency_unrated') }}</p>
                                        @endif
                                    </div>
                                    <flux:button wire:click="openProficiencyEditor({{ $assertion->id }})" size="xs" variant="ghost">{{ __('ui.common.edit') }}</flux:button>
                                </div>
                                @if ($skillPercent !== null)
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-zinc-700 dark:bg-zinc-300" style="width: {{ $skillPercent }}%"></div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif

                    <div class="mt-3">
                        <select
                            wire:change="setVisibility({{ $assertion->id }}, $event.target.value)"
                            class="w-full rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-900"
                            aria-label="{{ __('ui.profile.item_visibility') }}"
                        >
                            <option value="inherited" @selected($assertion->visibility->value === 'inherited')>{{ __('ui.profile.item_visibility_options.inherited') }}</option>
                            <option value="private" @selected($assertion->visibility->value === 'private')>{{ __('ui.profile.item_visibility_options.private') }}</option>
                        </select>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
