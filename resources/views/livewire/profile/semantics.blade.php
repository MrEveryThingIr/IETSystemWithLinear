<section class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
    <div>
        <h2 class="text-lg font-semibold">{{ __('ui.profile.semantics.title') }}</h2>
        <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile.semantics.help') }}</p>
    </div>

    <form wire:submit="add" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_13rem_11rem_auto] md:items-end">
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
            <select id="semantic-predicate" wire:model="predicate" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
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
        <flux:button type="submit" variant="primary">{{ __('ui.profile.semantics.add') }}</flux:button>
    </form>

    @if ($assertions->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($assertions as $assertion)
                <article class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800" wire:key="profile-semantic-{{ $assertion->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium" dir="auto">{{ $assertion->concept->displayLabel() }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('ui.profile.semantics.types.'.$assertion->predicate->value) }}</p>
                        </div>
                        <flux:button wire:click="remove({{ $assertion->id }})" wire:confirm="{{ __('ui.profile.semantics.remove_confirm') }}" size="xs" variant="ghost">
                            {{ __('ui.common.remove') }}
                        </flux:button>
                    </div>
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
