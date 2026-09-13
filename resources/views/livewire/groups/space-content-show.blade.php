<section class="space-y-6">
    @php
        $hasDraft = $content->draft_revision_id !== null;
        $hasPublishedEdition = $content->active_revision_id !== null;
    @endphp

    <x-app.page-header :title="$currentRevision->title" :description="$content->definition->name">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('groups.spaces.contents.index', [$group, $space])" variant="ghost">
                    {{ __('ui.content.all_content') }}
                </flux:button>
                @if ($canPublish)
                    <flux:button wire:click="publish" variant="primary" :disabled="$publishBlocked">
                        {{ __('ui.content.publish') }}
                    </flux:button>
                @endif
                @if ($canArchive)
                    <flux:button wire:click="archive" variant="danger">{{ __('ui.content.archive') }}</flux:button>
                @endif
            </div>
        </x-slot:actions>
    </x-app.page-header>

    <x-app.group-space-tabs :group="$group" :current-space="$space" />
    <x-app.space-section-tabs :group="$group" :space="$space" current="content" />

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @error('publish')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if ($canPublish && $publishBlocked)
        <flux:callout>
            <div class="space-y-2">
                <div class="font-medium">{{ __('media.publish_blocked_title') }}</div>
                <div>{{ __('media.publish_blocked_help') }}</div>
                <ul class="list-disc space-y-1 ps-5 text-sm">
                    @foreach ($blockingMediaAssets as $blockingAsset)
                        <li>{{ $blockingAsset->original_filename }} — {{ __('media.rights.'.$blockingAsset->rights_status) }}</li>
                    @endforeach
                </ul>
            </div>
        </flux:callout>
    @endif

    <flux:card class="space-y-5">
        <div class="space-y-1">
            <flux:heading size="lg">{{ __('workflow.content.where_now') }}</flux:heading>
            <flux:text>{{ __('workflow.content.subtitle') }}</flux:text>
        </div>

        <x-app.workflow-pipeline :steps="[
            ['label' => __('workflow.content.definition'), 'description' => __('workflow.content.definition_help'), 'state' => 'complete'],
            ['label' => __('workflow.content.draft'), 'description' => __('workflow.content.draft_help'), 'state' => ($hasDraft || $hasPublishedEdition) ? 'complete' : 'current'],
            ['label' => __('workflow.content.review'), 'description' => __('workflow.content.review_help'), 'state' => $hasDraft ? 'current' : ($hasPublishedEdition ? 'complete' : 'upcoming')],
            ['label' => __('workflow.content.publish'), 'description' => __('workflow.content.publish_help'), 'state' => $hasPublishedEdition ? 'complete' : 'upcoming'],
        ]" />

        <div class="flex flex-wrap gap-2">
            <flux:badge>{{ __('ui.content.status_'.$content->status) }}</flux:badge>
            @if ($hasDraft && $canUpdate)
                <flux:badge>{{ __('ui.content.status_draft') }}</flux:badge>
            @endif
            <flux:badge>{{ __('ui.content.revision_number', ['revision' => $currentRevision->revision]) }}</flux:badge>
            <flux:badge>{{ __('ui.content.definition_version', ['version' => $definitionVersion->version]) }}</flux:badge>
        </div>

        @if (! $canUpdate)
            <flux:callout>{{ __('workflow.content.reader_notice') }}</flux:callout>
        @elseif ($hasDraft)
            <flux:callout>{{ __('workflow.content.draft_notice') }}</flux:callout>
        @elseif ($hasPublishedEdition)
            <flux:callout>{{ __('workflow.content.published_notice') }} {{ __('workflow.content.start_next_edition_help') }}</flux:callout>
        @else
            <flux:callout>{{ __('workflow.content.first_draft_notice') }}</flux:callout>
        @endif
    </flux:card>

    <flux:card class="space-y-5">
        <div class="space-y-1">
            <flux:heading size="lg">
                {{ $hasDraft && $canUpdate ? __('workflow.content.draft_preview') : __('workflow.content.published_preview') }}
            </flux:heading>
            @if ($hasDraft && $canUpdate)
                <flux:text>{{ __('workflow.content.draft_notice') }}</flux:text>
            @elseif ($hasPublishedEdition)
                <flux:text>{{ __('workflow.content.published_notice') }}</flux:text>
            @endif
        </div>

        @foreach ($definitionVersion->schema['fields'] as $field)
            @php
                $value = $currentRevision->payload[$field['key']] ?? null;
                $displayValue = $value;
                if ($field['type'] === 'boolean') {
                    $displayValue = $value ? __('ui.content.yes') : __('ui.content.no');
                } elseif ($field['type'] === 'select' && $value !== null) {
                    $match = collect($field['options'])->firstWhere('value', $value);
                    $displayValue = $match['label'] ?? $value;
                } elseif ($value === null || $value === '') {
                    $displayValue = '—';
                }
            @endphp
            <div class="space-y-1 border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-800">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $field['label'] }}</flux:text>
                <div class="whitespace-pre-wrap break-words text-sm text-zinc-900 dark:text-zinc-100">{{ $displayValue }}</div>
            </div>
        @endforeach

        <div class="space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-800">
            <div>
                <flux:heading>{{ __('media.title') }}</flux:heading>
                <flux:text>{{ __('media.preview_help') }}</flux:text>
            </div>

            @forelse ($mediaAssets as $asset)
                @php
                    $assetUrl = route('groups.spaces.contents.assets.show', [$group, $space, $content, $asset]);
                    $downloadUrl = route('groups.spaces.contents.assets.download', [$group, $space, $content, $asset]);
                    $caption = $asset->pivot->caption;
                @endphp
                <div wire:key="content-asset-{{ $asset->id }}" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    @switch($asset->mediaKind())
                        @case('image')
                            <img src="{{ $assetUrl }}" alt="{{ $caption ?: $asset->original_filename }}" class="max-h-[32rem] w-auto rounded-lg object-contain" />
                            @break
                        @case('audio')
                            <audio controls preload="metadata" class="w-full" src="{{ $assetUrl }}"></audio>
                            @break
                        @case('video')
                            <video controls preload="metadata" class="max-h-[36rem] w-full rounded-lg bg-black" src="{{ $assetUrl }}"></video>
                            @break
                        @default
                            <a href="{{ $assetUrl }}" target="_blank" rel="noopener" class="block rounded-lg bg-zinc-50 p-4 text-sm font-medium text-zinc-800 hover:bg-zinc-100 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800">
                                {{ __('media.open_file') }} · {{ $asset->original_filename }}
                            </a>
                    @endswitch

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 space-y-2">
                            <flux:text class="font-medium">{{ $asset->original_filename }}</flux:text>
                            @if ($caption)
                                <flux:text>{{ $caption }}</flux:text>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                <flux:badge>{{ __('media.rights.'.$asset->rights_status) }}</flux:badge>
                                <flux:text class="text-xs">{{ number_format($asset->byte_size / 1024, 1) }} KB</flux:text>
                            </div>

                            @if ($canUpdate && $hasDraft)
                                <div class="max-w-sm">
                                    <flux:select wire:change="updateAssetRights({{ $asset->id }}, $event.target.value)" :label="__('media.rights_status')">
                                        @foreach ($rightsStatuses as $status)
                                            <option value="{{ $status }}" @selected($asset->rights_status === $status)>
                                                {{ __('media.rights.'.$status) }}
                                            </option>
                                        @endforeach
                                    </flux:select>
                                    @error('assetRights.'.$asset->id)
                                        <flux:text class="mt-1 text-sm text-red-600">{{ $message }}</flux:text>
                                    @enderror
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <flux:button :href="$downloadUrl" size="sm" variant="ghost">{{ __('media.download') }}</flux:button>
                            @if ($canUpdate)
                                <flux:button wire:click="removeAsset({{ $asset->id }})" size="sm" variant="danger">
                                    {{ __('media.remove') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <flux:text>{{ __('media.none') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    @if ($canUpdate)
        <flux:card class="space-y-5">
            <div class="space-y-1">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('workflow.content.draft') }}</div>
                <flux:heading size="lg">{{ __('media.draft_title') }}</flux:heading>
                <flux:text>{{ __('media.draft_help') }}</flux:text>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div>
                        <flux:heading>{{ __('media.upload_title') }}</flux:heading>
                        <flux:text>{{ __('media.upload_help') }}</flux:text>
                    </div>

                    <label class="block space-y-2">
                        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('media.choose_file') }}</span>
                        <input type="file" wire:model="assetUpload" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200 dark:file:bg-zinc-800" />
                    </label>
                    @error('assetUpload')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    <flux:select wire:model="assetRightsStatus" :label="__('media.rights_status')">
                        @foreach ($rightsStatuses as $status)
                            <option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>
                        @endforeach
                    </flux:select>
                    <flux:text class="text-xs">{{ __('media.rights_help') }}</flux:text>

                    <flux:input wire:model="assetCaption" :label="__('media.caption')" maxlength="1000" />

                    <div class="flex items-center justify-between gap-3">
                        <flux:text wire:loading wire:target="assetUpload,attachAsset" class="text-xs">{{ __('media.uploading') }}</flux:text>
                        <flux:button wire:click="attachAsset" wire:loading.attr="disabled" wire:target="assetUpload,attachAsset" variant="primary">
                            {{ __('media.add_to_draft') }}
                        </flux:button>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div>
                        <flux:heading>{{ __('media.record_title') }}</flux:heading>
                        <flux:text>{{ __('media.record_help') }}</flux:text>
                    </div>

                    <flux:input wire:model="recordingCaption" :label="__('media.caption')" maxlength="1000" />

                    <div id="content-audio-recorder-{{ $content->id }}" wire:ignore class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-center gap-3">
                            <flux:button id="content-audio-start-{{ $content->id }}" type="button" variant="primary">
                                {{ __('media.start_recording') }}
                            </flux:button>
                            <flux:button id="content-audio-stop-{{ $content->id }}" type="button" variant="danger" class="hidden">
                                {{ __('media.stop_recording') }}
                            </flux:button>
                            <span id="content-audio-recording-{{ $content->id }}" class="hidden text-sm font-medium text-red-600">
                                {{ __('media.recording') }} <span id="content-audio-timer-{{ $content->id }}">00:00</span>
                            </span>
                            <span id="content-audio-uploading-{{ $content->id }}" class="hidden text-sm text-zinc-600 dark:text-zinc-300">
                                {{ __('media.uploading_recording') }}
                            </span>
                        </div>
                        <p id="content-audio-error-{{ $content->id }}" class="mt-3 hidden text-sm text-red-600"></p>
                    </div>

                    <flux:callout>{{ __('media.recording_rights_help') }}</flux:callout>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-5">
            <div class="space-y-1">
                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('workflow.content.next_step') }}</div>
                <flux:heading size="lg">
                    {{ $hasDraft ? __('workflow.content.edit_draft') : __('workflow.content.start_next_edition') }}
                </flux:heading>
                <flux:text>{{ $hasDraft ? __('workflow.content.edit_draft_help') : __('workflow.content.start_next_edition_help') }}</flux:text>
            </div>

            <form wire:submit="saveRevision" class="space-y-4">
                <flux:input wire:model="title" :label="__('ui.content.title')" maxlength="255" />
                @foreach ($definitionVersion->schema['fields'] as $field)
                    @switch($field['type'])
                        @case('short_text')
                            <x-space-content.fields.short-text :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('long_text')
                            <x-space-content.fields.long-text :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('number')
                            <x-space-content.fields.number :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('date')
                            <x-space-content.fields.date :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('boolean')
                            <x-space-content.fields.boolean :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('select')
                            <x-space-content.fields.select :field="$field" :model="'payload.'.$field['key']" />
                            @break
                    @endswitch
                @endforeach
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:text class="text-xs">{{ __('workflow.content.edit_draft_help') }}</flux:text>
                    <flux:button type="submit" variant="primary">{{ __('workflow.content.save_private_revision') }}</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    @if ($canViewRevisions)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('ui.content.history') }}</flux:heading>
                <flux:text>{{ __('ui.content.history_help') }}</flux:text>
            </div>

            @foreach ($revisions as $revision)
                @php
                    $revisionSchema = $revision->definitionVersion->schema['fields'] ?? [];
                @endphp
                <details wire:key="content-revision-{{ $revision->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                    <summary class="cursor-pointer font-medium">
                        {{ __('ui.content.revision_number', ['revision' => $revision->revision]) }} · {{ $revision->title }}
                    </summary>
                    <div class="mt-4 space-y-3">
                        <flux:text class="text-xs">
                            {{ $revision->createdBy->user?->username ?? __('ui.common.unknown_account') }} · {{ $revision->created_at->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') }}
                        </flux:text>
                        @foreach ($revisionSchema as $field)
                            @php
                                $value = $revision->payload[$field['key']] ?? null;
                                if ($field['type'] === 'boolean') {
                                    $value = $value ? __('ui.content.yes') : __('ui.content.no');
                                } elseif ($field['type'] === 'select' && $value !== null) {
                                    $match = collect($field['options'])->firstWhere('value', $value);
                                    $value = $match['label'] ?? $value;
                                } elseif ($value === null || $value === '') {
                                    $value = '—';
                                }
                            @endphp
                            <div>
                                <flux:text class="text-xs font-medium text-zinc-500">{{ $field['label'] }}</flux:text>
                                <div class="whitespace-pre-wrap break-words text-sm">{{ $value }}</div>
                            </div>
                        @endforeach
                        <flux:text class="font-mono text-[11px] text-zinc-500">sha256:{{ $revision->content_hash }}</flux:text>
                    </div>
                </details>
            @endforeach
        </flux:card>
    @endif
</section>

@if ($canUpdate)
    @script
    <script>
        (() => {
            const suffix = @js((string) $content->id);
            const root = document.getElementById(`content-audio-recorder-${suffix}`)

            if (!root || root.dataset.recorderReady === '1') return
            root.dataset.recorderReady = '1'

            const startButton = document.getElementById(`content-audio-start-${suffix}`)
            const stopButton = document.getElementById(`content-audio-stop-${suffix}`)
            const recordingLabel = document.getElementById(`content-audio-recording-${suffix}`)
            const timerLabel = document.getElementById(`content-audio-timer-${suffix}`)
            const uploadingLabel = document.getElementById(`content-audio-uploading-${suffix}`)
            const errorLabel = document.getElementById(`content-audio-error-${suffix}`)

            let recorder = null
            let stream = null
            let chunks = []
            let elapsed = 0
            let timer = null

            const show = (element, visible) => element?.classList.toggle('hidden', !visible)
            const setError = message => {
                if (!errorLabel) return
                errorLabel.textContent = message || ''
                show(errorLabel, Boolean(message))
            }
            const updateTimer = () => {
                if (!timerLabel) return
                const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0')
                const seconds = (elapsed % 60).toString().padStart(2, '0')
                timerLabel.textContent = `${minutes}:${seconds}`
            }
            const setMode = mode => {
                show(startButton, mode === 'idle')
                show(stopButton, mode === 'recording')
                show(recordingLabel, mode === 'recording')
                show(uploadingLabel, mode === 'uploading')
            }
            const stopTracks = () => {
                stream?.getTracks().forEach(track => track.stop())
                stream = null
            }
            const finishTimer = () => {
                clearInterval(timer)
                timer = null
            }

            const uploadRecording = () => {
                const type = recorder?.mimeType || 'audio/webm'
                const extension = type.includes('mp4') ? 'm4a' : (type.includes('ogg') ? 'ogg' : 'webm')
                const blob = new Blob(chunks, { type })

                stopTracks()
                recorder = null
                chunks = []

                if (blob.size === 0) {
                    setMode('idle')
                    setError(@js(__('media.recording_error')))
                    return
                }

                const file = new File([blob], `recording-${Date.now()}.${extension}`, { type })
                setMode('uploading')

                $wire.upload('assetUpload', file, () => {
                    $wire.call('attachRecordedAsset').then(() => {
                        elapsed = 0
                        updateTimer()
                        setMode('idle')
                    }).catch(() => {
                        setMode('idle')
                        setError(@js(__('media.recording_error')))
                    })
                }, () => {
                    setMode('idle')
                    setError(@js(__('media.recording_error')))
                })
            }

            startButton?.addEventListener('click', async () => {
                setError('')

                if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
                    setError(@js(__('media.recorder_unavailable')))
                    return
                }

                try {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: true })
                    const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4']
                    const mimeType = candidates.find(type => MediaRecorder.isTypeSupported(type)) ?? ''
                    recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined)
                    chunks = []
                    elapsed = 0
                    updateTimer()

                    recorder.addEventListener('dataavailable', event => {
                        if (event.data.size > 0) chunks.push(event.data)
                    })
                    recorder.addEventListener('stop', uploadRecording, { once: true })
                    recorder.start()
                    setMode('recording')
                    timer = setInterval(() => {
                        elapsed++
                        updateTimer()
                    }, 1000)
                } catch (error) {
                    finishTimer()
                    stopTracks()
                    recorder = null
                    setMode('idle')
                    setError(@js(__('media.recording_error')))
                }
            })

            stopButton?.addEventListener('click', () => {
                if (!recorder || recorder.state === 'inactive') return
                finishTimer()
                recorder.stop()
            })

            setMode('idle')
            updateTimer()
        })()
    </script>
    @endscript
@endif
