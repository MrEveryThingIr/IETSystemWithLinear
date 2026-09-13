@props(['group', 'currentSpace' => null, 'managing' => false])

@php
    $viewer = auth()->user();
    $groupSpaces = $group->spaces()
        ->where('status', 'active')
        ->orderByDesc('is_default')
        ->orderBy('id')
        ->get();
    $visibleSpaces = $viewer
        ? $groupSpaces->filter(fn ($candidate) => $viewer->can('view', $candidate))
        : collect();
    $showOverview = $viewer?->can('view', $group) ?? false;
    $canManageSpaces = $viewer
        ? ($viewer->can('manageSpaces', $group)
            || $groupSpaces->contains(fn ($candidate) => $viewer->can('manage', $candidate)))
        : false;
@endphp

<div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800">
    <flux:text class="me-2 font-medium">{{ __('ui.spaces.title') }}</flux:text>

    @if ($showOverview)
        <flux:button
            :href="route('groups.show', $group)"
            size="sm"
            :variant="$currentSpace === null && ! $managing ? 'primary' : 'ghost'"
        >
            {{ __('ui.spaces.overview') }}
        </flux:button>
    @endif

    @foreach ($visibleSpaces as $candidate)
        <flux:button
            :href="route('groups.spaces.show', [$group, $candidate])"
            size="sm"
            :variant="$currentSpace?->is($candidate) ? 'primary' : 'ghost'"
        >
            # {{ $candidate->name }}
        </flux:button>
    @endforeach

    @if ($canManageSpaces)
        <flux:button
            :href="route('groups.spaces.manage', $group)"
            size="sm"
            :variant="$managing ? 'primary' : 'ghost'"
            icon="cog-6-tooth"
        >
            {{ __('ui.spaces.manage') }}
        </flux:button>
    @endif
</div>
