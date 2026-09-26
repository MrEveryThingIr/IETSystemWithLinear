<section class="mx-auto max-w-4xl space-y-6">
    <x-app.page-header :title="__('commitments.create.title')" :description="__('commitments.create.help')" />

    <flux:callout>
        {{ __('commitments.create.exact_version', ['version' => $version->version]) }}
    </flux:callout>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:input wire:model="title" :label="__('commitments.create.title_field')" maxlength="180" />
            <flux:textarea wire:model="description" :label="__('commitments.create.description')" rows="4" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="kind" :label="__('commitments.create.kind')">
                    @foreach ($kinds as $kindOption)
                        <flux:select.option :value="$kindOption->value">
                            {{ __('commitments.kind.'.$kindOption->value) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="unit" :label="__('commitments.create.unit')" maxlength="40" />
                <flux:input wire:model="quantity" :label="__('commitments.create.quantity')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="obligorUsername" :label="__('commitments.create.obligor')">
                    @foreach ($parties as $party)
                        <flux:select.option :value="$party->actor->user?->username">
                            {{ $party->actor->user?->username }} · {{ $party->role }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="beneficiaryUsername" :label="__('commitments.create.beneficiary')">
                    @foreach ($parties as $party)
                        <flux:select.option :value="$party->actor->user?->username">
                            {{ $party->actor->user?->username }} · {{ $party->role }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-app.calendar-datetime-input model="dueStart" :label="__('commitments.create.due_start')" />
                <x-app.calendar-datetime-input model="dueEnd" :label="__('commitments.create.due_end')" />
            </div>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">
                {{ __('commitments.create.submit') }}
            </flux:button>
        </div>
    </form>

    <flux:callout>{{ __('commitments.create.boundary') }}</flux:callout>
</section>
