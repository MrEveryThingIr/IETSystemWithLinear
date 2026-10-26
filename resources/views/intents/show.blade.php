@php
    $viewer = auth()->user();
    $canManage = $viewer !== null && \Illuminate\Support\Facades\Gate::forUser($viewer)->allows('update', $intent);
    $business = $intent->publisherBusiness;
    $group = $intent->publisherGroup;
    $published = $intent->publishedVersion;
@endphp
<x-public-content.layout :title="$presentation['title'] ?: \Illuminate\Support\Str::limit((string) $presentation['description'], 60) ?: __('composer.title')" :noindex="isset($version) || $intent->visibility->value !== 'public'">
    <main class="mx-auto max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        @isset($version)<p class="rounded-lg border p-3 text-sm" role="note">{{ __('composer.version', ['version' => $version->version]) }} · {{ $version->published_at->locale(app()->getLocale())->isoFormat('LLL') }}</p>@endisset
        <x-market.intention-presentation :presentation="$presentation" />
        <footer class="flex flex-col gap-3 border-t pt-4 text-sm sm:flex-row sm:items-center sm:justify-between" data-intention-footer>
            <div class="min-w-0 space-y-0.5 [overflow-wrap:anywhere]">
                @if($business)
                    @if($business->status === 'active' && $business->visibility === 'public')<a href="{{ route('public.businesses.show', $business) }}" class="font-medium hover:underline" dir="auto">{{ $business->name }}</a>@else<span class="font-medium" dir="auto">{{ $business->name }}</span>@endif
                @elseif($group)
                    <span class="font-medium" dir="auto">{{ $group->name }}</span>
                @elseif($viewer && \Illuminate\Support\Facades\Gate::forUser($viewer)->allows('view', $intent->profile))
                    <a href="{{ route('profiles.show', $intent->profile) }}" class="font-medium hover:underline">{{ $intent->profile->display_name ?: __('intents.directory.participant') }}</a>
                @else
                    <span class="text-zinc-500">{{ __('intents.directory.private_participant') }}</span>
                @endif
                @if($canManage && $published)
                    <p class="text-xs text-zinc-500">{{ __('composer.published_state', ['version' => $published->version]) }}@if($intent->working_copy) · {{ __('composer.pending') }}@endif</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if($canManage)
                    <flux:button size="sm" variant="primary" :href="route('intents.compose.edit', $intent)">{{ __('composer.edit_post') }}</flux:button>
                    @if($intent->status === \App\ProfileIntentStatus::Active)<flux:button size="sm" :href="route('intents.matches', $intent)">{{ __('intents.matches.find') }}</flux:button>@endif
                @elseif($viewer)
                    <flux:button size="sm" variant="primary" :href="route('relationships.create', ['intent' => $intent->uuid])">{{ __('deals.market_response') }}</flux:button>
                @else
                    <flux:button size="sm" variant="primary" :href="route('login')">{{ __('composer.sign_in_to_respond') }}</flux:button>
                @endif
            </div>
        </footer>
        @if (! empty($deals) && $deals->isNotEmpty())
            <section class="mt-4 space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-intent-deals>
                <h2 class="text-sm font-semibold">{{ $canManage ? __('deals.on_this_post') : __('deals.your_conversation') }}</h2>
                @foreach ($deals as $row)
                    <a href="{{ route('relationships.show', $row['deal']) }}" class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <span>{{ $row['deal']->title ?: __('deals.open') }}</span>
                        @if ($row['waiting'])<flux:badge size="sm" color="amber">{{ __('deals.waiting_for_you') }}</flux:badge>@endif
                    </a>
                @endforeach
            </section>
        @endif
    </main>
</x-public-content.layout>
