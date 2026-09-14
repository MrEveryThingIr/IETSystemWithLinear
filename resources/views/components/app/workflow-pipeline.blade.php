@props(['steps'])

<ol {{ $attributes->class(['grid gap-3 md:grid-cols-2 xl:grid-cols-4']) }}>
    @foreach ($steps as $step)
        @php
            $state = $step['state'] ?? 'upcoming';
            $containerClasses = match ($state) {
                'complete' => 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900 dark:bg-emerald-950/20',
                'current' => 'border-zinc-400 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900',
                default => 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-white/5',
            };
            $numberClasses = match ($state) {
                'complete' => 'bg-emerald-600 text-white',
                'current' => 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900',
                default => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
            };
        @endphp

        <li
            class="rounded-xl border p-4 {{ $containerClasses }}"
            @if ($state === 'current') aria-current="step" @endif
        >
            <div class="flex items-start gap-3">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $numberClasses }}">
                    {{ $loop->iteration }}
                </span>
                <div class="min-w-0 space-y-1">
                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $step['label'] }}</div>
                    <p class="text-sm leading-5 text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                </div>
            </div>
        </li>
    @endforeach
</ol>
