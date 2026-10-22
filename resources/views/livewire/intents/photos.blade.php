<section class="mx-auto max-w-4xl space-y-6">
    <x-app.page-header :title="__('intents.media.title')" :description="__('intents.media.help')">
        <x-slot:actions>
            <flux:button :href="route('profile.edit')" variant="ghost">{{ __('intents.media.back') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:card class="space-y-1">
        <div class="text-lg font-semibold [overflow-wrap:anywhere]" dir="auto">{{ $intent->title ?: $intent->concept->displayLabel() }}</div>
        <flux:text>{{ __('intents.kinds.'.$intent->kind->value) }}</flux:text>
    </flux:card>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @if ($items->isNotEmpty())
        <ul class="grid gap-3 [grid-template-columns:repeat(auto-fill,minmax(min(100%,11rem),1fr))]" data-intent-media>
            @foreach ($items as $index => $item)
                <li wire:key="media-{{ $item->uuid }}" class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="relative aspect-[4/3] bg-zinc-100 dark:bg-zinc-800">
                        @if ($item->asset?->mediaKind() === 'video')
                            <video src="{{ route('intents.media.show', [$intent, $item]) }}" class="h-full w-full object-cover" preload="metadata" controls playsinline></video>
                        @else
                            <img src="{{ route('intents.media.show', [$intent, $item]) }}" alt="{{ $item->caption }}" loading="lazy" decoding="async" class="h-full w-full object-cover">
                        @endif
                        @if ($index === 0)
                            <span class="absolute start-2 top-2 rounded-full bg-zinc-900/80 px-2 py-0.5 text-xs text-white">{{ __('intents.media.cover') }}</span>
                        @endif
                    </div>
                    <div class="space-y-2 p-3">
                        @if ($item->caption)
                            <p class="text-sm [overflow-wrap:anywhere]" dir="auto">{{ $item->caption }}</p>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            @if ($index > 0)
                                <flux:button size="xs" variant="ghost" wire:click="moveUp('{{ $item->uuid }}')">{{ __('intents.media.move_up') }}</flux:button>
                            @endif
                            <flux:button size="xs" variant="danger" wire:click="remove('{{ $item->uuid }}')" wire:confirm="{{ __('intents.media.remove_confirm') }}">{{ __('intents.media.remove') }}</flux:button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <flux:text>{{ __('intents.media.empty') }}</flux:text>
    @endif

    @if ($items->count() < $max)
        <flux:card>
            <form wire:submit="save" class="space-y-4">
                <flux:heading size="lg">{{ __('intents.media.add') }}</flux:heading>
                <div>
                    <label x-data="{ name: '' }" x-on:intent-media-added.window="name = ''" class="flex min-h-11 min-w-0 cursor-pointer flex-wrap items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-4 py-3 text-sm hover:border-zinc-500 dark:border-zinc-600">
                        <input type="file" wire:model="upload" x-on:change="name = $event.target.files[0]?.name ?? ''" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" class="sr-only" data-intent-media-file>
                        <flux:icon.photo class="size-5 shrink-0 text-zinc-500" />
                        <span class="font-medium">{{ __('intents.media.choose') }}</span>
                        <span class="min-w-0 text-zinc-500 [overflow-wrap:anywhere]" dir="auto" x-text="name"></span>
                    </label>
                    <div wire:loading wire:target="upload" class="mt-1 text-sm text-zinc-500">{{ __('intents.media.uploading') }}</div>
                    @error('upload') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <flux:input wire:model="caption" :label="__('intents.media.caption')" maxlength="500" />
                <flux:checkbox wire:model="rights" :label="__('intents.media.rights')" />
                @error('rights') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                <flux:text class="text-xs">{{ __('intents.media.privacy') }}</flux:text>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="upload,save">{{ __('intents.media.save') }}</flux:button>
            </form>
        </flux:card>
    @else
        <flux:callout>{{ __('intents.media.too_many', ['max' => $max]) }}</flux:callout>
    @endif
</section>
