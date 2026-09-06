@php($pageTitle = $title ?? trim($__env->yieldContent('title', 'Dashboard')))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
            {{ $slot ?? '' }}
            @yield('content')
        </x-app.shell>
        @livewireScripts
        @fluxScripts
    </body>
</html>
