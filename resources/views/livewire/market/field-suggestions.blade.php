<section class="mx-auto max-w-3xl space-y-5" data-field-suggestions>
    <header class="space-y-1">
        <flux:heading size="xl">{{ __('composer.suggest.my_title') }}</flux:heading>
        <flux:text>{{ __('composer.suggest.my_help') }}</flux:text>
    </header>
    <ul class="divide-y rounded-2xl border bg-white dark:bg-zinc-900">
        @forelse($suggestions as $suggestion)
            <li class="space-y-1 p-4" wire:key="suggestion-{{ $suggestion->uuid }}" data-suggestion-status="{{ $suggestion->status }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-medium" dir="auto">{{ \App\Support\Market\FieldSuggestionTypes::summary($suggestion) }}</p>
                    <flux:badge size="sm" :color="match ($suggestion->status) { 'published' => 'green', 'approved' => 'blue', 'rejected' => 'red', default => 'zinc' }">{{ __('composer.suggest.status.'.$suggestion->status) }}</flux:badge>
                </div>
                <p class="text-xs text-zinc-500"><x-app.local-datetime :value="$suggestion->created_at" />@if($suggestion->explanation) · <span dir="auto">{{ \Illuminate\Support\Str::limit($suggestion->explanation, 120) }}</span>@endif</p>
                @if($suggestion->review_note)<p class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800/50" dir="auto"><span class="font-medium">{{ __('composer.suggest.reviewer_note') }}:</span> {{ $suggestion->review_note }}</p>@endif
            </li>
        @empty
            <li class="p-6 text-center text-sm text-zinc-600 dark:text-zinc-400">{{ __('composer.suggest.my_empty') }}</li>
        @endforelse
    </ul>
</section>
