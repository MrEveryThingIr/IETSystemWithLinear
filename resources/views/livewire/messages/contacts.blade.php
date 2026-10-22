<div class="mx-auto max-w-4xl space-y-6" data-contacts>
    <div>
        <flux:button :href="route('messages.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left" class="rtl:[&_svg]:rotate-180">{{ __('messages.title') }}</flux:button>
    </div>
    <x-app.page-header :title="__('messages.contacts')" :description="__('messages.contacts_page.help')" />

    @if ($status)
        <flux:callout variant="success" icon="check-circle">{{ $status }}</flux:callout>
    @endif

    @php($row = 'flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700')

    @if ($incoming->isNotEmpty())
        <flux:card class="space-y-3" data-incoming>
            <flux:heading size="lg">{{ __('messages.contacts_page.incoming') }}</flux:heading>
            @foreach ($incoming as $contact)
                @php($person = $people->get($contact->otherThan($meId)))
                @continue(! $person)
                <div class="{{ $row }}" wire:key="in-{{ $contact->id }}">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-app.actor-avatar :actor="$person" size="sm" />
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $person->user?->username }}</div>
                            @if ($contact->note)<div class="text-sm text-zinc-500 [overflow-wrap:anywhere]" dir="auto">“{{ $contact->note }}”</div>@endif
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" variant="primary" wire:click="respond({{ $contact->id }}, true)">{{ __('messages.contacts_page.accept') }}</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="respond({{ $contact->id }}, false)">{{ __('messages.contacts_page.decline') }}</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="block({{ $person->id }})" wire:confirm="{{ __('messages.block_confirm') }}">{{ __('messages.block') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </flux:card>
    @endif

    <flux:card class="space-y-3" data-accepted>
        <flux:heading size="lg">{{ __('messages.contacts_page.mine', ['count' => $accepted->count()]) }}</flux:heading>
        @forelse ($accepted as $contact)
            @php($person = $people->get($contact->otherThan($meId)))
            @continue(! $person)
            <div class="{{ $row }}" wire:key="c-{{ $contact->id }}">
                <div class="flex min-w-0 items-center gap-3">
                    <x-app.actor-avatar :actor="$person" size="sm" />
                    <span class="min-w-0 truncate font-medium">{{ $person->user?->username }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="primary" icon="chat-bubble-left-right" wire:click="message({{ $person->id }})">{{ __('messages.message_them') }}</flux:button>
                    <flux:dropdown position="bottom" align="end">
                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('messages.menu')" />
                        <flux:menu>
                            @if ($person->profile)<flux:menu.item :href="route('profiles.show', $person->profile)" icon="user">{{ __('messages.view_profile') }}</flux:menu.item>@endif
                            <flux:menu.item wire:click="remove({{ $contact->id }})" wire:confirm="{{ __('messages.contacts_page.remove_confirm') }}" icon="user-minus">{{ __('messages.contacts_page.remove') }}</flux:menu.item>
                            <flux:menu.item wire:click="block({{ $person->id }})" wire:confirm="{{ __('messages.block_confirm') }}" icon="no-symbol" variant="danger">{{ __('messages.block') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        @empty
            <flux:text>{{ __('messages.contacts_page.none') }}</flux:text>
        @endforelse
    </flux:card>

    <flux:card class="space-y-3" data-find>
        <flux:heading size="lg">{{ __('messages.contacts_page.find') }}</flux:heading>
        <flux:input wire:model.live.debounce.400ms="find" icon="magnifying-glass" :placeholder="__('messages.contacts_page.find_placeholder')" class="[&_input]:text-base sm:[&_input]:text-sm" />
        @if (mb_strlen(ltrim(trim($find), '@')) >= 3)
            <flux:input wire:model="note" maxlength="300" :label="__('messages.contacts_page.note')" class="[&_input]:text-base sm:[&_input]:text-sm" />
            @forelse ($results as $person)
                <div class="{{ $row }}" wire:key="r-{{ $person->id }}">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-app.actor-avatar :actor="$person" size="sm" />
                        <span class="min-w-0 truncate font-medium">{{ $person->user?->username }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if (! in_array((int) $person->id, $contactIds, true))
                            <flux:button size="sm" variant="primary" icon="user-plus" wire:click="ask({{ $person->id }})">{{ __('messages.contacts_page.ask') }}</flux:button>
                        @endif
                        @if ($canStart($person))
                            <flux:button size="sm" variant="ghost" icon="chat-bubble-left-right" wire:click="message({{ $person->id }})">{{ __('messages.message_them') }}</flux:button>
                        @endif
                    </div>
                </div>
            @empty
                <flux:text>{{ __('messages.no_results') }}</flux:text>
            @endforelse
        @else
            <flux:text class="text-sm">{{ __('messages.contacts_page.find_help') }}</flux:text>
        @endif
    </flux:card>

    @if ($outgoing->isNotEmpty())
        <flux:card class="space-y-3" data-outgoing>
            <flux:heading size="lg">{{ __('messages.contacts_page.outgoing') }}</flux:heading>
            @foreach ($outgoing as $contact)
                @php($person = $people->get($contact->otherThan($meId)))
                @continue(! $person)
                <div class="{{ $row }}" wire:key="out-{{ $contact->id }}">
                    <span class="min-w-0 truncate">{{ $person->user?->username }}</span>
                    <flux:button size="sm" variant="ghost" wire:click="remove({{ $contact->id }})">{{ __('messages.contacts_page.withdraw') }}</flux:button>
                </div>
            @endforeach
        </flux:card>
    @endif

    <flux:card class="space-y-3" data-privacy>
        <flux:heading size="lg">{{ __('messages.privacy.title') }}</flux:heading>
        <flux:text class="text-sm">{{ __('messages.privacy.help') }}</flux:text>
        <div class="grid gap-2 sm:grid-cols-3">
            @foreach (\App\Support\Messaging\MessagingPrivacy::SETTINGS as $option)
                <label @class(['flex min-h-11 cursor-pointer gap-3 rounded-xl border p-3 text-sm', 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' => $privacy === $option, 'border-zinc-200 dark:border-zinc-700' => $privacy !== $option])>
                    <input type="radio" wire:model.live="privacy" value="{{ $option }}" class="mt-1">
                    <span><span class="block font-medium">{{ __('messages.privacy.options.'.$option.'.title') }}</span><span class="block text-zinc-500">{{ __('messages.privacy.options.'.$option.'.help') }}</span></span>
                </label>
            @endforeach
        </div>
        <flux:text class="text-xs">{{ __('messages.privacy_note') }}</flux:text>
    </flux:card>

    @if ($blocked->isNotEmpty())
        <flux:card class="space-y-3" data-blocked>
            <flux:heading size="lg">{{ __('messages.contacts_page.blocked_people') }}</flux:heading>
            @foreach ($blocked as $block)
                <div class="{{ $row }}" wire:key="b-{{ $block->id }}">
                    <span class="min-w-0 truncate">{{ $block->blocked?->user?->username }}</span>
                    <flux:button size="sm" variant="ghost" wire:click="unblock({{ $block->blocked_actor_id }})">{{ __('messages.unblock') }}</flux:button>
                </div>
            @endforeach
        </flux:card>
    @endif
</div>
