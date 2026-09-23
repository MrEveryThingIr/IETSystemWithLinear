<section class="mx-auto max-w-6xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header
        :title="$currentRevision->title"
        :description="$content->blueprintVersion?->blueprint?->name ?? $content->definition->name"
    >
        <x-slot:actions>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <flux:button :href="route('contexts.contents.index', $context)" variant="ghost">
                    {{ __('ui.context_content.back') }}
                </flux:button>
                @if ($canUpdate)
                    @if (config('ai.enabled'))
                        <flux:button :href="route('contexts.contents.ai', [$context, $content])" variant="ghost" icon="sparkles">{{ __('ai.title') }}</flux:button>
                    @endif
                    <flux:button :href="route('contexts.contents.blocks', [$context, $content])" variant="ghost">{{ __('blocks.title') }}</flux:button>
                    <flux:button :href="route('contexts.contents.appearance', [$context, $content])" variant="ghost">{{ __('presentation.title') }}</flux:button>
                    <flux:button :href="route('contexts.contents.outline', [$context, $content])" variant="ghost">{{ __('structure.title') }}</flux:button>
                @endif
                @if ($canOpenReader)
                    <flux:button :href="route('contexts.contents.show', [$context, $content])" variant="ghost">
                        {{ __('ui.context_content.open_reader') }}
                    </flux:button>
                @endif
            </div>
        </x-slot:actions>
    </x-app.page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="min-w-0 space-y-6">
            <flux:card class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ __('studio.document') }}</flux:heading>
                        <flux:text>{{ $content->draft_revision_id ? __('studio.private_draft') : __('studio.live_edition') }}</flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:badge>{{ __('ui.content.status_'.$content->status) }}</flux:badge>
                        <flux:badge>{{ __('ui.content.revision_number', ['revision' => $currentRevision->revision]) }}</flux:badge>
                        @if ($content->blueprintVersion)
                            <flux:badge>{{ __('ui.context_content.blueprint_version', ['version' => $content->blueprintVersion->version]) }}</flux:badge>
                        @endif
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
                            <flux:button type="submit" variant="primary">{{ __('studio.save_document') }}</flux:button>
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

            <flux:card class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('studio.media') }}</flux:heading>
                    <flux:text>{{ __('media.preview_help') }}</flux:text>
                </div>

                <div class="space-y-4">
                    @forelse ($mediaAssets as $asset)
                        @php
                            $assetUrl = route('contexts.contents.assets.show', [$context, $content, $asset]);
                            $downloadUrl = route('contexts.contents.assets.download', [$context, $content, $asset]);
                            $caption = $asset->pivot->caption;
                            $canRetry = in_array($asset->scan_status, ['failed', 'quarantined'], true)
                                || in_array($asset->processing_status, ['failed', 'pending'], true);
                        @endphp
                        <div wire:key="context-studio-asset-{{ $asset->uuid }}" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
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
                                    <a href="{{ $assetUrl }}" target="_blank" rel="noopener" class="block break-all rounded-lg bg-zinc-50 p-4 font-medium dark:bg-zinc-900" dir="auto">{{ $asset->original_filename }} ↗</a>
                            @endswitch

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 space-y-2">
                                    <div class="break-words font-medium" dir="auto">{{ $asset->original_filename }}</div>
                                    @if ($caption)
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400" dir="auto">{{ $caption }}</div>
                                    @endif
                                    <div class="flex flex-wrap gap-2">
                                        <flux:badge>{{ __('media.rights.'.$asset->rights_status) }}</flux:badge>
                                        <flux:badge>{{ $asset->scan_status }}</flux:badge>
                                        <flux:badge>{{ $asset->processing_status }}</flux:badge>
                                    </div>
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

                            @if ($canUpdate)
                                <div class="max-w-sm">
                                    <flux:select wire:change="updateAssetRights({{ $asset->id }}, $event.target.value)" :label="__('media.rights_status')">
                                        @foreach ($rightsStatuses as $status)
                                            <option value="{{ $status }}" @selected($asset->rights_status === $status)>{{ __('media.rights.'.$status) }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            @endif
                        </div>
                    @empty
                        <x-app.empty-state :title="__('media.none')" />
                    @endforelse
                </div>

                @if ($canUpdate)
                    <div class="space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                        <flux:heading>{{ __('media.upload_title') }}</flux:heading>
                        <input type="file" wire:model="assetUpload" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" />
                        @error('assetUpload')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:select wire:model="assetRightsStatus" :label="__('media.rights_status')">
                                @foreach ($rightsStatuses as $status)
                                    <option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="assetCaption" :label="__('media.caption')" maxlength="1000" />
                        </div>
                        <div class="flex justify-end">
                            <flux:button wire:click="attachAsset" wire:loading.attr="disabled" wire:target="assetUpload,attachAsset" variant="primary">
                                {{ __('media.add_to_draft') }}
                            </flux:button>
                        </div>
                    </div>
                @endif
            </flux:card>

            @if ($canViewRevisions)
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('studio.history') }}</flux:heading>
                        <flux:text>{{ __('ui.content.history_help') }}</flux:text>
                    </div>

                    @foreach ($revisions as $revision)
                        <details wire:key="context-history-{{ $revision->uuid }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                            <summary class="cursor-pointer font-medium" dir="auto">
                                {{ __('ui.content.revision_number', ['revision' => $revision->revision]) }} · {{ $revision->title }}
                            </summary>
                            <div class="mt-3 space-y-2 text-sm">
                                <div class="flex flex-wrap items-center gap-2 text-zinc-500">
                                    <x-app.actor-identity :actor="$revision->createdBy" size="xs" />
                                    <span>· {{ $revision->created_at->format('Y-m-d H:i') }}</span>
                                </div>
                                <div class="break-all font-mono text-[11px] text-zinc-500">content: {{ $revision->content_hash }}</div>
                                @if ($revision->manifest_hash)
                                    <div class="break-all font-mono text-[11px] text-zinc-500">manifest v{{ $revision->manifest_version ?? 0 }}: {{ $revision->manifest_hash }}</div>
                                @endif
                                <flux:badge>{{ $revision->evidence_status }}</flux:badge>
                            </div>
                        </details>
                    @endforeach
                </flux:card>
            @endif
        </div>

        <div class="space-y-4 self-start xl:sticky xl:top-6">
            @if ($canPublish)
                <flux:card class="space-y-4">
                    <flux:heading>{{ __('ui.content.publish') }}</flux:heading>
                    @if ($publishBlocked)
                        <flux:callout variant="danger">{{ __('media.publish_blocked_help') }}</flux:callout>
                        @foreach ($publicationIssues as $issue)
                            <div class="text-sm text-red-600" dir="auto">{{ $issue['filename'] }} · {{ $issue['code'] }}</div>
                        @endforeach
                    @endif
                    @error('publish')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                    <flux:button wire:click="publish" variant="primary" class="w-full" :disabled="$publishBlocked">
                        {{ __('ui.content.publish') }}
                    </flux:button>
                </flux:card>
            @endif

            @if ($canArchive || $canRestore)
                <flux:card class="space-y-4">
                    <flux:heading>{{ __('studio.lifecycle') }}</flux:heading>

                    @if ($canArchive)
                        @if (! $showArchiveConfirmation)
                            <flux:button wire:click="beginArchive" variant="ghost" class="w-full">{{ __('studio.archive') }}</flux:button>
                        @else
                            <div class="space-y-3">
                                <flux:textarea wire:model="archiveReason" :label="__('studio.archive_reason')" rows="3" maxlength="1000" />
                                <div class="flex gap-2">
                                    <flux:button wire:click="cancelArchive" variant="ghost" class="flex-1">{{ __('studio.cancel') }}</flux:button>
                                    <flux:button wire:click="archive" variant="danger" class="flex-1">{{ __('studio.confirm_archive') }}</flux:button>
                                </div>
                            </div>
                        @endif
                    @endif

                    @if ($canRestore)
                        <div class="space-y-3">
                            <flux:textarea wire:model="restoreReason" :label="__('studio.restore_reason')" rows="3" maxlength="1000" />
                            <flux:button wire:click="restore" variant="primary" class="w-full">{{ __('studio.restore') }}</flux:button>
                        </div>
                    @endif
                </flux:card>
            @endif

            @if ($canViewRevisions && $lifecycleEvents->isNotEmpty())
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('studio.lifecycle') }}</flux:heading>
                    @foreach ($lifecycleEvents->take(10) as $event)
                        <div class="rounded-lg border border-zinc-200 p-3 text-xs dark:border-zinc-800">
                            <div class="font-medium">{{ __('studio.event_'.$event->event_type) }}</div>
                            <div class="mt-1 text-zinc-500" dir="auto">{{ $event->reason }}</div>
                        </div>
                    @endforeach
                </flux:card>
            @endif
        </div>
    </div>
</section>
