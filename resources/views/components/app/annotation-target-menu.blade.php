@props([
    'targetType',
    'targetUuid' => null,
    'fieldKey' => null,
    'label' => null,
    'markerUuids' => [],
])

<div
    class="relative inline-flex items-center gap-1"
    data-annotation-target-menu
    data-target-type="{{ $targetType }}"
    data-target-uuid="{{ $targetUuid }}"
    data-field-key="{{ $fieldKey }}"
    data-target-label="{{ $label }}"
>
    @if (count($markerUuids) > 0)
        <button
            type="button"
            class="inline-flex min-h-7 min-w-7 items-center justify-center rounded-full border px-2 text-xs font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
            style="border-color: var(--content-accent); color: var(--content-accent); background: color-mix(in srgb, var(--content-accent) 10%, transparent)"
            data-marker-preview='@json(array_values($markerUuids))'
            aria-label="{{ trans_choice('interactions.annotation_count', count($markerUuids), ['count' => count($markerUuids)]) }}"
        >
            {{ count($markerUuids) }}
        </button>
    @endif

    <details class="relative" data-context-menu>
        <summary
            class="flex min-h-8 min-w-8 cursor-pointer list-none items-center justify-center rounded-full border text-sm font-bold shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 [&::-webkit-details-marker]:hidden"
            style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-muted)"
            aria-label="{{ __('interactions.target_actions') }}"
        >···</summary>

        <div
            class="absolute end-0 z-30 mt-2 w-48 rounded-xl border p-1.5 text-start text-sm shadow-xl"
            style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)"
        >
            @foreach ([
                'remember' => __('interactions.quick.remember'),
                'note' => __('interactions.quick.note'),
                'question' => __('interactions.quick.question'),
                'comment' => __('interactions.quick.comment'),
            ] as $purpose => $text)
                <button type="button" data-context-purpose="{{ $purpose }}" class="block w-full rounded-lg px-3 py-2 text-start hover:bg-black/5 focus:bg-black/5 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10">
                    {{ $text }}
                </button>
            @endforeach

            <button type="button" data-context-select class="block w-full rounded-lg px-3 py-2 text-start hover:bg-black/5 focus:bg-black/5 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10">
                {{ __('interactions.quick.add_to_selection') }}
            </button>

            <details class="mt-1 border-t pt-1" style="border-color: var(--content-border)">
                <summary class="cursor-pointer list-none rounded-lg px-3 py-2 font-medium hover:bg-black/5 focus:outline-none dark:hover:bg-white/10 [&::-webkit-details-marker]:hidden">
                    {{ __('interactions.quick.more') }} →
                </summary>
                <div class="ps-2">
                    @foreach ([
                        'translate' => __('interactions.quick.translate'),
                        'file' => __('interactions.quick.file'),
                        'voice' => __('interactions.quick.voice'),
                        'correction' => __('interactions.quick.correction'),
                        'idea' => __('interactions.quick.idea'),
                        'advanced' => __('interactions.quick.advanced'),
                    ] as $purpose => $text)
                        <button type="button" data-context-purpose="{{ $purpose }}" class="block w-full rounded-lg px-3 py-2 text-start hover:bg-black/5 focus:bg-black/5 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10">
                            {{ $text }}
                        </button>
                    @endforeach
                </div>
            </details>
        </div>
    </details>
</div>
