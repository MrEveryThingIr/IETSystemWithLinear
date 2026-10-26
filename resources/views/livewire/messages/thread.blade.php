<div
    class="flex h-full min-h-0 flex-col"
    x-data="messageThread"
    data-state-url="{{ route('messages.state', $conversation) }}"
    data-version="{{ $version }}"
    data-message-thread="{{ $conversation->uuid }}"
>
    {{-- Header --}}
    @unless ($embedded)
    <header class="flex min-w-0 items-center gap-2 border-b border-zinc-200 px-2 py-2 sm:px-4 dark:border-zinc-700">
        <flux:button :href="route('messages.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left" class="lg:hidden rtl:[&_svg]:rotate-180" :aria-label="__('messages.back')" />
        @if ($other)
            <x-app.actor-avatar :actor="$other" size="sm" />
        @else
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                <flux:icon :name="app(\App\Support\ContextPresenter::class)->icon($context)" class="size-5 text-zinc-500" />
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <div class="truncate font-semibold" dir="auto">{{ $title }}</div>
            <div class="truncate text-xs text-zinc-500">
                @if ($other)
                    {{ __('messages.kinds.direct') }}
                @else
                    {{ __('messages.kinds.'.$context->kind->value) }}
                @endif
                @if ($participant?->isMuted()) · {{ __('messages.muted') }} @endif
            </div>
        </div>
        <flux:dropdown position="bottom" align="end">
            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" :aria-label="__('messages.menu')" />
            <flux:menu>
                @if ($participant?->isMuted())
                    <flux:menu.item wire:click="mute(null)" icon="bell">{{ __('messages.unmute') }}</flux:menu.item>
                @else
                    <flux:menu.item wire:click="mute(8)" icon="bell-slash">{{ __('messages.mute_8h') }}</flux:menu.item>
                    <flux:menu.item wire:click="mute(0)" icon="bell-slash">{{ __('messages.mute_always') }}</flux:menu.item>
                @endif
                <flux:menu.item wire:click="pin({{ $participant?->pinned_at ? 'false' : 'true' }})" icon="bookmark">{{ $participant?->pinned_at ? __('messages.unpin') : __('messages.pin') }}</flux:menu.item>
                <flux:menu.item wire:click="archive({{ $participant?->archived_at ? 'false' : 'true' }})" icon="archive-box">{{ $participant?->archived_at ? __('messages.unarchive') : __('messages.archive') }}</flux:menu.item>
                @if ($other)
                    <flux:menu.separator />
                    @if ($other->profile)
                        <flux:menu.item :href="route('profiles.show', $other->profile)" icon="user">{{ __('messages.view_profile') }}</flux:menu.item>
                    @endif
                    <flux:menu.item wire:click="openReport" icon="flag">{{ __('messages.report.person') }}</flux:menu.item>
                    @if ($iBlocked)
                        <flux:menu.item wire:click="block(false)" icon="no-symbol">{{ __('messages.unblock') }}</flux:menu.item>
                    @else
                        <flux:menu.item wire:click="block(true)" wire:confirm="{{ __('messages.block_confirm') }}" icon="no-symbol" variant="danger">{{ __('messages.block') }}</flux:menu.item>
                    @endif
                @endif
            </flux:menu>
        </flux:dropdown>
    </header>
    @endunless

    @if ($reporting)
        <form wire:submit="submitReport" class="space-y-3 border-b border-zinc-200 bg-amber-50 p-3 sm:px-4 dark:border-zinc-700 dark:bg-amber-950/30" data-report-panel>
            <flux:heading size="sm">{{ $reportMessage ? __('messages.report.message') : __('messages.report.person') }}</flux:heading>
            <flux:select wire:model="reportReason" :label="__('messages.report.reason')">
                @foreach (\App\Models\MessageReport::REASONS as $reason)
                    <flux:select.option :value="$reason">{{ __('messages.report.reasons.'.$reason) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="reportDetails" rows="2" :label="__('messages.report.details')" />
            <flux:text class="text-xs">{{ __('messages.report.help') }}</flux:text>
            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" size="sm" variant="primary">{{ __('messages.report.send') }}</flux:button>
                <flux:button type="button" size="sm" variant="ghost" wire:click="closeReport">{{ __('messages.cancel') }}</flux:button>
            </div>
        </form>
    @endif

    {{-- History --}}
    <div x-ref="scroller" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-2 py-3 sm:px-4" aria-live="polite" data-message-list>
        @if ($hasOlder)
            <div class="mb-3 text-center">
                <flux:button wire:click="loadOlder" size="xs" variant="ghost">{{ __('messages.load_older') }}</flux:button>
            </div>
        @endif
        @if ($messages->isEmpty())
            <div class="flex h-full flex-col items-center justify-center gap-2 px-6 text-center text-sm text-zinc-500">
                <flux:icon.chat-bubble-left-right class="size-8" />
                <span>{{ __('messages.empty_thread') }}</span>
            </div>
        @endif

        @php $lastDay = null; @endphp
        <ol class="space-y-1">
            @foreach ($messages as $message)
                @php
                    $mine = (int) $message->author_actor_id === $meId;
                    $deleted = $message->isDeleted();
                    $day = $message->created_at?->timezone(\App\Support\TemporalPreferences::timezoneFor(auth()->user()))->toDateString();
                    $reactions = $message->reactions->groupBy('emoji');
                    $mediaOnly = ! $deleted && $message->kind === 'text' && $message->currentBody() === '' && ! $message->replyTo
                        && $message->assets->isNotEmpty() && $message->assets->every(fn ($a) => str_starts_with((string) $a->mime_type, 'image/') || str_starts_with((string) $a->mime_type, 'video/'));
                @endphp
                @if ($day !== $lastDay)
                    <li class="my-3 flex justify-center" aria-hidden="true"><span class="rounded-full bg-zinc-100 px-3 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800"><x-app.local-date :value="$message->created_at" /></span></li>
                    @php $lastDay = $day; @endphp
                @endif
                @if ($message->kind === 'deal')
                    <li wire:key="m-{{ $message->id }}" class="my-2 flex justify-center"><span class="rounded-full bg-sky-50 px-3 py-1 text-xs text-sky-800 dark:bg-sky-950 dark:text-sky-200">{{ __('deals.timeline.'.($message->meta['type'] ?? 'terms_proposed'), ['version' => $message->meta['version'] ?? '']) }} · {{ $message->author?->user?->username }}</span></li>
                    @continue
                @endif
                <li wire:key="m-{{ $message->id }}" id="message-{{ $message->uuid }}" @class(['group flex min-w-0', 'justify-end' => $mine, 'justify-start' => ! $mine])>
                    <div @class(['flex min-w-0 max-w-[85%] flex-col gap-1 sm:max-w-[75%]', 'items-end' => $mine, 'items-start' => ! $mine])>
                        @if (! $mine && ! $other)
                            <span class="px-1 text-xs font-medium text-zinc-500" dir="auto">{{ $message->author?->user?->username }}</span>
                        @endif
                        <div @class([
                            'min-w-0 max-w-full rounded-2xl text-[15px] leading-6 shadow-xs',
                            'px-3 py-2' => ! $mediaOnly,
                            'overflow-hidden p-0.5 [&_img]:mt-0 [&_video]:mt-0' => $mediaOnly,
                            'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' => $mine && ! $deleted,
                            'bg-white text-zinc-900 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-100 dark:ring-zinc-700' => ! $mine && ! $deleted,
                            'bg-transparent text-zinc-500 italic ring-1 ring-dashed ring-zinc-300 dark:ring-zinc-700' => $deleted,
                        ])>
                            @if ($deleted)
                                {{ __('messages.deleted') }}
                            @else
                                @if ($message->replyTo)
                                    <a href="#message-{{ $message->replyTo->uuid }}" class="mb-1 block border-s-2 border-current/40 ps-2 text-xs opacity-80">
                                        <span class="font-medium">{{ $message->replyTo->author?->user?->username }}</span>
                                        <span class="block truncate" dir="auto">{{ $message->replyTo->isDeleted() ? __('messages.deleted') : \Illuminate\Support\Str::limit($message->replyTo->currentBody() ?: __('messages.kinds_short.'.$message->replyTo->kind), 80) }}</span>
                                    </a>
                                @endif

                                @if ($editing === $message->id)
                                    <form wire:submit="saveEdit" class="space-y-2">
                                        <textarea wire:model="editBody" rows="3" class="block w-full min-w-[12rem] rounded-lg border border-zinc-300 bg-white p-2 text-base text-zinc-900 sm:text-sm"></textarea>
                                        <div class="flex gap-2"><flux:button type="submit" size="xs" variant="primary">{{ __('messages.save') }}</flux:button><flux:button type="button" size="xs" variant="ghost" wire:click="cancelEdit">{{ __('messages.cancel') }}</flux:button></div>
                                    </form>
                                @elseif ($message->kind === 'location' && is_array($message->meta))
                                    @php $lat = $message->meta['lat'] ?? 0; @endphp
                                    @php $lng = $message->meta['lng'] ?? 0; @endphp
                                    <a href="https://www.openstreetmap.org/?mlat={{ $lat }}&mlon={{ $lng }}#map=17/{{ $lat }}/{{ $lng }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 underline-offset-2 hover:underline" data-location>
                                        <flux:icon.map-pin class="size-5 shrink-0" />
                                        <span><span class="block font-medium">{{ __('messages.location.shared') }}</span><span class="block text-xs opacity-80" dir="ltr">{{ $lat }}, {{ $lng }}</span></span>
                                    </a>
                                @elseif ($message->kind === 'contact')
                                    @php $card = $contactCards->get((int) ($message->meta['actor_id'] ?? 0)); @endphp
                                    @if ($card)
                                        <div class="flex items-center gap-2" data-contact-card>
                                            <x-app.actor-avatar :actor="$card" size="xs" />
                                            <span class="min-w-0"><span class="block text-xs opacity-80">{{ __('messages.contact_card') }}</span><span class="block truncate font-medium" dir="auto">{{ $card->user?->username }}</span></span>
                                            @if ((int) $card->id !== $meId)
                                                <form method="POST" action="{{ route('messages.start', $card) }}">@csrf<button type="submit" class="ms-2 rounded-md px-2 py-1 text-xs underline">{{ __('messages.message_them') }}</button></form>
                                            @endif
                                        </div>
                                    @endif
                                @else
                                    @if ($message->currentBody() !== '')
                                        <p class="whitespace-pre-wrap [overflow-wrap:anywhere]" dir="auto">{{ $message->currentBody() }}</p>
                                    @endif
                                @endif

                                @foreach ($message->assets as $asset)
                                    @php $src = route('messages.assets.show', [$conversation, $message, $asset]); @endphp
                                    @if ($message->kind === 'voice' || str_starts_with((string) $asset->mime_type, 'audio/'))
                                        <div class="mt-1 flex items-center gap-2" data-voice>
                                            <flux:icon.microphone class="size-4 shrink-0" />
                                            <audio controls preload="none" src="{{ $src }}" class="h-9 w-56 max-w-full"></audio>
                                            @if (($message->meta['seconds'] ?? 0) > 0)<span class="text-xs opacity-80">{{ gmdate($message->meta['seconds'] >= 3600 ? 'H:i:s' : 'i:s', (int) $message->meta['seconds']) }}</span>@endif
                                        </div>
                                    @elseif (str_starts_with((string) $asset->mime_type, 'image/'))
                                        <a href="{{ $src }}" target="_blank" rel="noopener" class="mt-1 block"><img src="{{ $src }}" alt="{{ $asset->alt_text ?? $asset->original_filename }}" loading="lazy" decoding="async" class="max-h-72 w-auto max-w-full rounded-lg object-contain"></a>
                                    @elseif (str_starts_with((string) $asset->mime_type, 'video/'))
                                        <video controls playsinline preload="metadata" src="{{ $src }}" class="mt-1 max-h-72 w-full max-w-full rounded-lg"></video>
                                    @else
                                        <a href="{{ $src }}?download=1" class="mt-1 flex min-w-0 items-center gap-2 rounded-lg bg-black/5 px-2 py-1.5 text-sm underline-offset-2 hover:underline dark:bg-white/10">
                                            <flux:icon.paper-clip class="size-4 shrink-0" />
                                            <span class="min-w-0 truncate" dir="auto">{{ $asset->original_filename }}</span>
                                        </a>
                                    @endif
                                @endforeach
                                @foreach ($message->evidenceReferences as $reference)
                                    <a href="{{ route('content-evidence.show', $reference) }}" class="mt-1 flex min-w-0 items-center gap-2 rounded-lg bg-black/5 px-2 py-1.5 text-sm underline-offset-2 hover:underline dark:bg-white/10" data-evidence-reference>
                                        <flux:icon.document-text class="size-4 shrink-0" />
                                        <span class="min-w-0 truncate" dir="auto">{{ $reference->revision?->title ?: $reference->content?->activeRevision?->title ?: __('ui.content.untitled') }}</span>
                                    </a>
                                @endforeach
                            @endif
                        </div>

                        <div @class(['flex flex-wrap items-center gap-1 px-1 text-[11px] text-zinc-500', 'flex-row-reverse' => $mine])>
                            <x-app.local-time :value="$message->created_at" />
                            @if ($message->isEdited() && ! $deleted)<span>· {{ __('messages.edited') }}</span>@endif
                            @if ($mine && $other && ! $deleted && $otherRead >= $message->id && $loop->last)<span>· {{ __('messages.seen') }}</span>@endif
                            @foreach ($reactions as $glyph => $group)
                                <button type="button" wire:click="react({{ $message->id }}, {{ \Illuminate\Support\Js::from($glyph) }})" @class([
                                    'inline-flex min-h-7 items-center gap-0.5 rounded-full border px-1.5 text-xs',
                                    'border-zinc-900 bg-zinc-100 dark:border-white dark:bg-zinc-700' => $group->contains('actor_id', $meId),
                                    'border-zinc-200 dark:border-zinc-700' => ! $group->contains('actor_id', $meId),
                                ]) title="{{ $group->count() }}">{{ $glyph }} {{ $group->count() > 1 ? $group->count() : '' }}</button>
                            @endforeach
                            @if (! $deleted)
                                <flux:dropdown position="bottom" :align="$mine ? 'end' : 'start'">
                                    <button type="button" class="inline-flex size-7 items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100 focus:opacity-100 dark:hover:bg-zinc-800 lg:opacity-0 lg:group-hover:opacity-100" aria-label="{{ __('messages.actions') }}" data-message-actions>
                                        <flux:icon.ellipsis-horizontal class="size-4" />
                                    </button>
                                    <flux:menu>
                                        @if ($canWrite)
                                            <div class="flex flex-wrap gap-1 px-1 pb-1">
                                                @foreach ($emoji as $glyph)
                                                    <button type="button" wire:click="react({{ $message->id }}, {{ \Illuminate\Support\Js::from($glyph) }})" class="flex size-9 items-center justify-center rounded-lg text-lg hover:bg-zinc-100 dark:hover:bg-zinc-700">{{ $glyph }}</button>
                                                @endforeach
                                            </div>
                                            <flux:menu.item wire:click="reply({{ $message->id }})" icon="arrow-uturn-left">{{ __('messages.reply') }}</flux:menu.item>
                                        @endif
                                        @if ($mine && $message->kind === 'text')
                                            <flux:menu.item wire:click="startEdit({{ $message->id }})" icon="pencil">{{ __('messages.edit') }}</flux:menu.item>
                                        @endif
                                        @if ($mine)
                                            <flux:menu.item wire:click="deleteMessage({{ $message->id }})" wire:confirm="{{ __('messages.delete_confirm') }}" icon="trash" variant="danger">{{ __('messages.delete') }}</flux:menu.item>
                                        @else
                                            <flux:menu.item wire:click="openReport({{ $message->id }})" icon="flag">{{ __('messages.report.message') }}</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Composer --}}
    <div class="border-t border-zinc-200 p-2 sm:p-3 dark:border-zinc-700">
        @if ($notice)
            <div class="mb-2 rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-800" role="status">{{ $notice }}</div>
        @endif
        @if (! $canWrite)
            <flux:text class="py-2 text-center text-sm">{{ $iBlocked ? __('messages.you_blocked') : __('messages.cannot_write') }}</flux:text>
        @else
            @if ($replyMessage)
                <div class="mb-2 flex items-center gap-2 rounded-lg bg-zinc-100 px-3 py-1.5 text-sm dark:bg-zinc-800">
                    <flux:icon.arrow-uturn-left class="size-4 shrink-0 text-zinc-500" />
                    <span class="min-w-0 flex-1 truncate" dir="auto"><span class="font-medium">{{ $replyMessage->author?->user?->username }}:</span> {{ \Illuminate\Support\Str::limit($replyMessage->currentBody() ?: __('messages.kinds_short.'.$replyMessage->kind), 80) }}</span>
                    <button type="button" wire:click="cancelReply" class="inline-flex size-8 items-center justify-center rounded-md hover:bg-zinc-200 dark:hover:bg-zinc-700" aria-label="{{ __('messages.cancel') }}"><flux:icon.x-mark class="size-4" /></button>
                </div>
            @endif
            @if ($files !== [])
                <div class="mb-2 flex flex-wrap gap-2">
                    @foreach ($files as $index => $file)
                        <span class="inline-flex max-w-full items-center gap-1 rounded-full bg-zinc-100 py-1 ps-3 pe-1 text-xs dark:bg-zinc-800">
                            <span class="min-w-0 truncate" dir="auto">{{ $file->getClientOriginalName() }}</span>
                            <button type="button" wire:click="removeFile({{ $index }})" class="inline-flex size-6 items-center justify-center rounded-full hover:bg-zinc-200 dark:hover:bg-zinc-700" aria-label="{{ __('messages.cancel') }}"><flux:icon.x-mark class="size-3.5" /></button>
                        </span>
                    @endforeach
                </div>
            @endif
            @error('files.*') <div class="mb-2 text-sm text-red-600">{{ $message }}</div> @enderror
            @error('voice') <div class="mb-2 text-sm text-red-600">{{ $message }}</div> @enderror
            @if ($showShareContact)
                <div class="mb-2 max-h-48 overflow-y-auto rounded-lg border border-zinc-200 p-2 dark:border-zinc-700" data-share-contact>
                    @forelse ($myContacts as $contact)
                        <button type="button" wire:click="shareContact({{ $contact->id }})" class="flex w-full min-w-0 items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            <x-app.actor-avatar :actor="$contact" size="xs" /><span class="min-w-0 truncate">{{ $contact->user?->username }}</span>
                        </button>
                    @empty
                        <flux:text class="text-sm">{{ __('messages.no_contacts_to_share') }}</flux:text>
                    @endforelse
                </div>
            @endif

            <template x-if="recording">
                <div class="flex items-center gap-3 rounded-xl bg-red-50 px-3 py-2 dark:bg-red-950/40" data-recording>
                    <span class="size-2.5 animate-pulse rounded-full bg-red-600"></span>
                    <span class="flex-1 text-sm">{{ __('messages.composer.recording') }} <span x-text="String(Math.floor(recordSeconds/60)).padStart(2,'0')+':'+String(recordSeconds%60).padStart(2,'0')"></span></span>
                    <flux:button type="button" size="sm" variant="ghost" x-on:click="stopRecording(true)">{{ __('messages.cancel') }}</flux:button>
                    <flux:button type="button" size="sm" variant="primary" x-on:click="finishRecording()">{{ __('messages.composer.send_voice') }}</flux:button>
                </div>
            </template>

            <div x-show="!recording" class="flex items-end gap-1 sm:gap-2">
                <flux:dropdown position="top" align="start">
                    <button type="button" class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('messages.composer.attach') }}">
                        <flux:icon.plus class="size-5" />
                    </button>
                    <flux:menu>
                        <flux:menu.item icon="paper-clip" x-on:click="$refs.files.click()">{{ __('messages.composer.files') }}</flux:menu.item>
                        <flux:menu.item icon="map-pin" x-on:click="shareLocation()">{{ __('messages.composer.location') }}</flux:menu.item>
                        <flux:menu.item icon="user" wire:click="openShareContact">{{ __('messages.composer.contact') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <input type="file" x-ref="files" wire:model="files" multiple class="hidden" accept="image/*,video/*,audio/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                <label class="sr-only" for="composer-{{ $conversation->uuid }}">{{ __('messages.composer.placeholder') }}</label>
                <textarea
                    id="composer-{{ $conversation->uuid }}"
                    wire:model="body"
                    x-on:keydown="sendOnEnter($event)"
                    rows="1"
                    maxlength="4000"
                    dir="auto"
                    placeholder="{{ __('messages.composer.placeholder') }}"
                    class="block max-h-40 min-h-11 min-w-0 flex-1 resize-none rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-base leading-6 text-zinc-900 focus:border-zinc-500 focus:outline-none sm:text-sm dark:border-zinc-600 dark:bg-zinc-900 dark:text-white"
                    x-init="$el.addEventListener('input', () => { $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px' })"
                    data-composer
                ></textarea>
                <button type="button" x-on:click="startRecording()" class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" aria-label="{{ __('messages.composer.voice') }}" data-voice-button>
                    <flux:icon.microphone class="size-5" />
                </button>
                <button type="button" x-on:click="send()" wire:loading.attr="disabled" wire:target="send,files" class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-white disabled:opacity-50 dark:bg-white dark:text-zinc-900" aria-label="{{ __('messages.composer.send') }}" data-send>
                    <flux:icon.paper-airplane class="size-5 rtl:-scale-x-100" />
                </button>
            </div>
            <div wire:loading wire:target="files" class="mt-1 text-xs text-zinc-500">{{ __('messages.composer.uploading') }}</div>
            <div x-show="locating" class="mt-1 text-xs text-zinc-500">{{ __('messages.composer.locating') }}</div>
        @endif
    </div>
</div>
