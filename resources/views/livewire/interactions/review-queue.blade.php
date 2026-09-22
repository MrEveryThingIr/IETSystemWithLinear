<section class="mx-auto max-w-5xl space-y-6">
    <x-app.page-header
        :title="__('structured_interactions.review_submissions')"
        :description="__('structured_interactions.review_queue_help')"
    >
        <x-slot:actions>
            <flux:button :href="route('contexts.contents.index', $context)" variant="ghost">
                {{ __('structured_interactions.back_to_context') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <div class="space-y-3">
        @forelse ($submissions as $submission)
            @php
                $version = $submission->definitionVersion;
                $finalized = $submission->evaluations->where('status', \App\Models\Evaluation::STATUS_FINALIZED)->count();
                $purposeLabel = __('structured_interactions.purpose.'.$version->purpose_key);
                if ($purposeLabel === 'structured_interactions.purpose.'.$version->purpose_key) {
                    $purposeLabel = __('structured_interactions.purpose.general');
                }
            @endphp
            <article class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950 sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge>{{ $purposeLabel }}</flux:badge>
                            <flux:badge size="sm">{{ __('structured_interactions.status.'.$submission->status) }}</flux:badge>
                            @if ($finalized > 0)
                                <flux:badge size="sm">{{ trans_choice('structured_interactions.evaluations_count', $finalized, ['count' => $finalized]) }}</flux:badge>
                            @endif
                        </div>
                        <h2 class="text-lg font-semibold" dir="auto">{{ $version->title }}</h2>
                        <x-app.actor-identity :actor="$submission->submitter" size="xs" />
                        <div class="text-xs text-zinc-500">
                            {{ __('structured_interactions.attempt', ['number' => $submission->attempt_number]) }}
                            · {{ $submission->submitted_at?->format('Y-m-d H:i') }}
                        </div>
                    </div>
                    <flux:button :href="route('contexts.submissions.show', [$context, $submission])" variant="primary" class="w-full sm:w-auto">
                        {{ __('structured_interactions.review') }}
                    </flux:button>
                </div>
            </article>
        @empty
            <x-app.empty-state :title="__('structured_interactions.no_submissions')" />
        @endforelse
    </div>
</section>
