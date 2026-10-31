@props(['status'])

@php
    $color = ['available' => 'green', 'partial' => 'amber', 'research' => 'violet', 'planned' => 'zinc'][$status] ?? 'zinc';
@endphp

<flux:badge size="sm" :color="$color" data-docs-status="{{ $status }}">{{ __('docs.status.'.$status) }}</flux:badge>
