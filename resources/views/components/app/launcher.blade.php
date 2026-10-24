@php
    $launcherChoices = app(\App\Support\IntentionLauncher::class)->choicesFor(auth()->user(), request()->route()?->getName());
@endphp

{{-- The + launcher: a few plain sentences, each opening one form. Rendered once per page. --}}
@if ($launcherChoices !== [])
    <flux:modal name="intention-launcher" class="w-full max-w-md" data-launcher>
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" level="2">{{ __('launcher.title') }}</flux:heading>
                <flux:text size="sm" class="mt-1">{{ __('launcher.help') }}</flux:text>
            </div>
            <ul class="space-y-2">
                @foreach ($launcherChoices as $choice)
                    <li>
                        <a href="{{ $choice['href'] }}" wire:navigate data-launcher-choice="{{ $choice['key'] }}"
                           class="flex min-h-14 items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 text-start transition hover:border-zinc-400 hover:bg-zinc-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-800 dark:focus-visible:ring-white">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon :name="$choice['icon']" class="size-5 text-zinc-700 dark:text-zinc-200" />
                            </span>
                            <span class="min-w-0">
                                <span class="block font-medium text-zinc-900 dark:text-white">{{ $choice['label'] }}</span>
                                <span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $choice['hint'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </flux:modal>
@endif
