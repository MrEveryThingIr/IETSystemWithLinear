<section class="space-y-6">
    <x-app.page-header :title="$currentRevision->title" :description="$content->definition->name">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('groups.spaces.contents.index', [$group, $space])" variant="ghost">
                    {{ __('ui.content.all_content') }}
                </flux:button>
                @if ($canPublish)
                    <flux:button wire:click="publish" variant="primary">{{ __('ui.content.publish') }}</flux:button>
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

    <div class="flex flex-wrap gap-2">
        <flux:badge>{{ __('ui.content.status_'.$content->status) }}</flux:badge>
        @if ($canUpdate && $content->draft_revision_id !== null)
            <flux:badge>{{ __('ui.content.status_draft') }}</flux:badge>
        @endif
        <flux:badge>{{ __('ui.content.revision_number', ['revision' => $currentRevision->revision]) }}</flux:badge>
        <flux:badge>{{ __('ui.content.definition_version', ['version' => $definitionVersion->version]) }}</flux:badge>
    </div>

    <flux:card class="space-y-5">
        <flux:heading size="lg">{{ __('ui.content.current') }}</flux:heading>

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
    </flux:card>

    @if ($canUpdate)
        <flux:card class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('ui.content.revise') }}</flux:heading>
                <flux:text>{{ __('ui.content.revise_help') }}</flux:text>
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
                <flux:button type="submit" variant="primary">{{ __('ui.content.save_revision') }}</flux:button>
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
