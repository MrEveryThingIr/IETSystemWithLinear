<section class="mx-auto max-w-6xl space-y-6">
    <x-app.page-header
        :title="__('intents.matches.title')"
        :description="__('intents.matches.help')"
    >
        <x-slot:actions>
            <flux:button :href="route('intents.index')" variant="ghost">
                {{ __('intents.matches.back') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:card class="space-y-2">
        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
            {{ __('intents.matches.source') }}
        </div>
        <div class="text-lg font-semibold" dir="auto">
            {{ $intent->title ?: $intent->concept->displayLabel() }}
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:badge :color="$intent->kind->value === 'need' ? 'amber' : 'green'">
                {{ __('intents.kinds.'.$intent->kind->value) }}
            </flux:badge>
            <flux:badge>{{ $intent->concept->displayLabel() }}</flux:badge>
            <flux:badge color="zinc">{{ __('intents.subjects.'.$intent->subject_kind->value) }}</flux:badge>
        </div>
    </flux:card>

    <flux:callout>
        {{ __('intents.matches.boundary') }}
    </flux:callout>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($matches as $match)
            @php($candidate = $match->intent)
            <article class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap gap-2">
                            <flux:badge :color="$candidate->kind->value === 'need' ? 'amber' : 'green'">
                                {{ __('intents.kinds.'.$candidate->kind->value) }}
                            </flux:badge>
                            <flux:badge color="zinc">
                                {{ trans_choice('intents.matches.aligned_count', $match->alignedDimensions, ['count' => $match->alignedDimensions]) }}
                            </flux:badge>
                        </div>
                        <h2 class="mt-3 text-lg font-semibold" dir="auto">
                            {{ $candidate->title ?: $candidate->concept->displayLabel() }}
                        </h2>
                        @if ($candidate->description)
                            <p class="mt-2 line-clamp-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300" dir="auto">
                                {{ $candidate->description }}
                            </p>
                        @endif
                    </div>
                </div>

                <div class="mt-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                        {{ __('intents.matches.why') }}
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($match->reasons as $reason)
                            <flux:badge color="zinc">{{ __('intents.matches.reasons.'.$reason) }}</flux:badge>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    @can('view', $candidate->profile)
                        <a href="{{ route('profiles.show', $candidate->profile) }}" class="text-sm font-medium hover:underline">
                            {{ $candidate->profile->display_name ?: ($candidate->profile->actor->user?->username ?? __('intents.matches.participant')) }}
                        </a>
                    @else
                        <div class="text-sm text-zinc-500">
                            {{ __('intents.matches.private_participant') }}
                        </div>
                    @endcan
                </div>

                <div class="mt-4 flex justify-end">
                    <flux:button
                        :href="route('relationships.create', [
                            'intent' => $intent->uuid,
                            'match' => $candidate->uuid,
                        ])"
                        variant="primary"
                        size="sm"
                    >
                        {{ __('intents.matches.start_relationship') }}
                    </flux:button>
                </div>
            </article>
        @empty
            <div class="lg:col-span-2">
                <x-app.empty-state
                    :title="__('intents.matches.none')"
                    :description="__('intents.matches.none_help')"
                />
            </div>
        @endforelse
    </div>
</section>
