@php
    $side = in_array($intention, ['need', 'person'], true) ? 'need' : 'label';
    $termLabel = fn (string $block): string => (string) __('composer.terms.'.$block.'.'.($side === 'need' && in_array($block, ['price', 'place'], true) ? 'need' : 'label'));
    $input = 'block w-full min-w-0';
@endphp
@foreach(\App\Support\Market\IntentTerms::BLOCKS as $block)
    @continue(! in_array($block, $terms, true))
    @if($block === 'price')
        <x-market.term block="price" :label="$termLabel('price')" :locked="$catalog !== null">
            @if($legacyPrice)<p class="rounded-lg border p-3 text-sm">{{ __('composer.legacy_price') }}</p><flux:checkbox wire:model.live="replaceLegacyPrice" :label="__('composer.replace_price')" />@endif
            <div @if($legacyPrice && ! $replaceLegacyPrice) inert class="space-y-3 opacity-50" @else class="space-y-3" @endif>
                <flux:select wire:model.live="priceMode" :disabled="$catalog !== null" :label="__('composer.price_type')">@foreach(['free', 'fixed', 'per_unit', 'range', 'ask', 'exchange'] as $mode)<option value="{{ $mode }}">{{ __('composer.prices.'.$mode) }}</option>@endforeach</flux:select>
                @if(in_array($priceMode, ['fixed', 'per_unit', 'range'], true))
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input wire:model="price" :label="__('composer.amount_toman')" inputmode="decimal" />
                        @if($priceMode === 'range')<flux:input wire:model="maximumPrice" :label="__('composer.maximum_toman')" inputmode="decimal" />@endif
                        @if($priceMode === 'per_unit')<flux:select wire:model="core.cash_basis" :label="__('composer.per')">@foreach(\App\Support\Market\RateBasis::cases() as $basis)<option value="{{ $basis->value }}">{{ __('intents.rate_basis.'.$basis->value) }}</option>@endforeach</flux:select>@endif
                    </div>
                @endif
                <flux:checkbox wire:model="core.negotiable" :label="__('intents.fields.negotiable')" />
            </div>
        </x-market.term>
    @elseif($block === 'place')
        <x-market.term block="place" :label="$termLabel('place')" :locked="$catalog !== null">
            <flux:input wire:model="core.location_text" :disabled="$catalog !== null" :aria-label="$termLabel('place')" dir="auto" />
        </x-market.term>
    @elseif($block === 'when')
        <x-market.term block="when" :label="$termLabel('when')">
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model.live="core.schedule_kind" :label="__('ui.profile.intents.schedule_kind')">@foreach(\App\ProfileIntentScheduleKind::cases() as $schedule)<option value="{{ $schedule->value }}">{{ __('ui.profile.intents.schedules.'.$schedule->value) }}</option>@endforeach</flux:select>
                <div></div>
                <x-app.calendar-date-input model="core.starts_on" :label="__('composer.starts_on')" />
                <x-app.calendar-date-input model="core.ends_on" :label="__('composer.ends_on')" />
                <flux:input wire:model="core.time_window_start" type="time" :label="__('ui.profile.intents.time_from')" />
                <flux:input wire:model="core.time_window_end" type="time" :label="__('ui.profile.intents.time_to')" />
                @if(in_array($core['schedule_kind'] ?? '', ['daily', 'weekly', 'monthly'], true))<flux:input wire:model="core.recurrence_interval" :label="__('ui.profile.intents.repeat_every')" inputmode="numeric" />@endif
                @if(($core['schedule_kind'] ?? '') === 'monthly')<flux:input wire:model="core.recurrence_day_of_month" :label="__('ui.profile.intents.day_of_month')" inputmode="numeric" />@endif
            </div>
            @if(($core['schedule_kind'] ?? '') === 'weekly')<fieldset><legend class="mb-2 text-sm">{{ __('ui.profile.intents.weekdays') }}</legend><div class="flex flex-wrap gap-3">@foreach(range(1, 7) as $day)<flux:checkbox wire:model="core.recurrence_weekdays" value="{{ $day }}" :label="__('ui.profile.intents.weekday_names.'.$day)" />@endforeach</div></fieldset>@endif
            <details><summary class="cursor-pointer text-sm text-zinc-600 dark:text-zinc-400">{{ __('ui.profile.intents.timezone') }}: {{ $core['timezone'] ?? '' }}</summary><div class="mt-2"><flux:input wire:model="core.timezone" :aria-label="__('ui.profile.intents.timezone')" /></div></details>
        </x-market.term>
    @elseif($block === 'route')
        <x-market.term block="route" :label="$termLabel('route')">
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="core.origin_text" :label="__('ui.profile.intents.origin')" dir="auto" />
                <flux:input wire:model="core.destination_text" :label="__('ui.profile.intents.destination')" dir="auto" />
            </div>
            <flux:checkbox wire:model.live="core.round_trip" :label="__('ui.profile.intents.round_trip')" />
            @if($core['round_trip'] ?? false)<flux:input wire:model="core.return_after_days" :label="__('ui.profile.intents.return_after_days')" inputmode="numeric" />@endif
        </x-market.term>
    @elseif($block === 'quantity')
        <x-market.term block="quantity" :label="$termLabel('quantity')" :locked="$catalog !== null">
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="core.quantity" :disabled="$catalog !== null" :label="__('composer.quantity')" inputmode="decimal" />
                <flux:input wire:model="core.unit" :disabled="$catalog !== null" :label="__('composer.unit')" />
            </div>
        </x-market.term>
    @elseif($block === 'importance')
        <x-market.term block="importance" :label="$termLabel('importance')">
            <flux:input wire:model="core.importance_percent" :aria-label="__('ui.profile.intents.importance')" :label="__('ui.profile.intents.importance')" inputmode="numeric" />
        </x-market.term>
    @elseif($block === 'category')
        <x-market.term block="category" :label="$termLabel('category')">
            @if($intent === null)
                <livewire:categories.picker wire:model.live="conceptId" :owner="$publisher->key()" :kinds="\App\Support\Categories\CategoryKind::values()" :allow-create="true" :key="'composer-picker-'.$publisher->key()" />
                <flux:input wire:model="conceptLabel" :label="__('intents.create.type_instead')" />
            @else
                <p>{{ $conceptLabel }}</p>
            @endif
        </x-market.term>
    @elseif($block === 'media')
        <x-market.term block="media" :label="$termLabel('media')">
            @include('livewire.intents.composer.media')
        </x-market.term>
    @endif
@endforeach
