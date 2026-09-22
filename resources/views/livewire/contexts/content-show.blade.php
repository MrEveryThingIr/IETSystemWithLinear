<section class="mx-auto max-w-4xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="$revision->title" :description="$content->definition->name">
        <x-slot:actions>
            <flux:button :href="route('contexts.contents.index', $context)" variant="ghost" class="w-full sm:w-auto">
                {{ __('ui.context_content.back') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if ($canUpdate)
        <flux:card class="space-y-4">
            <flux:input wire:model="title" :label="__('ui.content.title')" maxlength="255" />
            <flux:textarea wire:model="body" :label="__('ui.context_content.body')" rows="12" maxlength="20000" />
            <div class="flex flex-col gap-2 sm:flex-row">
                <flux:button wire:click="save" variant="primary" class="w-full sm:w-auto">
                    {{ __('ui.content.save_revision') }}
                </flux:button>
                @if ($canPublish)
                    <flux:button wire:click="publish" class="w-full sm:w-auto">
                        {{ __('ui.content.publish') }}
                    </flux:button>
                @endif
            </div>
        </flux:card>
    @else
        <flux:card>
            <div class="whitespace-pre-wrap break-words leading-7" dir="auto">{{ $body }}</div>
        </flux:card>
    @endif

    <flux:card class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-app.actor-identity :actor="$content->author" size="sm" />
            <flux:badge>{{ __('ui.content.status_'.$content->status) }}</flux:badge>
        </div>
        <flux:text class="text-sm">{{ __('ui.content.revision_number', ['revision' => $revision->revision]) }}</flux:text>
    </flux:card>
</section>
