<section class="space-y-6">
    @unless ($embedded)
        <x-app.page-header :title="$group->name" :description="$group->description ?: __('ui.groups.no_description')">
            <x-slot:actions>
                <flux:button :href="route('groups.show', $group)" variant="ghost">{{ __('classroom.group.back_home') }}</flux:button>
            </x-slot:actions>
        </x-app.page-header>

        <x-app.group-space-tabs :group="$group" :current-space="$space" />
        <x-app.space-section-tabs :group="$group" :space="$space" current="chat" />
    @endunless

    <flux:card @class(['flex flex-col gap-4', 'min-h-[34rem]' => ! $embedded, 'min-h-[26rem]' => $embedded])>
        <div class="flex items-center justify-between gap-3 border-b border-zinc-200 pb-3 dark:border-zinc-800">
            <div>
                <flux:heading size="lg"># {{ $space->name }}</flux:heading>
                @unless ($embedded)
                    <flux:text>{{ __('ui.spaces.chat_description') }}</flux:text>
                @endunless
            </div>
            @if ($embedded)
                <flux:button :href="route('groups.spaces.show', [$group, $space])" size="sm" variant="ghost">{{ __('classroom.group.open_full_chat') }}</flux:button>
            @endif
        </div>

        <div wire:poll.5s @class(['flex-1 space-y-4 overflow-y-auto', 'max-h-[30rem]' => $embedded])>
            @forelse ($messages as $chatMessage)
                @php
                    $isMine = (int) $chatMessage->author_actor_id === (int) auth()->user()?->actor?->id;
                @endphp
                <div wire:key="space-message-{{ $chatMessage->id }}" class="group/message space-y-1">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <flux:text class="font-semibold">
                            <x-app.actor-identity :actor="$chatMessage->author" size="xs" />
                        </flux:text>
                        <flux:text class="text-xs text-zinc-500">
                            <x-app.local-datetime :value="$chatMessage->created_at" />
                            @if ($chatMessage->isEdited())
                                · {{ __('classroom.chat.edited') }}
                            @endif
                        </flux:text>
                    </div>
                    @if ($chatMessage->replyTo)
                        <div class="rounded-lg border-s-2 border-zinc-400 bg-zinc-100 px-3 py-1 text-xs dark:bg-zinc-800">
                            <x-app.actor-identity :actor="$chatMessage->replyTo->author" size="xs" />
                            <span class="line-clamp-2 whitespace-pre-wrap break-words">{{ $chatMessage->replyTo->isDeleted() ? __('classroom.chat.deleted') : $chatMessage->replyTo->currentBody() }}</span>
                        </div>
                    @endif

                    @if ($chatMessage->isDeleted())
                        <div class="text-sm italic text-zinc-500">{{ __('classroom.chat.deleted') }}</div>
                    @elseif ($editingMessageId === $chatMessage->id)
                        <form wire:submit="saveEdit" class="space-y-2">
                            <flux:textarea wire:model="editingBody" rows="2" maxlength="4000" :aria-label="__('classroom.chat.edit')" />
                            <div class="flex gap-2">
                                <flux:button type="submit" size="sm" variant="primary">{{ __('classroom.chat.save') }}</flux:button>
                                <flux:button type="button" size="sm" variant="ghost" wire:click="cancelEditing">{{ __('classroom.chat.cancel') }}</flux:button>
                            </div>
                        </form>
                    @else
                        <div class="whitespace-pre-wrap break-words text-sm text-zinc-800 dark:text-zinc-200">{{ $chatMessage->currentBody() }}</div>
                        <div class="flex gap-3 text-xs text-zinc-500">
                            <button type="button" wire:click="replyTo({{ $chatMessage->id }})" class="underline underline-offset-2 hover:text-zinc-900 dark:hover:text-zinc-100">{{ __('ui.spaces.reply') }}</button>
                            @if ($isMine)
                                <button type="button" wire:click="startEditing({{ $chatMessage->id }})" class="underline underline-offset-2 hover:text-zinc-900 dark:hover:text-zinc-100">{{ __('classroom.chat.edit') }}</button>
                                <button type="button" wire:click="deleteMessage({{ $chatMessage->id }})" wire:confirm="{{ __('classroom.chat.delete_confirm') }}" class="underline underline-offset-2 hover:text-red-600">{{ __('classroom.chat.delete') }}</button>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <x-app.empty-state :title="__('ui.spaces.no_messages')" :description="__('ui.spaces.no_messages_help')" />
            @endforelse
        </div>

        <form wire:submit="send" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
            @if ($replyToMessage)
                <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 p-3 text-xs dark:border-zinc-700">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 font-semibold"><span>{{ __('ui.spaces.replying_to') }}</span><x-app.actor-identity :actor="$replyToMessage->author" size="xs" /></div>
                        <div class="line-clamp-2 whitespace-pre-wrap break-words">{{ $replyToMessage->currentBody() }}</div>
                    </div>
                    <button type="button" wire:click="cancelReply" class="shrink-0 underline underline-offset-2">{{ __('ui.spaces.cancel_reply') }}</button>
                </div>
            @endif
            <flux:textarea
                wire:model="message"
                :label="$embedded ? null : __('ui.spaces.message')"
                :aria-label="__('ui.spaces.message')"
                rows="{{ $embedded ? 2 : 3 }}"
                maxlength="4000"
                :placeholder="__('ui.spaces.message_placeholder')"
            />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="send">{{ __('ui.spaces.send') }}</flux:button>
            </div>
        </form>
    </flux:card>
</section>
