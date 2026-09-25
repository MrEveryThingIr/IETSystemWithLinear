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

            <div>
                <flux:textarea
                    wire:model="partyUsernames"
                    :label="__('proposals.create.parties')"
                    rows="3"
                />
                <p class="mt-1 text-xs text-zinc-500">{{ __('proposals.create.parties_help') }}</p>
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
                <flux:button :href="route('proposals.index')" variant="ghost">
                    {{ __('ui.common.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('proposals.create.submit') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</section>
