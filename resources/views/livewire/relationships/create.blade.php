<section class="mx-auto max-w-2xl space-y-5">
    <x-app.page-header :title="__('deals.start.title')" :description="__('deals.start.help')" />

    @if ($blueprintVersion)
        <flux:callout>
            <div class="font-medium">{{ __('journeys.guided_by', ['name' => $blueprintVersion->blueprint->name, 'version' => $blueprintVersion->version]) }}</div>
        </flux:callout>
    @endif

    <x-deals.origin :card="$originCard" />

    <form wire:submit="save" class="space-y-4">
        <flux:card class="space-y-4">
            <div class="text-sm">
                <span class="text-zinc-500">{{ __('relationships.create.participant') }}:</span>
                <span class="font-medium" dir="auto">{{ $originParticipantLabel }}</span>
            </div>

            @if ($parties->count() > 1)
                <flux:select wire:model="actingAs" :label="__('deals.start.speaking_as')">
                    @foreach ($parties as $row)
                        <option value="{{ $row['party']->kind === 'actor' ? '' : $row['party']->key() }}">{{ $row['label'] }}</option>
                    @endforeach
                </flux:select>
            @endif

            <flux:textarea wire:model="message" rows="4" maxlength="4000" :label="__('deals.start.message')" :placeholder="__('deals.start.message_placeholder')" />
            @error('message') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('purposeConceptId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('participantUsername') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </flux:card>

        <p class="text-xs text-zinc-500">{{ __('deals.start.note') }}</p>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button :href="route('intents.index')" variant="ghost" class="w-full sm:w-auto">{{ __('studio.cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">{{ __('deals.start.send') }}</flux:button>
        </div>
    </form>
</section>
