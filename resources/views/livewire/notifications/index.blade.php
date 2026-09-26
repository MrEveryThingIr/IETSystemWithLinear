<section class="mx-auto max-w-5xl space-y-6" wire:poll.15s>
    <x-app.page-header :title="__('notifications.title')" :description="__('notifications.help')">
        <x-slot:actions>
            @if ($unreadCount > 0)
                <flux:button wire:click="markAllRead" variant="ghost">
                    {{ __('notifications.mark_all_read') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>{{ __('notifications.reconnect_boundary') }}</flux:callout>

    <div class="flex flex-wrap gap-2">
        <flux:button wire:click="$set('filter', 'unread')" :variant="$filter === 'unread' ? 'primary' : 'ghost'" size="sm">
            {{ __('notifications.unread') }} ({{ $unreadCount }})
        </flux:button>
        <flux:button wire:click="$set('filter', 'all')" :variant="$filter === 'all' ? 'primary' : 'ghost'" size="sm">
            {{ __('notifications.all') }}
        </flux:button>
    </div>

    <div class="space-y-3">
        @forelse ($notifications as $notification)
            @php
                $data = $notification->data;
                $titleKey = is_string($data['title_key'] ?? null) ? $data['title_key'] : 'notifications.messages.generic_title';
                $bodyKey = is_string($data['body_key'] ?? null) ? $data['body_key'] : null;
                $titleParams = is_array($data['title_params'] ?? null) ? $data['title_params'] : [];
                $bodyParams = is_array($data['body_params'] ?? null) ? $data['body_params'] : [];
            @endphp
            <article
                wire:key="notification-{{ $notification->id }}"
                class="rounded-xl border p-4 {{ $notification->read_at ? 'border-zinc-200 dark:border-zinc-800' : 'border-zinc-400 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900' }}"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if (! $notification->read_at)
                                <flux:badge>{{ __('notifications.new') }}</flux:badge>
                            @endif
                            <span class="font-semibold" dir="auto">{{ __($titleKey, $titleParams) }}</span>
                        </div>
                        @if ($bodyKey)
                            <div class="mt-2 text-sm text-zinc-500" dir="auto">{{ __($bodyKey, $bodyParams) }}</div>
                        @endif
                        @if ($notification->created_at)<div class="mt-2 text-xs text-zinc-500"><x-app.local-datetime :value="$notification->created_at" /></div>@endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if (! $notification->read_at)
                            <flux:button wire:click="markRead('{{ $notification->id }}')" size="sm" variant="ghost">
                                {{ __('notifications.mark_read') }}
                            </flux:button>
                        @endif
                        @if (is_string($data['url'] ?? null))
                            <flux:button wire:click="open('{{ $notification->id }}')" size="sm" variant="primary">
                                {{ __('notifications.open') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <x-app.empty-state :title="__('notifications.empty')" :description="__('notifications.empty_help')" />
        @endforelse
    </div>
</section>
