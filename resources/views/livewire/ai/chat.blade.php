<section class="mx-auto max-w-5xl space-y-6">
    <x-app.page-header :title="__('ai.chat.title')" :description="__('ai.chat.description')">
        <x-slot:actions>
            <flux:button wire:click="clearConversation" variant="ghost">{{ __('ai.chat.clear') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:card class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge :color="$configured ? 'green' : 'red'">
                {{ $configured ? __('ai.chat.configured') : __('ai.chat.not_configured') }}
            </flux:badge>
            <span class="text-sm text-zinc-500" dir="ltr">{{ $configuredModel }}</span>
        </div>

        <flux:text>{{ __('ai.chat.privacy') }}</flux:text>

        @if (! $configured)
            <flux:callout variant="warning">{{ __('ai.chat.configure_help') }}</flux:callout>
        @endif
    </flux:card>

    <flux:card class="space-y-5">
        <div class="max-h-[55vh] space-y-4 overflow-y-auto pe-1">
            @forelse ($messages as $index => $chatMessage)
                <div wire:key="ai-chat-{{ $index }}" class="flex {{ $chatMessage['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[88%] rounded-2xl px-4 py-3 text-sm leading-6 {{ $chatMessage['role'] === 'user' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-950' : 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' }}">
                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide opacity-60">
                            {{ $chatMessage['role'] === 'user' ? __('ai.chat.you') : __('ai.chat.assistant') }}
                        </div>
                        <div class="whitespace-pre-wrap break-words" dir="auto">{{ $chatMessage['content'] }}</div>
                    </div>
                </div>
            @empty
                <x-app.empty-state :title="__('ai.chat.empty')" />
            @endforelse
        </div>

        @if ($assistantError)
            <flux:callout variant="danger">
                <div dir="auto">{{ $assistantError }}</div>
            </flux:callout>
        @endif

        @if ($lastModel || $lastResponseId)
            <div class="flex flex-wrap gap-x-4 gap-y-1 border-t border-zinc-200 pt-3 text-xs text-zinc-500 dark:border-zinc-800">
                @if ($lastModel)<span dir="ltr">model: {{ $lastModel }}</span>@endif
                @if ($lastResponseId)<span dir="ltr">response: {{ $lastResponseId }}</span>@endif
                @if (($lastUsage['total_tokens'] ?? null) !== null)<span>{{ __('ai.chat.tokens') }}: {{ number_format($lastUsage['total_tokens']) }}</span>@endif
            </div>
        @endif

        <form wire:submit="send" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
            <flux:textarea
                wire:model="message"
                :label="__('ai.chat.message')"
                :placeholder="__('ai.chat.placeholder')"
                rows="4"
                maxlength="8000"
                :disabled="!$configured"
            />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="send" :disabled="!$configured">
                    {{ __('ai.chat.send') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</section>
