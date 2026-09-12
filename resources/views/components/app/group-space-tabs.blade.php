@props(['group', 'currentSpace' => null])

@php
    $groupSpaces = $group->spaces()
        ->where('status', 'active')
        ->orderByDesc('is_default')
        ->orderBy('id')
        ->get();
@endphp

<div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800">
    <flux:text class="me-2 font-medium">Spaces</flux:text>
    <flux:button
        :href="route('groups.show', $group)"
        size="sm"
        :variant="$currentSpace === null ? 'primary' : 'ghost'"
    >
        Overview
    </flux:button>

    @foreach ($groupSpaces as $candidate)
        <flux:button
            :href="route('groups.spaces.show', [$group, $candidate])"
            size="sm"
            :variant="$currentSpace?->is($candidate) ? 'primary' : 'ghost'"
        >
            # {{ $candidate->name }}
        </flux:button>
    @endforeach
</div>
