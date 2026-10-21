<section class="mx-auto max-w-4xl space-y-6" data-meeting-show>
    <x-app.flash-message />
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('planner.index') }}" wire:navigate class="text-sm text-zinc-500 hover:underline">← {{ __('planner.title') }}</a>
            <h1 class="mt-1 text-2xl font-semibold" dir="auto">{{ $meeting->title }}</h1>
            <p class="mt-1 text-sm text-zinc-500">
                {{ __('planner.meetings.organized_by', ['name' => $meeting->organizer?->profile?->display_name ?: $meeting->organizer?->user?->username]) }}
                · {{ trans_choice('planner.wizard.minutes', $meeting->duration_minutes, ['count' => $meeting->duration_minutes]) }}
                @if ($meeting->location) · <span dir="auto">{{ $meeting->location }}</span>@endif
            </p>
        </div>
        <flux:badge :color="match ($meeting->status) { 'agreed' => 'green', 'cancelled' => 'zinc', default => 'violet' }" data-meeting-status="{{ $meeting->status }}">{{ __('planner.meetings.status.'.$meeting->status) }}</flux:badge>
    </div>

    @if ($meeting->description)
        <p class="whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300" dir="auto">{{ $meeting->description }}</p>
    @endif

    @if ($meeting->status === 'agreed' && $meeting->finalSlot)
        <flux:callout variant="success" icon="check-circle" data-meeting-agreed>
            <flux:callout.heading>{{ __('planner.meetings.agreed_on') }}</flux:callout.heading>
            <flux:callout.text>
                @include('livewire.planner.meetings.partials.when', ['slot' => $meeting->finalSlot])
                <div class="mt-2 flex flex-wrap gap-3">
                    @if ($myPlan)
                        <a href="{{ route('planner.show', $myPlan) }}" wire:navigate class="font-medium underline">{{ __('planner.meetings.open_plan') }}</a>
                    @endif
                    <a href="{{ route('planner.meetings.ics', $meeting) }}" class="font-medium underline">{{ __('planner.meetings.add_to_calendar') }}</a>
                </div>
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($isOrganizer)
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('planner.meetings.answers_title') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[32rem] text-sm" data-meeting-grid>
                    <thead>
                        <tr class="text-start text-xs text-zinc-500">
                            <th class="py-2 pe-3 text-start font-medium">{{ __('planner.meetings.option') }}</th>
                            @foreach ($meeting->invitees as $person)
                                <th class="px-2 py-2 text-center font-medium" dir="auto">{{ $person->name }}@if ($person->status === 'declined')<span class="block text-[0.65rem] text-rose-600">{{ __('planner.meetings.declined_short') }}</span>@endif</th>
                            @endforeach
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($meeting->slots as $slot)
                            @php $yes = $slot->votes->where('answer', 'yes')->count(); @endphp
                            <tr wire:key="grid-{{ $slot->uuid }}" class="border-t border-zinc-100 dark:border-zinc-800 {{ $slot->status !== 'offered' && $meeting->final_slot_id !== $slot->id ? 'opacity-40' : '' }}">
                                <td class="py-2 pe-3">
                                    @include('livewire.planner.meetings.partials.when', ['slot' => $slot])
                                    @if ($slot->suggested_by_invitee_id)<span class="block text-xs text-violet-700 dark:text-violet-300">{{ __('planner.meetings.suggested_by', ['name' => $slot->suggestedBy?->name]) }}</span>@endif
                                </td>
                                @foreach ($meeting->invitees as $person)
                                    @php $answer = $slot->votes->firstWhere('meeting_invitee_id', $person->id)?->answer; @endphp
                                    <td class="px-2 py-2 text-center">
                                        @if ($answer)
                                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ ['yes' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300', 'maybe' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300', 'no' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'][$answer] }}">{{ __('planner.meetings.answers.'.$answer) }}</span>
                                        @else
                                            <span class="text-zinc-300">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-2 text-end">
                                    @if ($meeting->status === 'open')
                                        <flux:button wire:click="choose('{{ $slot->uuid }}')" wire:confirm="{{ __('planner.meetings.choose_confirm') }}" size="xs" :variant="$yes > 0 ? 'primary' : 'ghost'" data-meeting-choose="{{ $slot->uuid }}">{{ __('planner.meetings.choose') }}</flux:button>
                                    @elseif ($meeting->final_slot_id === $slot->id)
                                        <flux:badge size="sm" color="green">✓</flux:badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @foreach ($meeting->invitees->whereNotNull('note') as $person)
                <p class="text-sm"><span class="font-medium" dir="auto">{{ $person->name }}:</span> <span dir="auto">{{ $person->note }}</span></p>
            @endforeach
        </flux:card>

        @if ($links->isNotEmpty())
            <flux:card class="space-y-3" data-meeting-links>
                <flux:heading size="lg">{{ __('planner.meetings.guest_links') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ __('planner.meetings.guest_links_help') }}</p>
                @foreach ($meeting->invitees->filter->isGuest() as $guest)
                    @if ($links->has($guest->uuid))
                        <div class="space-y-1" x-data="{ copied: false }">
                            <div class="text-sm font-medium" dir="auto">{{ $guest->name }} @if ($guest->email)<span class="text-xs font-normal text-zinc-500">{{ $guest->email }}</span>@endif @if ($guest->last_viewed_at)<span class="text-xs font-normal text-emerald-700">· {{ __('planner.meetings.opened') }}</span>@endif</div>
                            <div class="flex gap-2">
                                <input type="text" readonly value="{{ $links[$guest->uuid] }}" class="min-w-0 flex-1 rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-xs dark:border-zinc-700 dark:bg-zinc-900" dir="ltr" data-guest-link>
                                <flux:button size="sm" x-on:click="navigator.clipboard?.writeText({{ \Illuminate\Support\Js::from($links[$guest->uuid]) }}); copied = true; setTimeout(() => copied = false, 1500)">
                                    <span x-show="! copied">{{ __('planner.meetings.copy') }}</span><span x-show="copied" x-cloak>✓</span>
                                </flux:button>
                            </div>
                        </div>
                    @endif
                @endforeach
            </flux:card>
        @endif

        @if ($meeting->status === 'open')
            <div class="text-end">
                <flux:button wire:click="cancelMeeting" wire:confirm="{{ __('planner.meetings.cancel_confirm') }}" variant="ghost" size="sm" data-meeting-cancel>{{ __('planner.meetings.cancel') }}</flux:button>
            </div>
        @endif
    @elseif ($invitee && $meeting->status === 'open')
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('planner.meetings.which_times') }}</flux:heading>
            @if ($invitee->status === 'declined')
                <flux:callout>{{ __('planner.meetings.you_declined') }}</flux:callout>
            @endif
            @include('livewire.planner.meetings.partials.respond')
        </flux:card>
    @elseif ($meeting->status === 'cancelled')
        <flux:callout>{{ __('planner.meetings.was_cancelled') }}</flux:callout>
    @endif
</section>
