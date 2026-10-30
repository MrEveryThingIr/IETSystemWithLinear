<div class="flex min-h-0 min-w-0 flex-1 flex-col" data-messages>
    <div class="grid min-h-0 min-w-0 flex-1 grid-rows-[minmax(0,1fr)] overflow-hidden rounded-none border-y border-zinc-200 bg-white sm:rounded-xl sm:border dark:border-zinc-700 dark:bg-zinc-900 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        {{-- Conversation list --}}
        <aside @class(['min-h-0 min-w-0 flex-col border-zinc-200 lg:flex lg:border-e dark:border-zinc-700', 'hidden' => $conversation, 'flex' => ! $conversation]) wire:poll.20s data-inbox>
            <div class="space-y-2 border-b border-zinc-200 p-3 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="lg" level="1">{{ __('messages.title') }}</flux:heading>
                    <flux:button :href="route('contacts.index')" wire:navigate size="sm" variant="ghost" icon="user-plus">{{ __('messages.contacts') }}</flux:button>
                </div>
                <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('messages.search')" class="[&_input]:text-base sm:[&_input]:text-sm" />
                <div class="flex gap-1 text-sm">
                    @foreach (['inbox', 'archived'] as $tab)
                        <button type="button" wire:click="$set('view', '{{ $tab }}')" aria-pressed="{{ $view === $tab ? 'true' : 'false' }}" @class(['min-h-9 rounded-full px-3', 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $view === $tab, 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' => $view !== $tab])>{{ __('messages.views.'.$tab) }}</button>
                    @endforeach
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                @if (trim($search) !== '')
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800" data-search-results>
                        @forelse ($results as $result)
                            <li>
                                <a href="{{ route('messages.index', ['c' => $result->conversation->uuid]) }}#message-{{ $result->uuid }}" wire:navigate class="block min-w-0 px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <span class="block text-xs text-zinc-500">{{ $result->author?->user?->username }} · <x-app.local-date :value="$result->created_at" /></span>
                                    <span class="block truncate text-sm" dir="auto">{{ \Illuminate\Support\Str::limit($result->currentBody(), 120) }}</span>
                                </a>
                            </li>
                        @empty
                            <li class="p-4 text-sm text-zinc-500">{{ __('messages.no_results') }}</li>
                        @endforelse
                    </ul>
                @else
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($rows as $row)
                            @php($selected = $conversation && $conversation->id === $row['conversation']->id)
                            <li wire:key="row-{{ $row['conversation']->uuid }}">
                                <a href="{{ route('messages.index', ['c' => $row['conversation']->uuid, 'view' => $view === 'archived' ? 'archived' : null]) }}" wire:navigate @class(['flex min-w-0 items-center gap-3 px-3 py-2.5', 'bg-zinc-100 dark:bg-zinc-800' => $selected, 'hover:bg-zinc-50 dark:hover:bg-zinc-800/60' => ! $selected]) data-conversation-row>
                                    @if ($row['other'])
                                        <x-app.actor-avatar :actor="$row['other']" size="sm" />
                                    @else
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800"><flux:icon :name="app(\App\Support\ContextPresenter::class)->icon($row['conversation']->context)" class="size-5 text-zinc-500" /></span>
                                    @endif
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center gap-1">
                                            <span @class(['min-w-0 flex-1 truncate', 'font-semibold' => $row['unread'] > 0, 'font-medium' => $row['unread'] === 0]) dir="auto">{{ $row['title'] }}</span>
                                            @if ($row['participant']->pinned_at)<flux:icon.bookmark class="size-3.5 shrink-0 text-zinc-400" />@endif
                                            @if ($row['participant']->isMuted())<flux:icon.bell-slash class="size-3.5 shrink-0 text-zinc-400" />@endif
                                            @if ($row['last'])<span class="shrink-0 text-xs text-zinc-500"><x-app.local-time :value="$row['last']->created_at" /></span>@endif
                                        </span>
                                        <span class="flex items-center gap-2">
                                            <span class="min-w-0 flex-1 truncate text-sm text-zinc-500" dir="auto">
                                                @if ($row['last'])
                                                    @if (! $row['other'] && $row['last']->author?->user)<span class="font-medium">{{ $row['last']->author->user->username }}:</span>@endif
                                                    {{ $row['last']->isDeleted() ? __('messages.deleted') : \Illuminate\Support\Str::limit($row['last']->currentBody() ?: __('messages.kinds_short.'.$row['last']->kind), 70) }}
                                                @else
                                                    {{ __('messages.kinds.'.$row['kind']) }}
                                                @endif
                                            </span>
                                            @if ($row['unread'] > 0)
                                                <span class="inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-zinc-900 px-1.5 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900" aria-label="{{ trans_choice('messages.unread', $row['unread'], ['count' => $row['unread']]) }}">{{ $row['unread'] > 99 ? '99+' : $row['unread'] }}</span>
                                            @endif
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @empty
                            <li class="space-y-3 p-6 text-center text-sm text-zinc-500">
                                <flux:icon.chat-bubble-left-right class="mx-auto size-8" />
                                <p>{{ $view === 'archived' ? __('messages.no_archived') : __('messages.no_conversations') }}</p>
                                @if ($view !== 'archived')
                                    <flux:button :href="route('contacts.index')" wire:navigate size="sm" variant="primary">{{ __('messages.find_people') }}</flux:button>
                                @endif
                            </li>
                        @endforelse
                    </ul>
                @endif
            </div>
        </aside>

        {{-- Open conversation --}}
        <section @class(['min-h-0 min-w-0', 'flex flex-col' => $conversation, 'hidden lg:flex lg:flex-col' => ! $conversation])>
            @if ($conversation)
                <livewire:messages.thread :conversation="$conversation" :key="'thread-'.$conversation->uuid" />
            @else
                <div class="flex h-full flex-col items-center justify-center gap-2 p-8 text-center text-sm text-zinc-500">
                    <flux:icon.chat-bubble-left-right class="size-10" />
                    <p>{{ __('messages.pick_conversation') }}</p>
                    <p class="max-w-sm text-xs">{{ __('messages.privacy_note') }}</p>
                </div>
            @endif
        </section>
    </div>
</div>
