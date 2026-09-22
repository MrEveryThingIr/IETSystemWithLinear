<section class="space-y-6">
    <x-app.page-header :title="__('ui.profile_sharing.shared_title')" :description="__('ui.profile_sharing.shared_help')" />

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-4 flex items-center gap-4">
            <x-app.actor-avatar :actor="$grant->profile->actor" size="lg" alt="" />
            <p class="min-w-0 text-sm text-zinc-500">{{ __('ui.profile_sharing.shared_help') }}</p>
        </div>
        @if ($grant->purpose)
            <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile_sharing.purpose_label', ['purpose' => $grant->purpose]) }}</p>
        @endif
        <p class="mt-1 text-xs text-zinc-500">
            @if ($grant->expires_at)
                {{ __('ui.profile_sharing.expires_at', ['date' => $grant->expires_at->toDayDateTimeString()]) }}
            @else
                {{ __('ui.profile_sharing.no_expiry') }}
            @endif
        </p>
    </div>

    @if ($disclosure['fields'] !== [])
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($disclosure['fields'] as $field)
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $field['label'] }}</div>
                    @if ($field['key'] === 'website_url')
                        <a href="{{ $field['value'] }}" rel="noopener noreferrer" class="mt-2 block break-all text-sm underline">{{ $field['value'] }}</a>
                    @else
                        <p class="mt-2 whitespace-pre-line text-sm">{{ $field['value'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($disclosure['assertions']->isNotEmpty())
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">{{ __('ui.profile_sharing.semantic') }}</h2>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($disclosure['assertions'] as $assertion)
                    <span class="rounded-full bg-zinc-100 px-3 py-1.5 text-sm dark:bg-zinc-800">
                        {{ $assertion->concept->displayLabel() }}
                        <span class="text-zinc-500">· {{ __('ui.profile.semantics.types.'.$assertion->predicate->value) }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    @if ($disclosure['intents']->isNotEmpty())
        <div class="space-y-3">
            <h2 class="text-lg font-semibold">{{ __('ui.profile_sharing.intents') }}</h2>
            @foreach ($disclosure['intents'] as $intent)
                <article class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $intent->title ?: $intent->concept->displayLabel() }}</span>
                        <flux:badge>{{ __('ui.profile.intents.kinds.'.$intent->kind->value) }}</flux:badge>
                    </div>
                    @if ($intent->description)
                        <p class="mt-2 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $intent->description }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                        @if ($intent->quantity)
                            <span>{{ $intent->quantity }} {{ $intent->unit }}</span>
                        @endif
                        @if ($intent->location_text)
                            <span>{{ $intent->location_text }}</span>
                        @endif
                        @if ($intent->origin_text || $intent->destination_text)
                            <span>{{ $intent->origin_text }} → {{ $intent->destination_text }}</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($disclosure['fields'] === [] && $disclosure['assertions']->isEmpty() && $disclosure['intents']->isEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white p-5 text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900">
            {{ __('ui.profile_sharing.nothing_current') }}
        </div>
    @endif
</section>
