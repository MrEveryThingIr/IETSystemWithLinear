<section class="mx-auto max-w-2xl space-y-6 py-6" data-meeting-guest
    x-data x-init="$wire.setViewerTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)">
    <x-app.flash-message />
    <div>
        <p class="text-sm text-zinc-500">{{ __('planner.meetings.guest_intro', ['name' => $meeting->organizer?->profile?->display_name ?: $meeting->organizer?->user?->username, 'guest' => $invitee->name]) }}</p>
        <h1 class="mt-1 text-2xl font-semibold" dir="auto">{{ $meeting->title }}</h1>
        <p class="mt-1 text-sm text-zinc-500">
            {{ trans_choice('planner.wizard.minutes', $meeting->duration_minutes, ['count' => $meeting->duration_minutes]) }}
            @if ($meeting->location) · <span dir="auto">{{ $meeting->location }}</span>@endif
        </p>
        @if ($meeting->description)
            <p class="mt-3 whitespace-pre-wrap text-sm" dir="auto">{{ $meeting->description }}</p>
        @endif
    </div>

    @if ($meeting->status === 'agreed' && $meeting->finalSlot)
        <flux:callout variant="success" icon="check-circle" data-meeting-agreed>
            <flux:callout.heading>{{ __('planner.meetings.agreed_on') }}</flux:callout.heading>
            <flux:callout.text>
                @include('livewire.planner.meetings.partials.when', ['slot' => $meeting->finalSlot])
                <div class="mt-2"><a href="{{ route('meetings.guest.ics', $token) }}" class="font-medium underline" data-guest-ics>{{ __('planner.meetings.add_to_calendar') }}</a></div>
            </flux:callout.text>
        </flux:callout>
    @elseif ($meeting->status === 'cancelled')
        <flux:callout>{{ __('planner.meetings.was_cancelled') }}</flux:callout>
    @else
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('planner.meetings.which_times') }}</flux:heading>
            <p class="text-xs text-zinc-500">{{ __('planner.meetings.times_in', ['timezone' => $viewerTimezone]) }}</p>
            @include('livewire.planner.meetings.partials.respond')
        </flux:card>
    @endif

    <flux:card class="space-y-2">
        <div class="font-medium">{{ __('planner.meetings.keep_title') }}</div>
        <p class="text-sm text-zinc-500">{{ __('planner.meetings.keep_help') }}</p>
        <flux:button :href="route('meetings.guest.sign-in', $token)" size="sm" data-guest-sign-in>{{ __('planner.meetings.sign_in') }}</flux:button>
    </flux:card>
</section>
