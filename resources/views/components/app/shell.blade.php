@props(['title'])

<div {{ $attributes->class('min-h-dvh') }}>
    <x-app.sidebar />
    @unless (config('release.profile') === 'planning_baseline')
        <x-app.ambient-bar />
    @endunless
    <x-app.navbar :title="$title" />
    <flux:main class="min-w-0">
        <main id="main-content" class="mx-auto w-full max-w-7xl space-y-6">
            <x-app.flash-message />
            {{ $slot }}
        </main>
    </flux:main>
</div>
