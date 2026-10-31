<x-docs.layout :title="__('docs.restricted.title')" :noindex="true">
    <main class="mx-auto w-full max-w-2xl space-y-4 px-4 py-16 text-center" data-docs-restricted>
        <h1 class="text-2xl font-semibold">{{ __('docs.restricted.title') }}</h1>
        <p class="leading-7 text-zinc-600 dark:text-zinc-300">{{ __('docs.restricted.body') }}</p>
        <flux:button :href="route('login')">{{ __('docs.nav.login') }}</flux:button>
    </main>
</x-docs.layout>
