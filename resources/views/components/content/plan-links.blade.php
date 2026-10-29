@props(['content'])
@php
    // Plans (and days) this Content is linked to, only those the reader may open. A link grants nothing by itself.
    $user = auth()->user();
    $links = $user ? \App\Models\PlanContentLink::query()->where('space_content_id', $content->id)->with(['plan', 'occurrence'])->limit(20)->get()
        ->filter(fn ($link) => \Illuminate\Support\Facades\Gate::forUser($user)->allows('view', $link->plan)) : collect();
@endphp
@if ($links->isNotEmpty())
    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm" style="color: var(--content-muted)" data-content-plan-links>
        <span>{{ __('plan_content.related_plan') }}:</span>
        @foreach ($links as $link)
            <a class="underline underline-offset-2" href="{{ route('planner.show', $link->plan) }}{{ $link->occurrence ? '#occurrence-'.$link->occurrence->uuid : '' }}" dir="auto">{{ $link->plan->title }}@if ($link->occurrence) · <x-app.local-date :value="$link->occurrence->local_date" />@endif</a>
        @endforeach
    </div>
@endif
