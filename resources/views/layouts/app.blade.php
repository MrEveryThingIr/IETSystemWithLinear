<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
        @fluxAppearance
    </head>

    <body>
        <main class="mx-auto max-w-lg space-y-6 px-6 py-12">
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        @livewireScripts
        @fluxScripts
    </body>
</html>