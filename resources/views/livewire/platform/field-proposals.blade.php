<section class="mx-auto max-w-4xl space-y-6" data-field-review>
    <header class="space-y-2">
        <flux:heading size="xl">{{ __('field_review.title') }}</flux:heading>
        <flux:text>{{ __('field_review.help') }}</flux:text>
    </header>

    <x-app.flash-message />

    <nav class="flex flex-wrap gap-1" aria-label="{{ __('field_review.filter') }}">
        @foreach (['proposed', 'reviewed', 'published', 'rejected', 'library'] as $tab)
            <button type="button" wire:click="$set('status', '{{ $tab }}')" aria-pressed="{{ $status === $tab ? 'true' : 'false' }}"
                class="rounded-full px-3 py-1.5 text-sm {{ $status === $tab ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200' }}">
                {{ __('field_review.status.'.$tab) }}@if($tab !== 'library') <span class="tabular-nums opacity-70">{{ $counts[$tab] ?? 0 }}</span>@endif
            </button>
        @endforeach
    </nav>

    @if ($status === 'library')
        <ul class="divide-y rounded-2xl border bg-white dark:bg-zinc-900" data-field-library>
            @foreach ($definitions as $definition)
                <li class="flex flex-wrap items-center justify-between gap-3 p-4" wire:key="def-{{ $definition->uuid }}">
                    <div class="min-w-0"><span class="font-medium" dir="auto">{{ $definition->labels[app()->getLocale()] ?? $definition->labels['en'] ?? $definition->field_key }}</span>
                        <span class="block text-xs text-zinc-500">{{ $definition->field_key }} · v{{ $definition->version }} · {{ $definition->type }} · {{ $definition->status }}</span></div>
                    @if ($definition->status === 'published')<flux:button size="sm" wire:click="retire('{{ $definition->field_key }}')" wire:confirm="{{ __('field_review.retire_confirm') }}">{{ __('field_review.retire') }}</flux:button>@endif
                </li>
            @endforeach
        </ul>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('field_review.builtin_note', ['count' => count($builtin)]) }}</p>
    @else
        <div class="space-y-3">
            @forelse ($proposals as $proposal)
                @php($payload = $proposal->payload)
                <article wire:key="proposal-{{ $proposal->uuid }}" class="space-y-3 rounded-2xl border bg-white p-4 dark:bg-zinc-900" data-proposal="{{ $proposal->uuid }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="font-semibold [overflow-wrap:anywhere]" dir="auto">{{ collect($payload['labels'] ?? [])->first() }}</div>
                            <div class="text-xs text-zinc-500">{{ $payload['type'] ?? '' }}@if(! empty($payload['unit'])) · {{ $payload['unit'] }}@endif @if(! empty($payload['options'])) · {{ implode(', ', $payload['options']) }}@endif</div>
                            @if ($proposal->review_note)<p class="text-sm text-zinc-500" dir="auto">{{ $proposal->review_note }}</p>@endif
                        </div>
                        @if (in_array($proposal->status, ['proposed', 'reviewed'], true) && $reviewing !== $proposal->uuid)
                            <flux:button size="sm" variant="primary" wire:click="open('{{ $proposal->uuid }}')" data-proposal-review>{{ __('field_review.review') }}</flux:button>
                        @endif
                    </div>
                    @if ($reviewing === $proposal->uuid)
                        <form wire:submit.prevent class="space-y-4 border-t pt-4" data-review-form>
                            @if (count($duplicates))
                                <div class="space-y-2 rounded-xl bg-amber-50 p-3 text-sm dark:bg-amber-950" role="status">
                                    <p class="font-medium">{{ __('field_review.duplicates') }}</p>
                                    @foreach ($duplicates as $key)
                                        <div class="flex flex-wrap items-center justify-between gap-2"><span>{{ \App\Support\Market\IntentDetailCatalog::label($key) }} <span class="text-xs text-zinc-500">({{ $key }})</span></span>
                                            <flux:button size="sm" wire:click="rejectAsDuplicate('{{ $key }}')" data-duplicate-of="{{ $key }}">{{ __('field_review.reject_duplicate') }}</flux:button></div>
                                    @endforeach
                                </div>
                            @endif
                            <flux:input wire:model="form.key" :label="__('field_review.key')" :description="__('field_review.key_help')" dir="ltr" />
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach (\App\Livewire\Platform\FieldProposals::LOCALES as $locale)
                                    <flux:input wire:model="form.labels.{{ $locale }}" :label="__('field_review.label').' · '.$locale" dir="auto" />
                                    <flux:input wire:model="form.aliases.{{ $locale }}" :label="__('field_review.aliases').' · '.$locale" dir="auto" />
                                @endforeach
                            </div>
                            @if (($payload['type'] ?? '') === 'choice')<flux:input wire:model="form.options" :label="__('field_review.options')" dir="auto" />@endif
                            <fieldset><legend class="mb-2 text-sm font-medium">{{ __('field_review.domains') }}</legend>
                                <div class="flex flex-wrap gap-3">@foreach (\App\Support\Market\IntentDetailCatalog::DOMAINS as $domain)<flux:checkbox wire:model="form.domains" value="{{ $domain }}" :label="__('composer.domains.'.$domain)" />@endforeach</div></fieldset>
                            <flux:textarea wire:model="note" :label="__('field_review.note')" rows="2" />
                            @error('form.*')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                            @foreach (['key', 'labels', 'options', 'note'] as $field)@error($field)<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror@endforeach
                            <div class="flex flex-wrap gap-2">
                                <flux:button type="button" variant="primary" wire:click="approve" data-approve>{{ __('field_review.approve') }}</flux:button>
                                <flux:button type="button" wire:click="saveReview">{{ __('field_review.save') }}</flux:button>
                                <flux:button type="button" wire:click="reject" data-reject>{{ __('field_review.reject') }}</flux:button>
                                <flux:button type="button" variant="ghost" wire:click="close">{{ __('field_review.cancel') }}</flux:button>
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <p class="rounded-2xl border p-6 text-center text-sm text-zinc-600 dark:text-zinc-400">{{ __('field_review.empty') }}</p>
            @endforelse
        </div>
    @endif
</section>
