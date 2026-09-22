@props([
    'actor',
    'linked' => true,
    'size' => 'sm',
    'showDisplayName' => false,
])

@php
    $actor->loadMissing(['user', 'profile']);
    $username = $actor->user?->username ?? __('ui.spaces.accountless_actor', ['id' => $actor->id]);
    $displayName = $actor->profile?->display_name;
    $contentClass = 'inline-flex min-w-0 items-center gap-2';
@endphp

@if ($linked && $actor->user)
    <a href="{{ route('actors.profile.reference', $actor) }}" {{ $attributes->class($contentClass.' rounded-lg underline-offset-4 hover:underline') }}>
        <x-app.actor-avatar :actor="$actor" :size="$size" :alt="$username" />
        <span class="min-w-0">
            <span class="block truncate font-medium" dir="auto">{{ $username }}</span>
            @if ($showDisplayName && $displayName && $displayName !== $username)
                <span class="block truncate text-xs text-zinc-500" dir="auto">{{ $displayName }}</span>
            @endif
        </span>
    </a>
@else
    <span {{ $attributes->class($contentClass) }}>
        <x-app.actor-avatar :actor="$actor" :size="$size" :alt="$username" />
        <span class="min-w-0">
            <span class="block truncate font-medium" dir="auto">{{ $username }}</span>
            @if ($showDisplayName && $displayName && $displayName !== $username)
                <span class="block truncate text-xs text-zinc-500" dir="auto">{{ $displayName }}</span>
            @endif
        </span>
    </span>
@endif
