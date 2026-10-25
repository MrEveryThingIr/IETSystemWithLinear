@props(['block', 'label', 'locked' => false])
<section {{ $attributes->class('space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/40') }} data-composer-term="{{ $block }}" aria-label="{{ $label }}">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold">{{ $label }}</h3>
        @unless($locked)
            <button type="button" wire:click="removeTerm('{{ $block }}')" wire:confirm="{{ __('composer.remove_term_confirm') }}" class="grid size-9 place-items-center rounded-full text-zinc-500 hover:bg-zinc-200 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-700 dark:hover:text-white" aria-label="{{ __('composer.remove') }}: {{ $label }}">
                <flux:icon.x-mark class="size-4" />
            </button>
        @endunless
    </div>
    {{ $slot }}
</section>
