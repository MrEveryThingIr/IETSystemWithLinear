<x-public-content.layout :title="__('public_content.index.title')" :description="__('public_content.index.description')" :canonical="route('public-content.index')">
    <main class="mx-auto w-full max-w-6xl space-y-6 px-4 py-8 sm:px-6 sm:py-12" data-public-index>
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight">{{ __('public_content.index.title') }}</h1>
            <p class="text-zinc-600 dark:text-zinc-300">{{ __('public_content.index.description') }}</p>
        </div>

        <form method="GET" action="{{ route('public-content.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" role="search">
            <div class="min-w-0 flex-1">
                <flux:input name="q" :value="$search" :placeholder="__('public_content.index.search')" icon="magnifying-glass" maxlength="100" :aria-label="__('public_content.index.search')" />
            </div>
            <div class="sm:w-48">
                <flux:select name="kind" :aria-label="__('public_content.index.kind')">
                    <option value="">{{ __('public_content.index.all_kinds') }}</option>
                    @foreach ($kinds as $option)
                        <option value="{{ $option }}" @selected($kind === $option)>{{ __('content_workspace.kinds.'.$option.'.name') }}</option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button type="submit" variant="primary">{{ __('public_content.index.apply') }}</flux:button>
        </form>

        @if ($cards === [])
            <div class="rounded-2xl border border-dashed border-zinc-300 p-10 text-center text-zinc-500 dark:border-zinc-700" data-public-empty>
                {{ $search !== '' || $kind !== '' ? __('public_content.index.no_results') : __('public_content.index.empty') }}
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cards as $card)
                    <x-public-content.card :card="$card" />
                @endforeach
            </div>
            <div>{{ $contents->links() }}</div>
        @endif
    </main>
</x-public-content.layout>
