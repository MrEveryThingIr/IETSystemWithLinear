<section class="mx-auto max-w-3xl space-y-6">
    <x-app.page-header :title="__('proposals.create.title')" :description="__('proposals.create.help')" />

    @if ($relationship)
        <flux:callout>
            {{ __('proposals.create.relationship_source', [
                'title' => $relationship->title ?: 'REL-'.str_pad((string) $relationship->id, 6, '0', STR_PAD_LEFT),
            ]) }}
        </flux:callout>
    @endif

    <flux:callout>{{ __('proposals.boundary') }}</flux:callout>

    <flux:card>
        <form wire:submit="save" class="space-y-5">
            <flux:input
                wire:model="title"
                :label="__('proposals.create.proposal_title')"
                maxlength="180"
            />

            <div class="space-y-2">
                <div class="text-sm font-medium">{{ __('proposals.create.parties') }}</div>
                <div class="rounded-xl bg-zinc-50 p-3 text-sm dark:bg-zinc-950" dir="auto">
                    {{ $partyUsernames }}
                </div>
                <p class="text-xs text-zinc-500">{{ __('deals.proposal_parties_help') }}</p>
            </div>

            <flux:textarea
                wire:model="summary"
                :label="__('proposals.create.summary')"
                rows="3"
            />

            <flux:textarea
                wire:model="terms"
                :label="__('proposals.create.terms')"
                rows="12"
            />

            <flux:textarea
                wire:model="notes"
                :label="__('proposals.create.notes')"
                rows="4"
            />

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button :href="route('relationships.show', $relationship)" variant="ghost">
                    {{ __('ui.common.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('proposals.create.submit') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</section>
