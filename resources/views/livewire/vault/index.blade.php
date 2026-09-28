<section class="mx-auto max-w-5xl space-y-6">
    <x-app.page-header :title="__('vault.title')" :description="__('vault.help')" />

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
        {{ __('vault.encryption_note') }}
    </div>

    <details class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <summary class="cursor-pointer list-none px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-950/50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="font-semibold">{{ __('vault.add_title') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('vault.add_help') }}</div>
                </div>
                <span class="text-zinc-400">⌄</span>
            </div>
        </summary>

        <form wire:submit="save" class="grid gap-4 border-t border-zinc-200 p-5 dark:border-zinc-800 sm:grid-cols-2">
            <flux:select wire:model="kind" :label="__('vault.kind')">
                @foreach (['login', 'email', 'phone', 'account', 'note'] as $option)
                    <option value="{{ $option }}">{{ __('vault.kinds.'.$option) }}</option>
                @endforeach
            </flux:select>

            <flux:input wire:model="title" :label="__('vault.item_title')" maxlength="180" />

            <flux:input
                wire:model="identifier"
                :label="__('vault.identifier')"
                :placeholder="__('vault.identifier_placeholder')"
                autocomplete="off"
            />

            <flux:input
                wire:model="secret"
                type="password"
                :label="__('vault.secret')"
                :placeholder="__('vault.secret_placeholder')"
                autocomplete="new-password"
            />

            <div class="sm:col-span-2">
                <flux:input wire:model="url" :label="__('vault.url')" placeholder="https://…" />
            </div>

            <div class="sm:col-span-2">
                <flux:textarea wire:model="notes" :label="__('vault.notes')" rows="3" />
            </div>

            <div class="flex justify-end sm:col-span-2">
                <flux:button type="submit" variant="primary">{{ __('vault.save') }}</flux:button>
            </div>
        </form>
    </details>

    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
            <div>
                <h2 class="font-semibold">{{ __('vault.table_title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('vault.table_help') }}</p>
            </div>
            <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                {{ $items->count() }}
            </span>
        </div>

        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @forelse ($items as $item)
                @php($isRevealed = in_array($item->id, $revealed, true))
                <article class="p-4 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-800">
                                    {{ __('vault.kinds.'.$item->kind) }}
                                </span>
                                <h3 class="font-semibold" dir="auto">{{ $item->title }}</h3>
                            </div>

                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                type="button"
                                wire:click="toggleReveal({{ $item->id }})"
                                class="rounded-lg px-3 py-2 text-xs font-semibold text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                {{ $isRevealed ? __('vault.hide') : __('vault.reveal') }}
                            </button>
                            <button
                                type="button"
                                wire:click="delete({{ $item->id }})"
                                wire:confirm="{{ __('vault.delete_confirm') }}"
                                class="inline-flex size-8 items-center justify-center rounded-lg text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"
                                aria-label="{{ __('vault.delete') }}"
                                title="{{ __('vault.delete') }}"
                            >
                                ×
                            </button>
                        </div>
                    </div>

                    @if ($isRevealed)
                        <div class="mt-4 grid gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60 sm:grid-cols-2">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('vault.identifier') }}</div>
                                <div class="mt-1 break-all font-mono text-sm" dir="auto">{{ $item->identifier ?: '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('vault.secret') }}</div>
                                <div class="mt-1 break-all font-mono text-sm" dir="auto">{{ $item->secret ?: '—' }}</div>
                            </div>
                            @if ($item->url)
                                <div class="sm:col-span-2">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('vault.url') }}</div>
                                    <a
                                        href="{{ $item->url }}"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="mt-1 block max-w-xl truncate text-sm underline decoration-zinc-300 underline-offset-4 hover:text-zinc-900 dark:hover:text-white"
                                        dir="ltr"
                                    >
                                        {{ $item->url }}
                                    </a>
                                </div>
                            @endif

                            @if ($item->notes)
                                <div class="sm:col-span-2">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('vault.notes') }}</div>
                                    <div class="mt-1 whitespace-pre-wrap text-sm" dir="auto">{{ $item->notes }}</div>
                                </div>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="px-5 py-12 text-center text-sm text-zinc-500">
                    {{ __('vault.empty') }}
                </div>
            @endforelse
        </div>
    </section>
</section>
