@extends('layouts.app')

@section('title', $asset->original_filename)

@section('content')
    @php
        $attacherName = $addedBy?->profile?->display_name ?: $addedBy?->user?->username;
    @endphp

    <article class="mx-auto max-w-3xl space-y-6" data-plan-evidence="{{ $asset->uuid }}">
        <a href="{{ route('planner.show', $plan) }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
            <flux:icon name="arrow-left" class="size-4 rtl:rotate-180" />
            <span dir="auto">{{ $plan->title }}</span>
        </a>

        <header class="space-y-2">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('media.evidence.kicker') }}</div>
            <h1 class="break-all text-2xl font-semibold" dir="auto">{{ $asset->original_filename }}</h1>
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                <span dir="auto">{{ $plan->title }}</span>
                <span>·</span>
                <x-app.local-datetime :value="$occurrence->scheduled_start_at" />
            </div>
        </header>

        <x-media.asset-preview :asset="$asset" :inline-url="$inlineUrl" :download-url="$downloadUrl" />

        <div class="flex flex-wrap gap-2">
            @if (in_array($asset->mediaKind(), ['image', 'pdf', 'video', 'audio'], true))
                <flux:button :href="$inlineUrl" target="_blank" variant="ghost" icon="arrow-top-right-on-square">{{ __('media.open_file') }}</flux:button>
            @endif
            <flux:button :href="$downloadUrl" variant="primary" icon="arrow-down-tray">{{ __('media.download') }}</flux:button>
        </div>

        <dl class="grid gap-x-6 gap-y-3 rounded-xl border border-zinc-200 p-4 text-sm sm:grid-cols-2 dark:border-zinc-800">
            <div>
                <dt class="text-xs text-zinc-500">{{ __('media.evidence.attached_by') }}</dt>
                <dd dir="auto">{{ $attacherName ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-zinc-500">{{ __('media.evidence.attached_at') }}</dt>
                <dd>@if ($attachedAt)<x-app.local-datetime :value="$attachedAt" />@else — @endif</dd>
            </div>
            <div>
                <dt class="text-xs text-zinc-500">{{ __('media.rights_status') }}</dt>
                <dd>{{ __('media.rights.'.$asset->rights_status) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-zinc-500">{{ __('media.evidence.file') }}</dt>
                <dd>{{ $asset->mime_type }} · {{ \Illuminate\Support\Number::fileSize((int) $asset->byte_size, precision: 1) }}</dd>
            </div>
        </dl>

        <details class="rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-800">
            <summary class="cursor-pointer font-medium">{{ __('media.evidence.provenance') }}</summary>
            <dl class="mt-3 space-y-2 text-xs">
                <div>
                    <dt class="text-zinc-500">SHA-256</dt>
                    <dd class="break-all font-mono">{{ $asset->sha256 }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">{{ __('media.evidence.asset_id') }}</dt>
                    <dd class="break-all font-mono">{{ $asset->uuid }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">{{ __('media.evidence.occurrence_id') }}</dt>
                    <dd class="break-all font-mono">{{ $occurrence->uuid }}</dd>
                </div>
            </dl>
        </details>
    </article>
@endsection
