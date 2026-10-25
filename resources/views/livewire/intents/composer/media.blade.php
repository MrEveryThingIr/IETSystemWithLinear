<div class="space-y-4">
    @foreach($mediaItems as $item)
        <div class="space-y-3 rounded-lg border bg-white p-3 dark:bg-zinc-900" wire:key="composer-media-{{ $item->uuid }}">
            <a class="block break-words underline" href="{{ route('intents.media.show', [$intent, $item]) }}" dir="auto">{{ $item->caption ?: $item->asset?->original_filename }}</a>
            <flux:select wire:change="setMediaVisibility('{{ $item->uuid }}', $event.target.value)" :label="__('composer.detail_audience')"><option value="same" @selected($item->visibility !== 'private')>{{ __('composer.same_audience') }}</option><option value="private" @selected($item->visibility === 'private')>{{ __('composer.only_me') }}</option></flux:select>
            <div class="flex gap-2"><flux:button wire:click="moveMediaUp('{{ $item->uuid }}')" size="sm">{{ __('composer.up') }}</flux:button><flux:button wire:click="removeMedia('{{ $item->uuid }}')" size="sm">{{ __('composer.remove') }}</flux:button></div>
        </div>
    @endforeach
    @if($mediaItems->count() < \App\Models\ActorProfileIntentMedia::MAX_PER_INTENT)
        <div class="space-y-3" x-data="{progress: 0, uploading: false, failed: false}" x-on:livewire-upload-start="uploading=true; failed=false" x-on:livewire-upload-finish="uploading=false" x-on:livewire-upload-error="uploading=false; failed=true" x-on:livewire-upload-progress="progress=$event.detail.progress">
            <label class="block space-y-2"><span class="text-sm font-medium">{{ __('composer.media_title') }}</span><input type="file" wire:model="upload" accept="image/*,video/*,application/pdf" class="block w-full min-w-0 text-sm" /></label>
            <div x-show="uploading" role="status"><progress max="100" x-bind:value="progress" :aria-label="__('intents.media.uploading')"></progress> <span x-text="progress+'%'"></span></div>
            <p x-show="failed" role="alert">{{ __('composer.retry_upload') }}</p>
            <flux:input wire:model="uploadCaption" :label="__('intents.media.caption')" />
            <flux:select wire:model="uploadVisibility" :label="__('composer.detail_audience')"><option value="same">{{ __('composer.same_audience') }}</option><option value="private">{{ __('composer.only_me') }}</option></flux:select>
            <flux:checkbox wire:model="rights" :label="__('intents.media.rights')" />
            <flux:button wire:click="uploadMedia" wire:loading.attr="disabled" wire:target="upload,uploadMedia">{{ __('intents.media.save') }}</flux:button>
        </div>
    @endif
</div>
