@php
    $pageTitle = $title ?? trim($__env->yieldContent('title', __('ui.dashboard.title')));
    $contentStudioRoute = request()->routeIs(
        'groups.spaces.contents.studio',
        'groups.spaces.contents.blocks',
        'groups.spaces.contents.appearance',
        'groups.spaces.contents.outline',
        'groups.spaces.contents.structure',
    );
    $contentStudioParams = $contentStudioRoute
        ? [request()->route('group'), request()->route('space'), request()->route('content')]
        : [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $pageTitle }} &mdash; {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @fluxAppearance
    </head>
    <body class="min-h-dvh bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <x-app.shell :title="$pageTitle">
            @if ($contentStudioRoute)
                <nav class="mb-5 flex flex-wrap gap-2 rounded-xl border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-800 dark:bg-zinc-950/50" aria-label="Content Studio">
                    <flux:button :href="route('groups.spaces.contents.studio', $contentStudioParams)" size="sm" :variant="request()->routeIs('groups.spaces.contents.studio') ? 'primary' : 'ghost'">
                        {{ __('studio.document') }}
                    </flux:button>
                    <flux:button :href="route('groups.spaces.contents.blocks', $contentStudioParams)" size="sm" :variant="request()->routeIs('groups.spaces.contents.blocks') ? 'primary' : 'ghost'">
                        {{ __('blocks.title') }}
                    </flux:button>
                    <flux:button :href="route('groups.spaces.contents.appearance', $contentStudioParams)" size="sm" :variant="request()->routeIs('groups.spaces.contents.appearance') ? 'primary' : 'ghost'">
                        {{ __('presentation.title') }}
                    </flux:button>
                    <flux:button :href="route('groups.spaces.contents.outline', $contentStudioParams)" size="sm" :variant="request()->routeIs('groups.spaces.contents.outline', 'groups.spaces.contents.structure') ? 'primary' : 'ghost'">
                        {{ __('studio.outline') }}
                    </flux:button>
                </nav>
            @endif

            {{ $slot ?? '' }}
            @yield('content')
        </x-app.shell>
        @livewireScripts
        @fluxScripts
    </body>
</html>
