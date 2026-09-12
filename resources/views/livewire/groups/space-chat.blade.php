<section class="space-y-6">
    <x-app.page-header :title="$group->name" :description="$group->description ?: __('ui.groups.no_description')">
        <x-slot:actions>
            <flux:button :href="route('groups.index')" variant="ghost">{{ __('ui.common.all_groups') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800">
        <flux:text class="me-2 font-medium">Spaces</flux:text>
        <flux:button :href="route('groups.show', $group)" size="sm" variant="ghost">Overview</flux:button>
        @foreach ($spaces as $candidate)
            <flux:button
                :href="route('groups.spaces.show', [$group, $candidate])"
                size="sm"
                :variant="$candidate->is($space) ? 'primary' : 'ghost'"
            >
                # {{ $candidate->name }}
            </flux:button>
        @endforeach
    </div>

    <flux:card class="flex min-h-[34rem] flex-col gap-4">
        <div class="border-b border-zinc-200 pb-3 dark:border-zinc-800">
            <flux:heading size="lg"># {{ $space->name }}</flux:heading>
            <flux:text>Simple group chat. This is the first communication surface built on the group-space substrate.</flux:text>
        </div>

        <div wire:poll.5s class="flex-1 space-y-4 overflow-y-auto">
            @forelse ($messages as $chatMessage)
                <div wire:key="space-message-{{ $chatMessage->id }}" class="space-y-1">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <flux:text class="font-semibold">
                            {{ $chatMessage->author->user?->username ?? __('ui.common.unknown_account') }}
                        </flux:text>
                        <flux:text class="text-xs text-zinc-500">
                            {{ $chatMessage->created_at->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') }}
                        </flux:text>
                    </div>
                    <div class="whitespace-pre-wrap break-words text-sm text-zinc-800 dark:text-zinc-200">{{ $chatMessage->body }}</div>
                </div>
            @empty
                <x-app.empty-state title="No messages yet" description="Start the conversation in this space." />
            @endforelse
        </div>

        <form wire:submit="send" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
            <flux:textarea
                wire:model="message"
                label="Message"
                rows="3"
                maxlength="4000"
                placeholder="Write a message to the group..."
            />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="send">Send</flux:button>
            </div>
        </form>
    </flux:card>
</section>
