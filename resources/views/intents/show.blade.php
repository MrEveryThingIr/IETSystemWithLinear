<x-public-content.layout :title="$presentation['title'] ?: \Illuminate\Support\Str::limit((string) $presentation['description'], 60) ?: __('composer.title')" :noindex="isset($version) || $intent->visibility->value !== 'public'">
    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        @isset($version)<p class="mb-4 rounded-lg border p-3 text-sm" role="note">{{ __('composer.version', ['version' => $version->version]) }} · {{ $version->published_at->locale(app()->getLocale())->isoFormat('LLL') }}</p>@endisset
        <x-market.intention-presentation :presentation="$presentation" />
    </main>
</x-public-content.layout>
