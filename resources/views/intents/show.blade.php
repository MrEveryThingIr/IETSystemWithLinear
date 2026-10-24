<x-public-content.layout :title="$presentation['title'] ?: \Illuminate\Support\Str::limit((string) $presentation['description'], 60) ?: __('composer.title')" :noindex="$intent->visibility->value !== 'public'">
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <x-market.intention-presentation :presentation="$presentation" />
    </main>
</x-public-content.layout>
