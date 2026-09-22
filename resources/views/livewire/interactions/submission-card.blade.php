<section class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 sm:p-5">
    @php
        $purposeKey = $version->purpose_key;
        $purposeLabel = __('structured_interactions.purpose.'.$purposeKey);
        if ($purposeLabel === 'structured_interactions.purpose.'.$purposeKey) {
            $purposeLabel = __('structured_interactions.purpose.general');
        }
    @endphp

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <flux:badge>{{ $purposeLabel }}</flux:badge>
                @if ($submission)
                    <flux:badge size="sm">{{ __('structured_interactions.status.'.$submission->status) }}</flux:badge>
                    <span class="text-xs text-zinc-500">{{ __('structured_interactions.attempt', ['number' => $submission->attempt_number]) }}</span>
                @endif
            </div>
            <h2 class="mt-2 text-xl font-semibold" dir="auto">{{ $version->title }}</h2>
            @if ($version->instructions)
                <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-zinc-600 dark:text-zinc-400" dir="auto">{{ $version->instructions }}</p>
            @endif
        </div>
    </div>

    @if (session('submission-status'))
        <div class="mt-4"><flux:callout variant="success">{{ session('submission-status') }}</flux:callout></div>
    @endif

    @if (! $submission)
        <div class="mt-5">
            @if ($canStart)
                <flux:button wire:click="start" wire:loading.attr="disabled" variant="primary" class="w-full sm:w-auto">
                    {{ __('structured_interactions.start.'.$purposeKey) !== 'structured_interactions.start.'.$purposeKey ? __('structured_interactions.start.'.$purposeKey) : __('structured_interactions.start.general') }}
                </flux:button>
            @else
                <flux:callout>{{ __('structured_interactions.not_available') }}</flux:callout>
            @endif
        </div>
    @elseif ($submission->status === AppModelsSubmission::STATUS_DRAFT)
        <form wire:submit="saveDraft" class="mt-5 space-y-5">
            @foreach ($version->items as $item)
                @php
                    $key = $item['key'];
                    $type = $item['type'];
                    $response = $submission->responses->firstWhere('item_key', $key);
                @endphp
                <fieldset class="space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <legend class="px-1 text-sm font-semibold" dir="auto">
                        {{ $item['label'] }}
                        @if ($item['required']) <span class="text-red-600" aria-hidden="true">*</span> @endif
                    </legend>
                    @if ($item['help'])<p class="text-sm text-zinc-500" dir="auto">{{ $item['help'] }}</p>@endif

                    @switch($type)
                        @case('short_text')
                            <flux:input wire:model="answers.{{ $key }}" :placeholder="$item['label']" />
                            @break
                        @case('long_text')
                            <flux:textarea wire:model="answers.{{ $key }}" rows="5" :placeholder="$item['label']" />
                            @break
                        @case('number')
                            <flux:input wire:model="answers.{{ $key }}" type="number" step="any" />
                            @break
                        @case('date')
                            <flux:input wire:model="answers.{{ $key }}" type="date" />
                            @break
                        @case('boolean')
                            <flux:select wire:model="answers.{{ $key }}">
                                <option value="">{{ __('structured_interactions.choose') }}</option>
                                <option value="1">{{ __('ui.content.yes') }}</option>
                                <option value="0">{{ __('ui.content.no') }}</option>
                            </flux:select>
                            @break
                        @case('single_choice')
                            <flux:select wire:model="answers.{{ $key }}">
                                <option value="">{{ __('structured_interactions.choose') }}</option>
                                @foreach ($item['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                            </flux:select>
                            @break
                        @case('multiple_choice')
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($item['options'] as $option)
                                    <label class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-800">
                                        <input type="checkbox" wire:model="answers.{{ $key }}" value="{{ $option }}" class="rounded border-zinc-300" />
                                        <span dir="auto">{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @break
                        @case('asset')
                            @if ($response?->asset)
                                <div class="flex items-center justify-between gap-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-900">
                                    <span class="break-all" dir="auto">{{ $response->asset->original_filename }}</span>
                                    <flux:button type="button" wire:click="clearResponse('{{ $key }}')" size="sm" variant="ghost">{{ __('structured_interactions.remove') }}</flux:button>
                                </div>
                            @endif
                            <input type="file" wire:model="assetUploads.{{ $key }}" class="block w-full text-sm" />
                            <flux:select wire:model="assetRights.{{ $key }}" :label="__('media.rights_status')">
                                @foreach (AppModelsAsset::RIGHTS_STATUSES as $status)
                                    <option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>
                                @endforeach
                            </flux:select>
                            @break
                        @case('content_evidence')
                            <flux:input wire:model="evidenceReferences.{{ $key }}" :label="__('structured_interactions.evidence_reference')" :placeholder="__('structured_interactions.evidence_placeholder')" />
                            @if ($response?->contentEvidenceReference)
                                <a href="{{ route('content-evidence.show', $response->contentEvidenceReference) }}" class="text-sm font-medium underline underline-offset-4">{{ __('structured_interactions.open_evidence') }}</a>
                            @endif
                            @break
                    @endswitch

                    @error('responses.'.$key)<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                    @error('assetUploads.'.$key)<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                </fieldset>
            @endforeach

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:button type="submit" variant="ghost" class="w-full sm:w-auto" wire:loading.attr="disabled">
                    {{ __('structured_interactions.save_draft') }}
                </flux:button>
                @if ($canSubmit)
                    <flux:button type="button" wire:click="submit" variant="primary" class="w-full sm:w-auto" wire:loading.attr="disabled">
                        {{ __('structured_interactions.submit.'.$purposeKey) !== 'structured_interactions.submit.'.$purposeKey ? __('structured_interactions.submit.'.$purposeKey) : __('structured_interactions.submit.general') }}
                    </flux:button>
                @endif
            </div>
        </form>
    @else
        <div class="mt-5 space-y-4">
            <flux:callout :variant="$submission->status === AppModelsSubmission::STATUS_SUBMITTED ? 'success' : 'warning'">
                {{ $submission->status === AppModelsSubmission::STATUS_SUBMITTED ? __('structured_interactions.submitted_help') : __('structured_interactions.withdrawn_help') }}
            </flux:callout>

            <div class="space-y-3">
                @foreach ($version->items as $item)
                    @php $response = $submission->responses->firstWhere('item_key', $item['key']); @endphp
                    @continue(! $response)
                    <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500" dir="auto">{{ $item['label'] }}</div>
                        <div class="mt-2 text-sm" dir="auto">
                            @if ($response->asset)
                                <span class="font-medium">{{ $response->asset->original_filename }}</span>
                            @elseif ($response->contentEvidenceReference)
                                <a href="{{ route('content-evidence.show', $response->contentEvidenceReference) }}" class="font-medium underline underline-offset-4">{{ __('structured_interactions.open_evidence') }}</a>
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

            @foreach ($submission->evaluations->where('status', AppModelsEvaluation::STATUS_FINALIZED) as $evaluation)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                    <div class="font-semibold">{{ __('structured_interactions.evaluation') }}</div>
                    @if ($evaluation->score !== null)<div class="mt-2 text-sm">{{ __('structured_interactions.score') }}: {{ $evaluation->score }}</div>@endif
                    @if ($evaluation->feedback)<div class="mt-2 whitespace-pre-wrap text-sm" dir="auto">{{ $evaluation->feedback }}</div>@endif
                </div>
            @endforeach

            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                @if ($canWithdraw)
                    <flux:button wire:click="withdraw" variant="danger" class="w-full sm:w-auto">{{ __('structured_interactions.withdraw') }}</flux:button>
                @endif
                @if ($submission->status === AppModelsSubmission::STATUS_WITHDRAWN && $canStart)
                    <flux:button wire:click="start" variant="primary" class="w-full sm:w-auto">{{ __('structured_interactions.start_another') }}</flux:button>
                @endif
            </div>
        </div>
    @endif
</section>
