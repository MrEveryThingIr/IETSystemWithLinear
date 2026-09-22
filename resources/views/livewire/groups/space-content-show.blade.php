<section class="mx-auto w-full max-w-7xl space-y-6 pb-12">
    @php
        $hasDraft = $content->draft_revision_id !== null;
    @endphp

    <x-app.page-header :title="$currentRevision->title" :description="__('studio.title')">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('groups.spaces.contents.index', [$group, $space])" variant="ghost">
                    {{ __('ui.content.all_content') }}
                </flux:button>
                @if ($canOpenReader)
                    <flux:button :href="route('groups.spaces.contents.show', [$group, $space, $content])" variant="ghost">
                        {{ __('studio.back_to_reader') }}
                    </flux:button>
                @endif
                @if ($canUpdate)
                    <flux:button :href="route('groups.spaces.contents.outline', [$group, $space, $content])" variant="ghost">
                        {{ __('studio.outline') }}
                    </flux:button>
                @endif
                @if ($canPublish)
                    <flux:button wire:click="publish" wire:loading.attr="disabled" wire:target="publish" variant="primary" :disabled="$publishBlocked">
                        {{ __('ui.content.publish') }}
                    </flux:button>
                @endif
            </div>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @error('publish')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if ($legacyEvidence)
        <flux:callout variant="warning">
            <div class="font-medium">{{ __('studio.legacy_title') }}</div>
            <div class="mt-1 text-sm">{{ __('studio.legacy_help') }}</div>
        </flux:callout>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            <flux:card id="document" class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('studio.document') }}</flux:heading>
                        <flux:text>{{ $hasDraft ? __('studio.private_draft') : __('studio.live_edition') }}</flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:badge>{{ __('ui.content.status_'.$content->status) }}</flux:badge>
                        <flux:badge>{{ __('ui.content.revision_number', ['revision' => $currentRevision->revision]) }}</flux:badge>
                        <flux:badge>{{ __('ui.content.definition_version', ['version' => $definitionVersion->version]) }}</flux:badge>
                    </div>
                </div>

                @if ($canUpdate)
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

                        <div class="flex justify-end">
                            <flux:button type="submit" wire:loading.attr="disabled" wire:target="saveRevision" variant="primary">
                                {{ __('studio.save_document') }}
                            </flux:button>
                        </div>
                    </form>
                @else
                    <div class="space-y-4">
                        @foreach ($definitionVersion->schema['fields'] as $field)
                            @php
                                $value = $currentRevision->payload[$field['key']] ?? null;
                                if ($field['type'] === 'boolean') {
                                    $value = $value ? __('ui.content.yes') : __('ui.content.no');
                                } elseif ($field['type'] === 'select' && $value !== null) {
                                    $match = collect($field['options'])->firstWhere('value', $value);
                                    $value = $match['label'] ?? $value;
                                } elseif ($value === null || $value === '') {
                                    $value = '—';
                                }
                            @endphp
                            <div class="border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-800">
                                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500" dir="auto">{{ $field['label'] }}</div>
                                <div class="mt-1 whitespace-pre-wrap break-words text-sm" dir="auto">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>

            <flux:card id="media" class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('studio.media') }}</flux:heading>
                    <flux:text>{{ __('media.preview_help') }}</flux:text>
                </div>

                <div class="space-y-4">
                    @forelse ($mediaAssets as $asset)
                        @php
                            $assetUrl = route('groups.spaces.contents.assets.show', [$group, $space, $content, $asset]);
                            $downloadUrl = route('groups.spaces.contents.assets.download', [$group, $space, $content, $asset]);
                            $caption = $asset->pivot->caption;
                            $canRetry = in_array($asset->scan_status, ['failed', 'quarantined'], true)
                                || in_array($asset->processing_status, ['failed', 'pending'], true);
                        @endphp
                        <div wire:key="studio-asset-{{ $asset->uuid }}" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            @switch($asset->mediaKind())
                                @case('image')
                                    <img src="{{ $assetUrl }}" alt="{{ $asset->alt_text ?: ($caption ?: $asset->original_filename) }}" class="max-h-80 w-auto rounded-lg object-contain" />
                                    @break
                                @case('audio')
                                    <audio controls preload="metadata" class="w-full" src="{{ $assetUrl }}"></audio>
                                    @break
                                @case('video')
                                    <video controls preload="metadata" class="max-h-96 w-full rounded-lg bg-black" src="{{ $assetUrl }}"></video>
                                    @break
                                @default
                                    <a href="{{ $assetUrl }}" target="_blank" rel="noopener" class="block rounded-lg bg-zinc-50 p-4 font-medium dark:bg-zinc-900" dir="auto">{{ $asset->original_filename }} ↗</a>
                            @endswitch

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 space-y-2">
                                    <div class="font-medium" dir="auto">{{ $asset->original_filename }}</div>
                                    @if ($caption)
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400" dir="auto">{{ $caption }}</div>
                                    @endif
                                    <div class="flex flex-wrap gap-2">
                                        <flux:badge>{{ __('media.rights.'.$asset->rights_status) }}</flux:badge>
                                        <flux:badge>{{ $asset->scan_status }}</flux:badge>
                                        <flux:badge>{{ $asset->processing_status }}</flux:badge>
                                    </div>
                                    @if ($asset->scan_error || $asset->processing_error)
                                        <div class="text-sm text-red-600" dir="auto">{{ $asset->scan_error ?: $asset->processing_error }}</div>
                                    @endif

                                    @if ($canUpdate && $hasDraft)
                                        <div class="max-w-sm">
                                            <flux:select wire:change="updateAssetRights({{ $asset->id }}, $event.target.value)" :label="__('media.rights_status')">
                                                @foreach ($rightsStatuses as $status)
                                                    <option value="{{ $status }}" @selected($asset->rights_status === $status)>{{ __('media.rights.'.$status) }}</option>
                                                @endforeach
                                            </flux:select>
                                            @error('assetRights.'.$asset->id)
                                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <flux:button :href="$downloadUrl" size="sm" variant="ghost">{{ __('media.download') }}</flux:button>
                                    @if ($canUpdate && $canRetry && ! app()->environment(['local', 'testing']))
                                        <flux:button wire:click="retryAssetProcessing({{ $asset->id }})" size="sm" variant="ghost">{{ __('studio.media_retry') }}</flux:button>
                                    @endif
                                    @if ($canUpdate)
                                        <flux:button wire:click="removeAsset({{ $asset->id }})" size="sm" variant="danger">{{ __('media.remove') }}</flux:button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <x-app.empty-state :title="__('media.none')" />
                    @endforelse
                </div>

                @if ($canUpdate)
                    <div class="grid gap-5 border-t border-zinc-200 pt-5 lg:grid-cols-2 dark:border-zinc-800">
                        <div class="space-y-4 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900/60">
                            <div>
                                <flux:heading>{{ __('media.upload_title') }}</flux:heading>
                                <flux:text>{{ __('media.upload_help') }}</flux:text>
                            </div>
                            <input type="file" wire:model="assetUpload" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                            @error('assetUpload')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                            <flux:select wire:model="assetRightsStatus" :label="__('media.rights_status')">
                                @foreach ($rightsStatuses as $status)
                                    <option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="assetCaption" :label="__('media.caption')" maxlength="1000" />
                            <div class="flex justify-end">
                                <flux:button wire:click="attachAsset" wire:loading.attr="disabled" wire:target="assetUpload,attachAsset" variant="primary">{{ __('media.add_to_draft') }}</flux:button>
                            </div>
                        </div>

                        <div class="space-y-4 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900/60">
                            <div>
                                <flux:heading>{{ __('media.record_title') }}</flux:heading>
                                <flux:text>{{ __('media.record_help') }}</flux:text>
                            </div>
                            <flux:input wire:model="recordingCaption" :label="__('media.caption')" maxlength="1000" />
                            <div id="content-audio-recorder-{{ $content->uuid }}" wire:ignore class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                <div class="flex flex-wrap items-center gap-3">
                                    <flux:button id="content-audio-start-{{ $content->uuid }}" type="button" variant="primary">{{ __('media.start_recording') }}</flux:button>
                                    <flux:button id="content-audio-stop-{{ $content->uuid }}" type="button" variant="danger" class="hidden">{{ __('media.stop_recording') }}</flux:button>
                                    <span id="content-audio-recording-{{ $content->uuid }}" class="hidden text-sm font-medium text-red-600">{{ __('media.recording') }} <span id="content-audio-timer-{{ $content->uuid }}">00:00</span></span>
                                    <span id="content-audio-uploading-{{ $content->uuid }}" class="hidden text-sm text-zinc-500">{{ __('media.uploading_recording') }}</span>
                                </div>
                                <p id="content-audio-error-{{ $content->uuid }}" class="mt-3 hidden text-sm text-red-600"></p>
                            </div>
                            <flux:callout>{{ __('media.recording_rights_help') }}</flux:callout>
                        </div>
                    </div>
                @endif
            </flux:card>

            @if ($canViewRevisions)
                <flux:card id="history" class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('studio.history') }}</flux:heading>
                        <flux:text>{{ __('ui.content.history_help') }}</flux:text>
                    </div>

                    @foreach ($revisions as $revision)
                        <details wire:key="history-{{ $revision->uuid }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                            <summary class="cursor-pointer font-medium" dir="auto">
                                {{ __('ui.content.revision_number', ['revision' => $revision->revision]) }} · {{ $revision->title }}
                            </summary>
                            <div class="mt-3 space-y-2 text-sm">
                                <div class="flex flex-wrap items-center gap-2 text-zinc-500"><x-app.actor-identity :actor="$revision->createdBy" size="xs" /><span>· {{ $revision->created_at->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') }}</span></div>
                                <div class="font-mono text-[11px] text-zinc-500">content: {{ $revision->content_hash }}</div>
                                @if ($revision->manifest_hash)
                                    <div class="font-mono text-[11px] text-zinc-500">manifest v{{ $revision->manifest_version ?? 0 }}: {{ $revision->manifest_hash }}</div>
                                @endif
                                <flux:badge>{{ $revision->evidence_status }}</flux:badge>
                            </div>
                        </details>
                    @endforeach
                </flux:card>
            @endif

            @if ($canViewRevisions || $canArchive || $canRestore)
                <flux:card id="lifecycle" class="space-y-5">
                    <div>
                        <flux:heading size="lg">{{ __('studio.lifecycle') }}</flux:heading>
                    </div>

                    @if ($canArchive)
                        @if (! $showArchiveConfirmation)
                            <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                                <div>
                                    <div class="font-medium">{{ __('studio.archive') }}</div>
                                    <div class="mt-1 text-sm text-zinc-500">{{ __('studio.archive_help') }}</div>
                                </div>
                                <flux:button wire:click="beginArchive" variant="ghost">{{ __('studio.archive') }}</flux:button>
                            </div>
                        @else
                            <div class="space-y-3 rounded-xl border border-red-200 bg-red-50/60 p-4 dark:border-red-900 dark:bg-red-950/20">
                                <div class="font-medium text-red-800 dark:text-red-200">{{ __('studio.archive') }}</div>
                                <div class="text-sm text-red-700 dark:text-red-300">{{ __('studio.archive_help') }}</div>
                                <label class="block space-y-1">
                                    <span class="text-sm font-medium">{{ __('studio.archive_reason') }}</span>
                                    <textarea wire:model="archiveReason" rows="3" maxlength="1000" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                                </label>
                                @error('archiveReason')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                                <div class="flex justify-end gap-2">
                                    <flux:button wire:click="cancelArchive" variant="ghost">{{ __('studio.cancel') }}</flux:button>
                                    <flux:button wire:click="archive" wire:loading.attr="disabled" wire:target="archive" variant="danger">{{ __('studio.confirm_archive') }}</flux:button>
                                </div>
                            </div>
                        @endif
                    @endif

                    @if ($canRestore)
                        <div class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                            <div class="font-medium">{{ __('studio.restore') }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('studio.restore_help') }}</div>
                            <label class="block space-y-1">
                                <span class="text-sm font-medium">{{ __('studio.restore_reason') }}</span>
                                <textarea wire:model="restoreReason" rows="3" maxlength="1000" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                            </label>
                            @error('restoreReason')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                            <div class="flex justify-end">
                                <flux:button wire:click="restore" wire:loading.attr="disabled" wire:target="restore" variant="primary">{{ __('studio.restore') }}</flux:button>
                            </div>
                        </div>
                    @endif

                    @if ($canViewRevisions)
                        <div class="space-y-3">
                            @forelse ($lifecycleEvents as $event)
                                <div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-800">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="font-medium">{{ __('studio.event_'.$event->event_type) }}</div>
                                        <div class="text-xs text-zinc-500">{{ $event->created_at->timezone($group->timezone ?: 'UTC')->format('Y-m-d H:i') }}</div>
                                    </div>
                                    <div class="mt-1 text-zinc-600 dark:text-zinc-400" dir="auto">{{ $event->reason }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500"><x-app.actor-identity :actor="$event->actor" size="xs" /><span>· {{ $event->from_status }} → {{ $event->to_status }}</span></div>
                                </div>
                            @empty
                                <flux:text>{{ __('studio.no_lifecycle_events') }}</flux:text>
                            @endforelse
                        </div>
                    @endif
                </flux:card>
            @endif
        </div>

        <aside class="space-y-4 self-start xl:sticky xl:top-6">
            <flux:card id="publication" class="space-y-4">
                <div>
                    <flux:heading>{{ __('studio.publication') }}</flux:heading>
                    <flux:text class="mt-1">{{ $publishBlocked ? __('studio.blocked_help') : __('studio.ready_help') }}</flux:text>
                </div>

                <flux:badge>{{ $publishBlocked ? __('studio.blocked') : __('studio.ready') }}</flux:badge>

                @if ($publicationIssues->isNotEmpty())
                    <ul class="space-y-2 text-sm">
                        @foreach ($publicationIssues as $issue)
                            <li class="rounded-lg bg-amber-50 p-2 dark:bg-amber-950/30">
                                <div class="font-medium" dir="auto">{{ $issue['filename'] }}</div>
                                <div class="text-amber-800 dark:text-amber-200">{{ __('studio.issue.'.$issue['code']) }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canPublish)
                    <flux:button wire:click="publish" wire:loading.attr="disabled" wire:target="publish" class="w-full" variant="primary" :disabled="$publishBlocked">
                        {{ __('ui.content.publish') }}
                    </flux:button>
                @endif
            </flux:card>

            @if ($canUpdate)
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('studio.outline') }}</flux:heading>
                    <flux:button :href="route('groups.spaces.contents.outline', [$group, $space, $content])" class="w-full" variant="ghost">
                        {{ __('studio.outline') }} →
                    </flux:button>
                </flux:card>
            @endif
        </aside>
    </div>
</section>

@if ($canUpdate)
    @script
    <script>
        (() => {
            const suffix = @js((string) $content->uuid);
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
