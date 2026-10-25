@php
    $bottomNavigation = app(\App\Services\Surfaces\ExperienceNavigation::class)->for(auth()->user());
    $bottomPrimary = collect($bottomNavigation['primary'])->keyBy('key');
    $bottomItems = collect([
        ['key' => 'today', 'label' => __('home.title'), 'icon' => 'home', 'route' => 'dashboard', 'patterns' => ['dashboard']],
        $bottomPrimary->get('deals') ?? $bottomPrimary->get('market'),
        $bottomPrimary->get('planner'),
        $bottomPrimary->get('messages'),
    ])->filter()->values();
    $hasLauncher = app(\App\Support\IntentionLauncher::class)->choicesFor(auth()->user()) !== [];
@endphp

{{-- Phone navigation: four daily destinations and "More" (the full menu). The + floats above it. --}}
<nav class="fixed inset-x-0 bottom-0 z-[15] border-t border-zinc-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden dark:border-zinc-800 dark:bg-zinc-900/95"
     aria-label="{{ __('ui.navigation.bottom') }}" data-bottom-bar>
    <ul class="mx-auto grid max-w-lg" style="grid-template-columns: repeat({{ $bottomItems->count() + 1 }}, minmax(0, 1fr));">
        @foreach ($bottomItems as $item)
            @php($active = request()->routeIs(...$item['patterns']))
            <li>
                <a href="{{ route($item['route']) }}" wire:navigate data-bottom-item="{{ $item['key'] }}"
                   @if ($active) aria-current="page" @endif
                   @class([
                       'relative flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 text-[11px] font-medium leading-tight',
                       'text-zinc-950 dark:text-white' => $active,
                       'text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200' => ! $active,
                   ])>
                    <flux:icon :name="$item['icon']" :variant="$active ? 'solid' : 'outline'" class="size-6" />
                    <span class="max-w-full truncate">{{ $item['label'] }}</span>
                    @if ($item['key'] === 'messages')
                        <span x-data="messageBadge({ url: {{ \Illuminate\Support\Js::from(route('messages.unread')) }}, count: 0 })" x-show="count > 0" x-cloak x-text="count > 99 ? '99+' : count" class="absolute start-1/2 top-1.5 ms-2 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white"></span>
                    @endif
                </a>
            </li>
        @endforeach
        <li>
            <button type="button" x-data x-on:click="$dispatch('flux-sidebar-toggle')" data-bottom-more
                    class="flex min-h-14 w-full flex-col items-center justify-center gap-0.5 px-1 text-[11px] font-medium leading-tight text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200">
                <flux:icon name="bars-3" class="size-6" />
                <span>{{ __('ui.navigation.more') }}</span>
            </button>
        </li>
    </ul>
</nav>

@if ($hasLauncher)
    <flux:modal.trigger name="intention-launcher">
        <button type="button" data-launcher-fab
                class="fixed bottom-[calc(4.5rem+env(safe-area-inset-bottom))] end-4 z-[15] flex size-14 items-center justify-center rounded-full bg-zinc-900 text-white shadow-lg ring-4 ring-white focus:outline-none focus-visible:ring-zinc-400 lg:hidden dark:bg-white dark:text-zinc-900 dark:ring-zinc-950"
                aria-label="{{ __('launcher.open') }}">
            <flux:icon name="plus" class="size-7" />
        </button>
    </flux:modal.trigger>
@endif
