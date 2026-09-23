<section class="mx-auto max-w-4xl space-y-6">
    <x-app.page-header :title="__('intents.create.title')" :description="__('intents.create.help')">
        <x-slot:actions>
            <flux:button :href="route('intents.index')" variant="ghost">{{ __('intents.directory.title') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <div class="flex items-center gap-2 overflow-x-auto pb-1">
        @for ($number = 1; $number <= 6; $number++)
            <div class="flex min-w-10 items-center justify-center rounded-full border px-3 py-2 text-sm font-semibold {{ $step === $number ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : ($step > $number ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200' : 'border-zinc-200 text-zinc-500 dark:border-zinc-800') }}">
                {{ $number }}
            </div>
            @if ($number < 6)<div class="h-px min-w-5 flex-1 bg-zinc-200 dark:bg-zinc-800"></div>@endif
        @endfor
    </div>

    <flux:card class="space-y-6">
        @if ($step === 1)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('intents.create.step1') }}</flux:heading>
                    <flux:text>{{ __('intents.create.step1_help') }}</flux:text>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['need', 'offer'] as $option)
                        <button type="button" wire:click="$set('kind', '{{ $option }}')" class="rounded-2xl border p-5 text-start {{ $kind === $option ? 'border-zinc-900 ring-2 ring-zinc-900/10 dark:border-white' : 'border-zinc-200 dark:border-zinc-800' }}">
                            <div class="text-lg font-semibold">{{ __('intents.kinds.'.$option) }}</div>
                            <div class="mt-1 text-sm text-zinc-500">{{ __('intents.kind_help.'.$option) }}</div>
                        </button>
                    @endforeach
                </div>
                <div>
                    <div class="mb-2 text-sm font-medium">{{ __('intents.fields.subject_kind') }}</div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ($subjectKinds as $option)
                            <button type="button" wire:click="$set('subjectKind', '{{ $option->value }}')" class="rounded-xl border px-4 py-3 text-start text-sm {{ $subjectKind === $option->value ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-800' }}">
                                {{ __('intents.subjects.'.$option->value) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @elseif ($step === 2)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('intents.create.step2') }}</flux:heading>
                    <flux:text>{{ __('intents.create.step2_help') }}</flux:text>
                </div>
                <flux:input wire:model="conceptLabel" :label="__('intents.fields.subject')" :placeholder="__('intents.fields.subject_placeholder')" maxlength="120" />
                <div>
                    <div class="mb-2 text-sm font-medium">{{ __('intents.fields.arrangement') }}</div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($arrangementKinds as $option)
                            <button type="button" wire:click="$set('arrangementKind', '{{ $option->value }}')" class="rounded-xl border px-4 py-3 text-start {{ $arrangementKind === $option->value ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-800' }}">
                                <div class="font-medium">{{ __('intents.arrangements.'.$kind.'.'.$option->value) }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @elseif ($step === 3)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('intents.create.step3') }}</flux:heading>
                    <flux:text>{{ __('intents.create.step3_help') }}</flux:text>
                </div>
                <flux:input wire:model="locationText" :label="__('intents.fields.location')" maxlength="255" />
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="cashMin" type="number" min="0" step="0.01" :label="__('intents.fields.cash_min')" />
                    <flux:input wire:model="cashMax" type="number" min="0" step="0.01" :label="__('intents.fields.cash_max')" />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="currencyCode" :label="__('intents.fields.currency')" maxlength="3" placeholder="IRR, CAD, USD…" />
                    <div>
                        <label class="mb-2 block text-sm font-medium">{{ __('intents.fields.cash_basis') }}</label>
                        <select wire:model="cashBasis" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach (['total','hour','day','week','month','year'] as $basis)
                                <option value="{{ $basis }}">{{ __('intents.cash_basis.'.$basis) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <flux:callout>{{ __('intents.create.cash_note') }}</flux:callout>
            </div>
        @elseif ($step === 4)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('intents.create.step4') }}</flux:heading>
                    <flux:text>{{ __('intents.create.step4_help') }}</flux:text>
                </div>
                <div class="space-y-3">
                    @foreach ($exchangePreferences as $option)
                        <label class="flex cursor-pointer gap-3 rounded-xl border p-4 dark:border-zinc-800">
                            <input type="radio" wire:model="exchangePreference" value="{{ $option->value }}" class="mt-1">
                            <span>
                                <span class="block font-medium">{{ __('intents.exchange.'.$option->value.'.title') }}</span>
                                <span class="mt-1 block text-sm text-zinc-500">{{ __('intents.exchange.'.$option->value.'.help') }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <flux:textarea wire:model="exchangeNotes" :label="__('intents.fields.exchange_notes')" :placeholder="__('intents.fields.exchange_notes_placeholder')" rows="4" maxlength="2000" />
                <flux:callout variant="warning">{{ __('intents.exchange_nonbinding') }}</flux:callout>
            </div>
        @elseif ($step === 5)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('intents.create.step5') }}</flux:heading>
                    <flux:text>{{ __('intents.create.step5_help') }}</flux:text>
                </div>
                <flux:input wire:model="title" :label="__('intents.fields.title')" maxlength="180" />
                <flux:textarea wire:model="description" :label="__('intents.fields.description')" rows="5" maxlength="3000" />
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium">{{ __('intents.fields.timing') }}</label>
                        <select wire:model="scheduleKind" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($scheduleKinds as $option)
                                <option value="{{ $option->value }}">{{ __('intents.timing.'.$option->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium">{{ __('intents.fields.visibility') }}</label>
                        <select wire:model="visibility" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="private">{{ __('intents.visibility.private') }}</option>
                            <option value="authenticated">{{ __('intents.visibility.authenticated') }}</option>
                            <option value="public">{{ __('intents.visibility.public') }}</option>
                        </select>
                    </div>
                </div>
            </div>
        @else
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('intents.create.step6') }}</flux:heading>
                    <flux:text>{{ __('intents.create.step6_help') }}</flux:text>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950"><span class="text-xs text-zinc-500">{{ __('intents.fields.kind') }}</span><div class="font-medium">{{ __('intents.kinds.'.$kind) }}</div></div>
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950"><span class="text-xs text-zinc-500">{{ __('intents.fields.subject_kind') }}</span><div class="font-medium">{{ __('intents.subjects.'.$subjectKind) }}</div></div>
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950"><span class="text-xs text-zinc-500">{{ __('intents.fields.subject') }}</span><div class="font-medium" dir="auto">{{ $conceptLabel }}</div></div>
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950"><span class="text-xs text-zinc-500">{{ __('intents.fields.arrangement') }}</span><div class="font-medium">{{ __('intents.arrangements.'.$kind.'.'.$arrangementKind) }}</div></div>
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950 sm:col-span-2"><span class="text-xs text-zinc-500">{{ __('intents.fields.exchange_preference') }}</span><div class="font-medium">{{ __('intents.exchange.'.$exchangePreference.'.title') }}</div></div>
                </div>
                <flux:callout>{{ __('intents.create.review_note') }}</flux:callout>
            </div>
        @endif

        @error('*')<flux:callout variant="danger">{{ $message }}</flux:callout>@enderror

        <div class="flex flex-col-reverse gap-2 border-t border-zinc-200 pt-5 sm:flex-row sm:justify-between dark:border-zinc-800">
            <div>
                @if ($step > 1)<flux:button wire:click="previous" variant="ghost">{{ __('intents.create.back') }}</flux:button>@endif
            </div>
            <div>
                @if ($step < 6)
                    <flux:button wire:click="next" variant="primary">{{ __('intents.create.next') }}</flux:button>
                @else
                    <flux:button wire:click="save" variant="primary" wire:loading.attr="disabled">{{ __('intents.create.save') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:card>
</section>
