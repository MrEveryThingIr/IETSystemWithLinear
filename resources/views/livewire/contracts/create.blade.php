<section class="mx-auto max-w-5xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('contracts.create.title')" :description="__('contracts.create.help')" />

    @if ($proposal)
        <flux:callout>
            {{ __('contracts.create.proposal_source', ['title' => $proposal->title]) }}
        </flux:callout>
    @elseif ($relationship)
        <flux:callout>
            {{ __('contracts.create.relationship_source', ['title' => $relationship->title ?: 'REL-'.str_pad((string) $relationship->id, 6, '0', STR_PAD_LEFT)]) }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        @if (! $proposal)
            <flux:card class="space-y-4">
                <flux:input wire:model="title" :label="__('contracts.create.contract_title')" maxlength="180" />
                <flux:input wire:model="creatorRole" :label="__('contracts.create.creator_role')" maxlength="80" />
                <flux:textarea wire:model="partyLines" :label="__('contracts.create.parties')" rows="6" />
                <flux:text size="sm">{{ __('contracts.create.parties_help') }}</flux:text>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:textarea wire:model="summary" :label="__('contracts.create.summary')" rows="3" />
                <flux:textarea wire:model="terms" :label="__('contracts.create.terms')" rows="12" />
                <flux:textarea wire:model="notes" :label="__('contracts.create.notes')" rows="4" />
            </flux:card>
        @else
            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ $proposal->title }}</flux:heading>
                <flux:text>{{ __('contracts.boundary') }}</flux:text>
                @php($sourceVersion = $proposal->currentVersionRecord())
                @if ($sourceVersion)
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                            {{ __('proposals.show.current_version', ['version' => $sourceVersion->version]) }}
                        </div>
                        <div class="mt-2 whitespace-pre-wrap" dir="auto">{{ $sourceVersion->termsRevision->payload['terms'] ?? '' }}</div>
                    </div>
                @endif
            </flux:card>
        @endif

        <flux:card class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-app.calendar-datetime-input model="effectiveAt" :label="__('contracts.create.effective_at')" :timezone="$timezone" />
                <flux:input wire:model="timezone" :label="__('contracts.create.timezone')" maxlength="64" />
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">
                {{ __('contracts.create.submit') }}
            </flux:button>
        </div>
    </form>
</section>
