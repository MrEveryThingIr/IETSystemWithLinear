<section class="space-y-6">
    <x-app.page-header :title="$group->name" :description="$group->description ?: __('ui.groups.no_description')">
        <x-slot:actions>
            <flux:button :href="route('groups.index')" variant="ghost">{{ __('ui.common.all_groups') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <x-app.group-space-tabs :group="$group" :current-space="$space" />
    <x-app.space-section-tabs :group="$group" :space="$space" current="chat" />

    <flux:card class="flex min-h-[34rem] flex-col gap-4">
        <div class="border-b border-zinc-200 pb-3 dark:border-zinc-800">
            <flux:heading size="lg"># {{ $space->name }}</flux:heading>
            <flux:text>{{ __('ui.spaces.chat_description') }}</flux:text>
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
                    @if ($chatMessage->replyTo)
                        <div class="rounded-lg border-s-2 border-zinc-400 bg-zinc-100 px-3 py-1 text-xs dark:bg-zinc-800">
                            <span class="font-semibold">{{ $chatMessage->replyTo->author->user?->username ?? __('ui.common.unknown_account') }}</span>
                            <span class="line-clamp-2 whitespace-pre-wrap break-words">{{ $chatMessage->replyTo->body }}</span>
                        </div>
                    @endif
                    <div class="whitespace-pre-wrap break-words text-sm text-zinc-800 dark:text-zinc-200">{{ $chatMessage->body }}</div>
                    <button type="button" wire:click="replyTo({{ $chatMessage->id }})" class="text-xs text-zinc-500 underline underline-offset-2 hover:text-zinc-900 dark:hover:text-zinc-100">{{ __('ui.spaces.reply') }}</button>
                </div>
            @empty
                <x-app.empty-state :title="__('ui.spaces.no_messages')" :description="__('ui.spaces.no_messages_help')" />
            @endforelse
        </div>

        <form wire:submit="send" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
            @if ($replyToMessage)
                <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 p-3 text-xs dark:border-zinc-700">
                    <div class="min-w-0">
                        <div class="font-semibold">{{ __('ui.spaces.replying_to') }} {{ $replyToMessage->author->user?->username ?? __('ui.common.unknown_account') }}</div>
                        <div class="line-clamp-2 whitespace-pre-wrap break-words">{{ $replyToMessage->body }}</div>
                    </div>
                    <button type="button" wire:click="cancelReply" class="shrink-0 underline underline-offset-2">{{ __('ui.spaces.cancel_reply') }}</button>
                </div>
            @endif
            <flux:textarea
                wire:model="message"
                :label="__('ui.spaces.message')"
                rows="3"
                maxlength="4000"
                :placeholder="__('ui.spaces.message_placeholder')"
            />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="send">{{ __('ui.spaces.send') }}</flux:button>
            </div>
        </form>
    </flux:card>
</section>
