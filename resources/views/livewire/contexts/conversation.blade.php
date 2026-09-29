<section class="mx-auto max-w-5xl space-y-6">
    <x-app.page-header :title="__('collaboration.conversation.title')" :description="__('collaboration.conversation.help')">
        <x-slot:actions>
            <flux:button :href="route('contexts.timeline', $context)" variant="ghost">
                {{ __('collaboration.tabs.timeline') }}
            </flux:button>
            <flux:button :href="route('contexts.contents.index', $context)" variant="ghost">
                {{ __('collaboration.tabs.content') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>{{ __('collaboration.conversation.non_authority') }}</flux:callout>

    @unless ($canPost)
        <flux:callout variant="warning">{{ __('collaboration.conversation.read_only') }}</flux:callout>
    @endunless

    <flux:card class="flex min-h-[34rem] flex-col gap-4">
        <div wire:poll.5s class="flex-1 space-y-5 overflow-y-auto">
            @forelse ($messages as $chatMessage)
                <article id="message-{{ $chatMessage->uuid }}" wire:key="context-message-{{ $chatMessage->uuid }}" class="space-y-2">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <x-app.actor-identity :actor="$chatMessage->author" size="xs" />
                        <span class="text-xs text-zinc-500">@if ($chatMessage->created_at)<x-app.local-datetime :value="$chatMessage->created_at" />@endif</span>
                    </div>

                    @if ($chatMessage->replyTo)
                        <div class="rounded-lg border-s-2 border-zinc-400 bg-zinc-100 px-3 py-2 text-xs dark:bg-zinc-800">
                            <x-app.actor-identity :actor="$chatMessage->replyTo->author" size="xs" />
                            <div class="line-clamp-2 whitespace-pre-wrap break-words">{{ $chatMessage->replyTo->body }}</div>
                        </div>
                    @endif

                    <div class="whitespace-pre-wrap break-words text-sm text-zinc-800 dark:text-zinc-200">{{ $chatMessage->body }}</div>

                    @if ($chatMessage->assets->isNotEmpty() || $chatMessage->evidenceReferences->isNotEmpty())
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($chatMessage->assets as $asset)
                                <div wire:key="message-asset-{{ $chatMessage->id }}-{{ $asset->id }}" class="rounded-lg border border-zinc-200 p-3 text-xs dark:border-zinc-700">
                                    <div class="font-medium">{{ __('collaboration.conversation.asset') }}</div>
                                    <div class="mt-1 break-all">{{ $asset->original_filename }}</div>
                                    <a href="{{ route('contexts.conversation.assets.download', [$context, $chatMessage, $asset]) }}" class="mt-2 inline-block font-medium underline">
                                        {{ __('collaboration.conversation.download_asset') }}
                                    </a>
                                </div>
                            @endforeach

                            @foreach ($chatMessage->evidenceReferences as $reference)
                                <div wire:key="message-evidence-{{ $chatMessage->id }}-{{ $reference->id }}" class="rounded-lg border border-zinc-200 p-3 text-xs dark:border-zinc-700">
                                    <div class="font-medium">{{ __('collaboration.conversation.evidence') }}</div>
                                    <div class="mt-1" dir="auto">{{ $reference->revision?->title ?: $reference->content?->activeRevision?->title ?: __('ui.content.untitled') }}</div>
                                    <a href="{{ route('content-evidence.show', $reference) }}" class="mt-2 inline-block font-medium underline">
                                        {{ __('collaboration.conversation.open_evidence') }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($canPost)
                        <button type="button" wire:click="replyTo({{ $chatMessage->id }})" class="text-xs text-zinc-500 underline underline-offset-2">
                            {{ __('collaboration.conversation.reply') }}
                        </button>
                    @endif
                </article>
            @empty
                <x-app.empty-state :title="__('collaboration.conversation.none')" :description="__('collaboration.conversation.none_help')" />
            @endforelse
        </div>

        @if ($canPost)
            <form wire:submit="send" class="space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                @if ($replyToMessage)
                    <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-200 p-3 text-xs dark:border-zinc-700">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 font-semibold">
                                <span>{{ __('collaboration.conversation.replying_to') }}</span>
                                <x-app.actor-identity :actor="$replyToMessage->author" size="xs" />
                            </div>
                            <div class="line-clamp-2 whitespace-pre-wrap break-words">{{ $replyToMessage->body }}</div>
                        </div>
                        <button type="button" wire:click="cancelReply" class="shrink-0 underline underline-offset-2">
                            {{ __('collaboration.conversation.cancel_reply') }}
                        </button>
                    </div>
                @endif

                <flux:textarea
                    wire:model="message"
                    :label="__('collaboration.conversation.message')"
                    rows="3"
                    maxlength="4000"
                    :placeholder="__('collaboration.conversation.message_placeholder')"
                />

                @if ($assets->isNotEmpty())
                    <details class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <summary class="cursor-pointer font-medium">{{ __('collaboration.conversation.attachments') }}</summary>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('collaboration.conversation.attachments_help') }}</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($assets as $asset)
                                <label wire:key="available-asset-{{ $asset->id }}" class="flex items-start gap-2 rounded-lg border border-zinc-200 p-2 text-sm dark:border-zinc-700">
                                    <input type="checkbox" wire:model="assetIds" value="{{ $asset->id }}" class="mt-1">
                                    <span class="min-w-0 break-all">{{ $asset->original_filename }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endif

                @if ($evidenceReferences->isNotEmpty())
                    <details class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                        <summary class="cursor-pointer font-medium">{{ __('collaboration.conversation.references') }}</summary>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('collaboration.conversation.references_help') }}</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($evidenceReferences as $reference)
                                <label wire:key="available-evidence-{{ $reference->id }}" class="flex items-start gap-2 rounded-lg border border-zinc-200 p-2 text-sm dark:border-zinc-700">
                                    <input type="checkbox" wire:model="evidenceReferenceIds" value="{{ $reference->id }}" class="mt-1">
                                    <span class="min-w-0" dir="auto">{{ $reference->revision?->title ?: $reference->content?->activeRevision?->title ?: __('ui.content.untitled') }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                @endif

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="send">
                        {{ __('collaboration.conversation.send') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:card>
</section>
