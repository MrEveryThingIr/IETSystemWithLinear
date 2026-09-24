<section class="mx-auto max-w-3xl space-y-6">
    <x-app.page-header :title="__('relationships.create.title')" :description="__('relationships.create.help')" />

    @if ($intentUuid !== '')
        <flux:callout>
            <div class="space-y-1">
                <div class="font-medium">{{ __('relationships.create.origin') }}</div>
                <div dir="auto">{{ __('relationships.create.origin_help', ['title' => $originIntentTitle]) }}</div>
            </div>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('relationships.create.purpose') }}</flux:heading>
                @if ($purposeLabel !== '')
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <flux:badge>{{ $purposeLabel }}</flux:badge>
                        @unless ($purposeLocked)
                            <flux:button type="button" wire:click="clearPurpose" variant="ghost" size="sm">
                                {{ __('relationships.create.change_purpose') }}
                            </flux:button>
                        @endunless
                    </div>
                @elseif (! $purposeLocked)
                    <div class="mt-4 space-y-3">
                        <flux:input
                            wire:model.live.debounce.250ms="purposeSearch"
                            :label="__('relationships.create.purpose_search')"
                            :placeholder="__('relationships.create.purpose_search_placeholder')"
                        />
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($purposeOptions as $concept)
                                <button
                                    type="button"
                                    wire:key="purpose-{{ $concept->uuid }}"
                                    wire:click="selectPurpose({{ $concept->id }})"
                                    class="rounded-xl border border-zinc-200 px-4 py-3 text-start text-sm hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-900"
                                >
                                    <span class="font-medium" dir="auto">{{ $concept->displayLabel() }}</span>
                                    <span class="mt-1 block text-xs text-zinc-500">{{ $concept->slug }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @error('purposeConceptId') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </flux:card>

        <flux:card class="space-y-5">
            @if ($participantLocked)
                <div>
                    <div class="text-sm font-medium">{{ __('relationships.create.participant') }}</div>
                    <div class="mt-2 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        {{ $originParticipantLabel }}
                    </div>
                </div>
            @else
                <div>
                    <flux:input wire:model="participantUsername" :label="__('relationships.create.participant')" />
                    <flux:text class="mt-1 text-xs">{{ __('relationships.create.participant_help') }}</flux:text>
                    @error('participantUsername') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="creatorRole" :label="__('relationships.create.creator_role')" maxlength="80" />
                <flux:input wire:model="participantRole" :label="__('relationships.create.participant_role')" maxlength="80" />
            </div>

            <div>
                <flux:input wire:model="title" :label="__('relationships.create.relationship_title')" maxlength="180" />
                <flux:text class="mt-1 text-xs">{{ __('relationships.create.relationship_title_help') }}</flux:text>
            </div>
        </flux:card>

        <flux:callout variant="warning">{{ __('relationships.create.boundary') }}</flux:callout>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button :href="route('relationships.index')" variant="ghost" class="w-full sm:w-auto">
                {{ __('studio.cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                {{ __('relationships.create.submit') }}
            </flux:button>
        </div>
    </form>
</section>
