<section class="mx-auto max-w-5xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header
        :title="__('ui.context_content.title')"
        :description="__('ui.context_content.help')"
    />

    @unless ($canCreate)
        <flux:callout>{{ __('ui.context_content.read_only') }}</flux:callout>
    @endunless

    @if ($canManageDefinitions)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('ui.context_content.local_definition') }}</flux:heading>
                <flux:text>{{ __('ui.context_content.local_definition_help') }}</flux:text>
            </div>

            <form wire:submit="createDefinition" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <flux:input wire:model="definitionName" :label="__('ui.content.definition_name')" maxlength="120" />
                </div>
                <flux:button type="submit" variant="primary" class="w-full sm:w-auto">
                    {{ __('ui.context_content.create_definition') }}
                </flux:button>
            </form>
        </flux:card>
    @endif

    @if ($canCreate)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('ui.content.create') }}</flux:heading>
                <flux:text>{{ __('ui.context_content.create_help') }}</flux:text>
            </div>

            @if ($definitions->isEmpty())
                <flux:callout>{{ __('ui.context_content.no_definitions') }}</flux:callout>
            @else
                <form wire:submit="createContent" class="space-y-4">
                    <div class="space-y-2">
                        <label for="context-definition" class="text-sm font-medium">{{ __('ui.content.definition') }}</label>
                        <select id="context-definition" wire:model="definitionId" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="">{{ __('ui.content.choose_definition') }}</option>
                            @foreach ($definitions as $definition)
                                <option value="{{ $definition->id }}">{{ $definition->name }}</option>
                            @endforeach
                        </select>
                        @error('definitionId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <flux:input wire:model="title" :label="__('ui.content.title')" maxlength="255" />
                    <flux:textarea wire:model="body" :label="__('ui.context_content.body')" rows="8" maxlength="20000" />

                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto">
                        {{ __('ui.content.save_draft') }}
                    </flux:button>
                </form>
            @endif
        </flux:card>
    @endif

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('ui.context_content.contents') }}</flux:heading>
            <flux:text>{{ __('ui.context_content.contents_help') }}</flux:text>
        </div>

        @forelse ($contents as $content)
            @php
                $revision = $content->draftRevision ?? $content->activeRevision;
            @endphp
            <a
                href="{{ route('contexts.contents.show', [$context, $content]) }}"
                class="block rounded-xl border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="break-words font-semibold" dir="auto">{{ $revision?->title ?: __('ui.content.untitled') }}</div>
                        <div class="mt-1 text-sm text-zinc-500">
                            {{ $content->definition->name }} · {{ __('ui.content.status_'.$content->status) }}
                        </div>
                    </div>
                    <x-app.actor-identity :actor="$content->author" size="xs" />
                </div>
            </a>
        @empty
            <flux:text>{{ __('ui.context_content.none') }}</flux:text>
        @endforelse
    </flux:card>
</section>
