@props([
    'targetType',
    'targetUuid' => null,
    'fieldKey' => null,
    'label' => null,
    'markerUuids' => [],
    'role' => null,
])

@php
    $sectionActions = \App\Support\Content\SectionSemantics::actionsFor($role);
    $itemClass = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start hover:bg-black/5 focus:bg-black/5 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10';
@endphp

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

    {{-- The panel is placed by the reader script (fixed, kept inside the screen; a bottom sheet on phones). --}}
    <details class="relative" data-context-menu>
        <summary
            class="flex min-h-8 min-w-8 cursor-pointer list-none items-center justify-center rounded-full border text-sm font-bold shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 [&::-webkit-details-marker]:hidden"
            style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-muted)"
            aria-label="{{ __('interactions.target_actions') }}"
        >···</summary>

        <div
            class="fixed z-50 w-56 max-w-[calc(100vw-1.5rem)] overflow-y-auto rounded-xl border p-1.5 text-start text-sm shadow-xl max-sm:inset-x-3 max-sm:bottom-3 max-sm:w-auto"
            style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)"
            data-floating-panel
            role="menu"
        >
            @if ($label)
                <div class="truncate px-3 pb-1 pt-1.5 text-xs font-medium" style="color: var(--content-muted)" dir="auto">{{ $label }}</div>
            @endif
            @foreach ($sectionActions['primary'] as $purpose)
                <button type="button" role="menuitem" data-context-purpose="{{ $purpose }}" class="{{ $itemClass }} {{ $loop->first && $role ? 'font-semibold' : '' }}">
                    {{ __('interactions.quick.'.$purpose) }}
                </button>
            @endforeach

            <button type="button" role="menuitem" data-context-select class="{{ $itemClass }}">
                {{ __('interactions.quick.add_to_selection') }}
            </button>

            <details class="mt-1 border-t pt-1" style="border-color: var(--content-border)" data-menu-more>
                <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2 font-medium hover:bg-black/5 focus:outline-none dark:hover:bg-white/10 [&::-webkit-details-marker]:hidden">
                    {{ __('interactions.quick.more') }}
                    <flux:icon name="chevron-down" class="size-4 opacity-60" />
                </summary>
                @foreach ($sectionActions['more'] as $purpose)
                    <button type="button" role="menuitem" data-context-purpose="{{ $purpose }}" class="{{ $itemClass }}">
                        {{ __('interactions.quick.'.$purpose) }}
                    </button>
                @endforeach
            </details>
        </div>
    </details>
</div>
