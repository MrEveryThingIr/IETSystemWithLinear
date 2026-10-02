@props([
    'purpose',
    'state',
    'nextAction',
    'audience',
    'consequence',
    'result',
    'steps' => [],
    'currentStep' => null,
    'progressive' => true,
])

@php
    $stepKeys = collect($steps)->keys()->values();
    $currentIndex = $currentStep !== null ? $stepKeys->search($currentStep) : false;
@endphp

<flux:card class="space-y-5">
    <div class="space-y-1">
        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-zinc-500">
            {{ __('workflow.shell_label') }}
        </div>
        <flux:heading size="lg">{{ __('workflow.purpose') }}</flux:heading>
        <flux:text>{{ $purpose }}</flux:text>
    </div>

    @if (count($steps) > 0)
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($steps as $key => $label)
                @php
                    $index = $stepKeys->search($key);
                    $isCurrent = $currentStep === $key;
                    $isPast = $progressive && $currentIndex !== false && $index !== false && $index < $currentIndex;
                @endphp
                <div
                    @class([
                        'rounded-xl border px-3 py-3 text-center text-sm',
                        'border-zinc-900 bg-zinc-900 font-semibold text-white dark:border-white dark:bg-white dark:text-zinc-950' => $isCurrent,
                        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200' => ! $isCurrent && $isPast,
                        'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300' => ! $isCurrent && ! $isPast,
                    ])
                >
                    {{ $label }}
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('workflow.current_state') }}</div>
            <div class="mt-2 font-medium">{{ $state }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('workflow.next_action') }}</div>
            <div class="mt-2 font-medium">{{ $nextAction }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('workflow.audience') }}</div>
            <div class="mt-2 text-sm">{{ $audience }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('workflow.durable_result') }}</div>
            <div class="mt-2 text-sm">{{ $result }}</div>
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            {{ $actions }}
        </div>
    @endisset

    <flux:callout>
        <strong>{{ __('workflow.consequence') }}:</strong>
        {{ $consequence }}
    </flux:callout>

    @isset($help)
        <div class="rounded-xl bg-zinc-50 p-4 text-sm text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
            <div class="font-semibold">{{ __('workflow.contextual_help') }}</div>
            <div class="mt-1">{{ $help }}</div>
        </div>
    @endisset

    @isset($advanced)
        <details class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <summary class="cursor-pointer font-medium">{{ __('workflow.advanced') }}</summary>
            <div class="mt-4">
                {{ $advanced }}
            </div>
        </details>
    @endisset
</flux:card>
