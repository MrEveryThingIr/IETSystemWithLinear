<section class="mx-auto max-w-5xl space-y-6">
    <x-app.page-header
        :title="__('structured_interactions.review_submission')"
        :description="$submission->definitionVersion->title"
    >
        <x-slot:actions>
            <flux:button :href="route('contexts.submissions.index', $context)" variant="ghost">
                {{ __('structured_interactions.back_to_queue') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('evaluation-status'))
        <flux:callout variant="success">{{ session('evaluation-status') }}</flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge>{{ __('structured_interactions.purpose.'.$submission->definitionVersion->purpose_key) }}</flux:badge>
            <flux:badge size="sm">{{ __('structured_interactions.status.'.$submission->status) }}</flux:badge>
            <span class="text-xs text-zinc-500">{{ __('structured_interactions.attempt', ['number' => $submission->attempt_number]) }}</span>
        </div>
        <x-app.actor-identity :actor="$submission->submitter" size="sm" />

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($submission->definitionVersion->items as $item)
                @php $response = $submission->responses->firstWhere('item_key', $item['key']); @endphp
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500" dir="auto">{{ $item['label'] }}</div>
                    <div class="mt-2 text-sm" dir="auto">
                        @if (! $response)
                            <span class="text-zinc-500">{{ __('structured_interactions.no_response') }}</span>
                        @elseif ($response->asset)
                            <a href="{{ route('contexts.submissions.assets.download', [$context, $submission, $response->asset]) }}" class="font-medium underline underline-offset-4">
                                {{ $response->asset->original_filename }}
                            </a>
                        @elseif ($response->contentEvidenceReference)
                            <a href="{{ route('content-evidence.show', $response->contentEvidenceReference) }}" class="font-medium underline underline-offset-4">
                                {{ __('structured_interactions.open_evidence') }}
                            </a>
                        @elseif (is_array($response->value))
                            {{ implode(', ', $response->value) }}
                        @elseif (is_bool($response->value))
                            {{ $response->value ? __('ui.content.yes') : __('ui.content.no') }}
                        @else
                            <span class="whitespace-pre-wrap">{{ $response->value }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="break-all rounded-lg bg-zinc-50 p-3 font-mono text-xs text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">
            {{ __('structured_interactions.evidence_hash') }}: {{ $submission->evidence_hash }}
        </div>
    </flux:card>

    <flux:card class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('structured_interactions.evaluation') }}</flux:heading>
            <flux:text>{{ __('structured_interactions.evaluation_help') }}</flux:text>
        </div>

        @if (! $evaluation)
            @if ($canEvaluate)
                <flux:button wire:click="startEvaluation" variant="primary" wire:loading.attr="disabled">
                    {{ __('structured_interactions.start_evaluation') }}
                </flux:button>
            @else
                <flux:callout>{{ __('structured_interactions.evaluation_not_available') }}</flux:callout>
            @endif
        @elseif ($evaluation->status === AppModelsEvaluation::STATUS_FINALIZED)
            <flux:callout variant="success">{{ __('structured_interactions.evaluation_finalized') }}</flux:callout>
            @if ($evaluation->score !== null)
                <div class="text-sm">{{ __('structured_interactions.score') }}: <strong>{{ $evaluation->score }}</strong></div>
            @endif
            @if ($evaluation->feedback)
                <div class="whitespace-pre-wrap rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-800" dir="auto">{{ $evaluation->feedback }}</div>
            @endif
            @if (($evaluation->criterion_results ?? []) !== [])
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($evaluation->criterion_results as $result)
                        <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                            <div class="font-medium">{{ $result['key'] }}</div>
                            <div class="mt-1 text-sm">{{ __('structured_interactions.score') }}: {{ $result['score'] }}</div>
                            @if ($result['feedback'])
                                <div class="mt-2 whitespace-pre-wrap text-sm" dir="auto">{{ $result['feedback'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="break-all rounded-lg bg-zinc-50 p-3 font-mono text-xs text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">
                {{ __('structured_interactions.evidence_hash') }}: {{ $evaluation->evidence_hash }}
            </div>
        @else
            @php $config = $submission->definitionVersion->evaluation_config; @endphp

            @if ($config['score_max'] !== null)
                <flux:input wire:model="score" type="number" step="any" min="0" :max="$config['score_max']" :label="__('structured_interactions.score_with_max', ['max' => $config['score_max']])" />
                @error('score')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
            @endif

            @foreach ($config['criteria'] as $criterion)
                <fieldset class="grid gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800 sm:grid-cols-[10rem_1fr]">
                    <legend class="px-1 font-medium" dir="auto">{{ $criterion['label'] }}</legend>
                    <flux:input
                        wire:model="criterionScores.{{ $criterion['key'] }}"
                        type="number"
                        step="any"
                        min="0"
                        :max="$criterion['score_max']"
                        :label="__('structured_interactions.score_with_max', ['max' => $criterion['score_max']])"
                    />
                    <flux:textarea wire:model="criterionFeedback.{{ $criterion['key'] }}" rows="3" :label="__('structured_interactions.feedback')" />
                    @error('criteria.'.$criterion['key'])<div class="text-sm text-red-600 sm:col-span-2">{{ $message }}</div>@enderror
                </fieldset>
            @endforeach

            <flux:textarea wire:model="feedback" rows="6" :label="__('structured_interactions.overall_feedback')" />
            @error('feedback')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
            @error('evaluation')<div class="text-sm text-red-600">{{ $message }}</div>@enderror

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                @if ($canUpdateEvaluation)
                    <flux:button wire:click="saveEvaluation" variant="ghost" class="w-full sm:w-auto" wire:loading.attr="disabled">
                        {{ __('structured_interactions.save_evaluation') }}
                    </flux:button>
                @endif
                @if ($canFinalizeEvaluation)
                    <flux:button wire:click="finalizeEvaluation" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled">
                        {{ __('structured_interactions.finalize_evaluation') }}
                    </flux:button>
                @endif
            </div>
        @endif
    </flux:card>
</section>
