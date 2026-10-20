{{-- Answer form for an invitee (user or guest). Needs $meeting, $invitee, $votes. --}}
<div class="space-y-4" data-meeting-respond>
    <div class="space-y-2">
        @foreach ($meeting->slots->where('status', 'offered') as $slot)
            @php $answer = $votes[$slot->uuid] ?? null; @endphp
            <div wire:key="respond-{{ $slot->uuid }}" class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700" data-slot="{{ $slot->uuid }}">
                <div class="text-sm">
                    @include('livewire.planner.meetings.partials.when', ['slot' => $slot])
                    @if ($slot->suggested_by_invitee_id)
                        <span class="ms-1 text-xs text-zinc-500">· {{ __('planner.meetings.suggested_by', ['name' => $slot->suggestedBy?->name]) }}</span>
                    @endif
                </div>
                <div class="flex gap-1" role="radiogroup" aria-label="{{ __('planner.meetings.your_answer') }}">
                    @foreach (['yes' => 'border-emerald-600 bg-emerald-600 text-white', 'maybe' => 'border-amber-500 bg-amber-500 text-white', 'no' => 'border-rose-600 bg-rose-600 text-white'] as $choice => $chosenClass)
                        <button type="button" wire:click="vote('{{ $slot->uuid }}', '{{ $choice }}')" role="radio" aria-checked="{{ $answer === $choice ? 'true' : 'false' }}"
                            class="rounded-lg border px-3 py-1.5 text-sm font-medium transition {{ $answer === $choice ? $chosenClass : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' }}"
                            data-vote="{{ $choice }}">{{ __('planner.meetings.answers.'.$choice) }}</button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div>
        <button type="button" wire:click="$toggle('suggesting')" class="text-sm font-medium underline-offset-4 hover:underline" data-suggest-toggle>{{ __('planner.meetings.suggest_other') }}</button>
        @if ($suggesting)
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <flux:input type="date" wire:model="suggestDate" :label="__('planner.create.starts_on')" />
                <flux:input type="time" wire:model="suggestTime" :label="__('planner.create.start_time')" />
            </div>
            <p class="mt-1 text-xs text-zinc-500">{{ __('planner.meetings.times_in', ['timezone' => $viewerTimezone]) }}</p>
        @endif
        @error('suggestDate')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @error('suggestTime')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @error('suggest')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <flux:textarea wire:model="note" rows="2" maxlength="1000" :label="__('planner.meetings.note')" />

    <div class="flex flex-wrap justify-between gap-2">
        <flux:button type="button" wire:click="decline" wire:confirm="{{ __('planner.meetings.decline_confirm') }}" variant="ghost" data-meeting-decline>{{ __('planner.meetings.decline') }}</flux:button>
        <flux:button type="button" wire:click="sendResponse" variant="primary" data-meeting-send>{{ __('planner.meetings.send') }}</flux:button>
    </div>
    @error('meeting')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</div>
