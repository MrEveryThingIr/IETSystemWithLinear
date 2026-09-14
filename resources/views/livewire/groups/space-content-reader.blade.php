<section class="mx-auto w-full max-w-7xl space-y-6 px-1 pb-12">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:button :href="route('groups.spaces.contents.index', [$group, $space])" variant="ghost" size="sm">
            ← {{ __('reader.back_to_library') }}
        </flux:button>

        @if ($canEnterStudio)
            <flux:button :href="route('groups.spaces.contents.studio', [$group, $space, $content])" variant="primary" size="sm">
                {{ __('reader.edit_in_studio') }}
            </flux:button>
        @endif
    </div>

    @if ($legacyEvidence)
        <flux:callout variant="warning">
            <div class="font-medium">{{ __('reader.legacy_title') }}</div>
            <div class="mt-1 text-sm">{{ __('reader.legacy_help') }}</div>
        </flux:callout>
    @endif

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <article class="min-w-0">
            <header class="mx-auto max-w-3xl border-b border-zinc-200 pb-6 dark:border-zinc-800">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                    <span dir="auto">{{ __('reader.by_author', ['author' => $content->author->user?->username ?? __('ui.common.unknown_account')]) }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ __('reader.published', ['date' => $content->published_at?->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') ?? '—']) }}</span>
                    @unless ($legacyEvidence)
                        <flux:badge size="sm">{{ __('reader.verified_edition') }}</flux:badge>
                    @endunless
                </div>

                <h1 class="text-3xl font-semibold tracking-tight text-zinc-950 sm:text-4xl dark:text-white" dir="auto">
                    {{ $revision->title }}
                </h1>

                <div class="mt-3 text-sm text-zinc-500" dir="auto">{{ $content->definition->name }}</div>
            </header>

            <div class="mx-auto mt-8 max-w-3xl space-y-7">
                @forelse ($definitionVersion->schema['fields'] as $field)
                    @php
                        $value = $revision->payload[$field['key']] ?? null;
                        if ($field['type'] === 'boolean') {
                            $displayValue = $value ? __('ui.content.yes') : __('ui.content.no');
                        } elseif ($field['type'] === 'select' && $value !== null) {
                            $match = collect($field['options'])->firstWhere('value', $value);
                            $displayValue = $match['label'] ?? $value;
                        } else {
                            $displayValue = $value;
                        }
                    @endphp

                    @if ($displayValue !== null && $displayValue !== '')
                        <section class="space-y-2">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500" dir="auto">{{ $field['label'] }}</h2>
                            <div class="whitespace-pre-wrap break-words text-[1.05rem] leading-8 text-zinc-800 dark:text-zinc-200" dir="auto">{{ $displayValue }}</div>
                        </section>
                    @endif
                @empty
                    <flux:text>{{ __('reader.no_body') }}</flux:text>
                @endforelse

                @if ($revision->assets->isNotEmpty())
                    <section class="space-y-5 border-t border-zinc-200 pt-7 dark:border-zinc-800">
                        <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">{{ __('reader.media') }}</h2>

                        @foreach ($revision->assets as $asset)
                            @php
                                $assetUrl = route('groups.spaces.contents.assets.show', [$group, $space, $content, $asset]);
                                $downloadUrl = route('groups.spaces.contents.assets.download', [$group, $space, $content, $asset]);
                                $caption = $asset->pivot->caption;
                            @endphp

                            <figure class="space-y-3 rounded-xl border border-zinc-200 p-3 sm:p-4 dark:border-zinc-800">
                                @switch($asset->mediaKind())
                                    @case('image')
                                        <img
                                            src="{{ $assetUrl }}"
                                            alt="{{ $asset->alt_text ?: ($caption ?: $asset->original_filename) }}"
                                            class="mx-auto max-h-[42rem] w-auto rounded-lg object-contain"
                                            loading="lazy"
                                        />
                                        @break
                                    @case('audio')
                                        <audio controls preload="metadata" class="w-full" src="{{ $assetUrl }}"></audio>
                                        @break
                                    @case('video')
                                        <video controls preload="metadata" class="max-h-[42rem] w-full rounded-lg bg-black" src="{{ $assetUrl }}"></video>
                                        @break
                                    @case('pdf')
                                        <a href="{{ $assetUrl }}" target="_blank" rel="noopener" class="block rounded-lg bg-zinc-50 p-5 font-medium hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800" dir="auto">
                                            {{ $asset->original_filename }} ↗
                                        </a>
                                        @break
                                    @default
                                        <a href="{{ $downloadUrl }}" class="block rounded-lg bg-zinc-50 p-5 font-medium hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800" dir="auto">
                                            {{ __('reader.download') }} · {{ $asset->original_filename }}
                                        </a>
                                @endswitch

                                @if ($caption)
                                    <figcaption class="text-sm leading-6 text-zinc-600 dark:text-zinc-400" dir="auto">{{ $caption }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </section>
                @endif

                <section class="space-y-6 border-t border-zinc-200 pt-7 dark:border-zinc-800" id="discussion">
                    <div class="space-y-1">
                        <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">{{ __('interactions.title') }}</h2>
                        <p class="text-sm text-zinc-500">{{ __('interactions.edition_help') }}</p>
                    </div>

                    @error('interaction')
                        <flux:callout variant="warning">{{ $message }}</flux:callout>
                    @enderror

                    @if (! $canInteract)
                        <flux:callout>{{ __('interactions.verified_only') }}</flux:callout>
                    @endif

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="me-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('interactions.reactions') }}</span>
                        @foreach ($reactionTypes as $reactionType)
                            @php
                                $reactionActive = in_array($reactionType, $viewerReactions, true);
                                $reactionCount = $reactionCounts[$reactionType] ?? 0;
                            @endphp
                            <flux:button
                                wire:key="reaction-{{ $reactionType }}"
                                wire:click="toggleReaction('{{ $reactionType }}')"
                                wire:loading.attr="disabled"
                                wire:target="toggleReaction"
                                size="sm"
                                :variant="$reactionActive ? 'primary' : 'ghost'"
                                :disabled="! $canInteract"
                            >
                                {{ __('interactions.reaction.'.$reactionType) }} · {{ $reactionCount }}
                            </flux:button>
                        @endforeach
                    </div>

                    @if ($canInteract)
                        <form wire:submit="addComment" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <label class="block space-y-2">
                                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('interactions.add_comment') }}</span>
                                <textarea
                                    wire:model="commentBody"
                                    rows="4"
                                    maxlength="5000"
                                    placeholder="{{ __('interactions.comment_placeholder') }}"
                                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                    dir="auto"
                                ></textarea>
                            </label>
                            @error('commentBody')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            <div class="flex justify-end">
                                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addComment">
                                    {{ __('interactions.post_comment') }}
                                </flux:button>
                            </div>
                        </form>
                    @endif

                    <div class="space-y-5">
                        @forelse ($annotations as $annotation)
                            <article wire:key="annotation-{{ $annotation->uuid }}" class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <div class="font-medium" dir="auto">{{ $annotation->author->user?->username ?? __('ui.common.unknown_account') }}</div>
                                    <div class="text-xs text-zinc-500">{{ $annotation->created_at->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') }}</div>
                                </div>
                                <div class="whitespace-pre-wrap break-words text-sm leading-7 text-zinc-800 dark:text-zinc-200" dir="auto">{{ $annotation->body }}</div>

                                @if ($canInteract)
                                    <div>
                                        <flux:button wire:click="startReply('{{ $annotation->uuid }}')" size="sm" variant="ghost">
                                            {{ __('interactions.reply') }}
                                        </flux:button>
                                    </div>
                                @endif

                                @if ($annotation->replies->isNotEmpty())
                                    <div class="space-y-3 border-s-2 border-zinc-200 ps-4 dark:border-zinc-800">
                                        @foreach ($annotation->replies as $reply)
                                            <div wire:key="reply-{{ $reply->uuid }}" class="space-y-1 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-900/60">
                                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                    <div class="font-medium" dir="auto">{{ $reply->author->user?->username ?? __('ui.common.unknown_account') }}</div>
                                                    <div class="text-xs text-zinc-500">{{ $reply->created_at->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') }}</div>
                                                </div>
                                                <div class="whitespace-pre-wrap break-words text-sm leading-6" dir="auto">{{ $reply->body }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($canInteract && $replyingTo === $annotation->uuid)
                                    <form wire:submit="addReply" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                                        <textarea
                                            wire:model="replyBody"
                                            rows="3"
                                            maxlength="5000"
                                            placeholder="{{ __('interactions.reply_placeholder') }}"
                                            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                                            dir="auto"
                                        ></textarea>
                                        @error('replyBody')
                                            <div class="text-sm text-red-600">{{ $message }}</div>
                                        @enderror
                                        <div class="flex justify-end gap-2">
                                            <flux:button wire:click="cancelReply" type="button" variant="ghost">{{ __('interactions.cancel') }}</flux:button>
                                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addReply">{{ __('interactions.post_reply') }}</flux:button>
                                        </div>
                                    </form>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-xl bg-zinc-50 p-5 text-sm text-zinc-500 dark:bg-zinc-900/50">{{ __('interactions.no_comments') }}</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </article>

        @if (count($outline) > 0)
            <aside class="self-start xl:sticky xl:top-6">
                <flux:card class="space-y-3">
                    <div>
                        <flux:heading>{{ __('reader.outline') }}</flux:heading>
                        <flux:text class="text-sm">{{ __('reader.outline_help') }}</flux:text>
                    </div>
                    <x-app.content-outline-tree :items="$outline" :group="$group" :space="$space" />
                </flux:card>
            </aside>
        @endif
    </div>
</section>
