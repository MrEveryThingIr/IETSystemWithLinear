@props(['title', 'viewport' => false])

<div {{ $attributes->class($viewport ? 'min-h-0 min-w-0 flex-1' : 'min-h-dvh') }}
     @if ($viewport) style="grid-template-rows: auto minmax(0, 1fr) auto" @endif>
    <x-app.sidebar />
    <x-app.navbar :title="$title" />
    <flux:main @class(['min-w-0', 'pb-36 lg:pb-8' => ! $viewport, 'flex min-h-0 flex-col !p-2 sm:!p-4 lg:!p-6' => $viewport])>
        <main id="main-content" @class(['mx-auto w-full max-w-7xl', 'space-y-6' => ! $viewport, 'flex min-h-0 flex-1 flex-col' => $viewport])>
            <x-app.flash-message />
            {{ $slot }}
        </main>
    </flux:main>
    @auth
        <x-app.bottom-bar :viewport="$viewport" />
        <x-app.launcher />
    @endauth
</div>
