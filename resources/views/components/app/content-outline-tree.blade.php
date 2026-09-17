@props(['items', 'group', 'space', 'depth' => 0])

@if (count($items) > 0)
    <ol class="space-y-2 {{ $depth > 0 ? 'mt-2 border-s border-zinc-200 ps-4 dark:border-zinc-800' : '' }}">
        @foreach ($items as $item)
            <li class="space-y-2" wire:key="outline-{{ $item['relationship_uuid'] }}">
                <div class="flex items-start gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-900/60">
                    <span class="mt-0.5 min-w-6 text-xs tabular-nums text-zinc-400">{{ $loop->iteration }}</span>
                    <div class="min-w-0 flex-1">
                        @if ($item['can_open_current'])
                            <a
                                href="{{ route('groups.spaces.contents.show', [$group, $space, $item['content_uuid']]) }}"
                                class="font-medium text-zinc-900 hover:underline dark:text-zinc-100"
                                dir="auto"
                            >
                                {{ $item['title'] }}
                            </a>
                        @else
                            <div class="font-medium text-zinc-900 dark:text-zinc-100" dir="auto">{{ $item['title'] }}</div>
                        @endif
                        <div class="mt-0.5 text-xs text-zinc-500" dir="auto">{{ $item['author'] }}</div>
                        @unless ($item['can_open_current'])
                            <div class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ __('reader.older_child_edition') }}</div>
                        @endunless
                    </div>
                </div>

                <x-app.content-outline-tree
                    :items="$item['children']"
                    :group="$group"
                    :space="$space"
                    :depth="$depth + 1"
                />
            </li>
        @endforeach
    </ol>
@endif
