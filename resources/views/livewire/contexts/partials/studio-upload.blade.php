{{--
    Upload with clear states. The limit is shown before choosing and the file
    is checked in the browser as soon as it is selected. Images and documents
    upload directly; video and audio upload in resumable parts (see
    resources/js/media-upload.js), so their size is not bound by the host's
    PHP request limit and a broken connection or refresh can continue.
--}}
@php
    $accept = match ($insertKind) {
        'image' => 'image/jpeg,image/png,image/gif,image/webp,image/avif',
        'video' => 'video/mp4,video/webm,video/quicktime,.mp4,.m4v,.webm,.mov',
        'audio' => 'audio/*,.mp3,.m4a,.wav,.ogg,.flac',
        'file' => '.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx',
        default => 'image/jpeg,image/png,image/gif,image/webp,image/avif,video/mp4,video/webm,video/quicktime,.mp4,.m4v,.webm,.mov,audio/*,.mp3,.m4a,.wav,.ogg,.flac,.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx',
    };
    $limitLabel = fn (int $bytes) => \Illuminate\Support\Number::fileSize($bytes, precision: $bytes >= 1073741824 ? 1 : 0);
    $videoLimit = $resumable['limits']['video'] > 0 ? $resumable['limits']['video'] : $uploadLimits['video'];
    $audioLimit = $resumable['limits']['audio'] > 0 ? $resumable['limits']['audio'] : $uploadLimits['audio'];
@endphp

<div
    x-data="mediaUpload({
        limits: @js($uploadLimits),
        resumableLimits: @js($resumable['limits']),
        createUrl: @js($resumable['createUrl']),
        statusUrl: @js($resumable['statusUrl']),
        contentUuid: @js($resumable['contentUuid']),
        csrf: @js(csrf_token()),
        labels: {
            tooLarge: @js(__('studio_ui.upload.too_large', ['size' => ':size', 'limit' => ':limit'])),
            failed: @js(__('studio_ui.upload.failed')),
            failedResumable: @js(__('studio_ui.upload.failed_resumable')),
            resuming: @js(__('studio_ui.upload.resuming')),
        },
    })"
    class="space-y-4"
    data-studio-upload
>
    <div class="rounded-xl bg-zinc-50 p-3 text-xs leading-5 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300" data-upload-limits>
        @if ($insertKind === 'image')
            {{ __('studio_ui.upload.limit_image', ['limit' => $limitLabel($uploadLimits['image'])]) }}
        @elseif ($insertKind === 'video')
            {{ __('studio_ui.upload.limit_video', ['limit' => $limitLabel($videoLimit)]) }} {{ __('studio_ui.upload.resumable_note') }}
        @elseif ($insertKind === 'audio')
            {{ __('studio_ui.upload.limit_audio', ['limit' => $limitLabel($audioLimit)]) }} {{ __('studio_ui.upload.resumable_note') }}
        @elseif ($insertKind === 'file')
            {{ __('studio_ui.upload.limit_document', ['limit' => $limitLabel($uploadLimits['document'])]) }}
        @else
            {{ __('studio_ui.upload.limit_all', [
                'image' => $limitLabel($uploadLimits['image']),
                'video' => $limitLabel($videoLimit),
                'audio' => $limitLabel($audioLimit),
                'document' => $limitLabel($uploadLimits['document']),
            ]) }}
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <flux:select wire:model.live="assetRightsStatus" :label="__('media.rights_status')">
            @foreach ($rightsStatuses as $status)
                <option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.300ms="assetCaption" :label="__('media.caption')" maxlength="1000" />
    </div>

    <label
        x-show="state === 'idle' || state === 'too_large' || state === 'failed'"
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 px-4 py-8 text-center hover:border-zinc-400 dark:border-zinc-700"
    >
        <flux:icon name="arrow-up-tray" class="size-6 text-zinc-400" />
        <span class="text-sm font-medium" x-text="state === 'failed' ? @js(__('studio_ui.upload.choose_again')) : @js(__('studio_ui.upload.choose'))"></span>
        <input type="file" class="sr-only" accept="{{ $accept }}" x-on:change="choose($event)" data-upload-input />
    </label>

    <template x-if="file && state !== 'idle'">
        <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700" data-upload-file>
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="min-w-0 truncate font-medium" dir="auto" x-text="file.name"></span>
                <span class="shrink-0 text-xs text-zinc-500" x-text="size(file.size)"></span>
            </div>
            <div x-show="state === 'uploading' || state === 'processing'" class="mt-2">
                <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-zinc-900 transition-all dark:bg-white" :style="`width: ${progress}%`"></div>
                </div>
                <div class="mt-1 flex items-center justify-between text-xs text-zinc-500">
                    <span x-show="state === 'uploading'" x-text="`{{ __('studio_ui.upload.uploading') }} ${progress}%`" data-upload-progress></span>
                    <span x-show="state === 'processing'">{{ __('studio_ui.upload.processing_media') }}</span>
                    <button type="button" x-show="state === 'uploading'" x-on:click="cancel()" class="font-medium underline-offset-4 hover:underline">{{ __('studio_ui.upload.cancel') }}</button>
                </div>
                <p x-show="note" x-text="note" class="mt-1 text-xs text-zinc-500"></p>
                <p x-show="mode === 'resumable' && state === 'uploading'" class="mt-1 text-xs text-zinc-500">{{ __('studio_ui.upload.keep_open') }}</p>
            </div>
            <div x-show="state === 'ready'" class="mt-2 flex items-center justify-between text-xs">
                <span class="text-green-700 dark:text-green-400">✓ {{ __('studio_ui.upload.ready') }}</span>
                <button type="button" x-on:click="clear()" class="text-zinc-500 underline-offset-4 hover:underline">{{ __('studio_ui.upload.remove') }}</button>
            </div>
        </div>
    </template>

    <p x-show="message" x-text="message" class="text-sm text-red-600" data-upload-message></p>
    @error('assetUpload')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <flux:button type="button" wire:click="closeMediaDialog" variant="ghost">{{ __('studio.cancel') }}</flux:button>

        <flux:button type="button" x-show="state === 'failed'" x-on:click="retry()" variant="primary" data-upload-retry>{{ __('studio_ui.upload.retry') }}</flux:button>
        <flux:button type="button" x-show="state === 'uploading' || state === 'processing'" variant="primary" disabled>
            <span x-text="state === 'processing' ? @js(__('studio_ui.upload.processing')) : `{{ __('studio_ui.upload.uploading') }} ${progress}%`"></span>
        </flux:button>
        <flux:button type="button" x-show="state === 'idle' || state === 'too_large'" variant="primary" disabled>{{ __('studio_ui.upload.select_first') }}</flux:button>
        <flux:button
            type="button"
            x-show="state === 'ready'"
            wire:click="attachAsset"
            wire:loading.attr="disabled"
            wire:target="attachAsset"
            variant="primary"
            data-upload-submit
        >
            <span wire:loading.remove wire:target="attachAsset">{{ $insertKind !== '' ? __('studio_ui.upload.add_to_document') : __('studio_ui.upload.add_to_media') }}</span>
            <span wire:loading wire:target="attachAsset">{{ __('studio_ui.upload.processing') }}</span>
        </flux:button>
    </div>
</div>
