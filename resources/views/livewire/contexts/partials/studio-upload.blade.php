{{--
    Upload with clear states: the limit is shown before choosing, the file is
    checked in the browser as soon as it is selected, progress is visible, and
    the upload can be cancelled or retried without reloading the page.
--}}
@php
    $accept = match ($insertKind) {
        'image' => 'image/jpeg,image/png,image/gif,image/webp,image/avif',
        'video' => 'video/mp4,video/webm,video/quicktime',
        'audio' => 'audio/*',
        'file' => '.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx',
        default => 'image/jpeg,image/png,image/gif,image/webp,image/avif,video/mp4,video/webm,video/quicktime,audio/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx',
    };
    $limitLabel = fn (int $bytes) => \Illuminate\Support\Number::fileSize($bytes, precision: 0);
@endphp

<div
    x-data="{
        limits: @js($uploadLimits),
        state: 'idle',
        file: null,
        progress: 0,
        message: '',
        kindOf(file) {
            const type = file.type || '';
            if (type.startsWith('image/')) return 'image';
            if (type.startsWith('video/')) return 'video';
            if (type.startsWith('audio/')) return 'audio';
            return 'document';
        },
        size(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        },
        choose(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (! file) return;
            this.file = file;
            const limit = this.limits[this.kindOf(file)];
            if (file.size > limit) {
                this.state = 'too_large';
                this.message = @js(__('studio_ui.upload.too_large', ['size' => ':size', 'limit' => ':limit'])).replace(':size', this.size(file.size)).replace(':limit', this.size(limit));
                return;
            }
            this.upload();
        },
        upload() {
            this.state = 'uploading';
            this.progress = 0;
            this.message = '';
            $wire.upload('assetUpload', this.file,
                () => { this.state = 'ready'; },
                () => { this.state = 'failed'; this.message = @js(__('studio_ui.upload.failed')); },
                (event) => { this.progress = event.detail.progress; },
                () => { this.state = 'idle'; this.file = null; },
            );
        },
        cancel() {
            $wire.cancelUpload('assetUpload');
            this.state = 'idle';
            this.file = null;
        },
        clear() {
            if (this.state === 'ready') { $wire.set('assetUpload', null); }
            this.state = 'idle';
            this.file = null;
            this.message = '';
        },
    }"
    class="space-y-4"
    data-studio-upload
>
    <div class="rounded-xl bg-zinc-50 p-3 text-xs leading-5 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300" data-upload-limits>
        @if ($insertKind === 'image')
            {{ __('studio_ui.upload.limit_image', ['limit' => $limitLabel($uploadLimits['image'])]) }}
        @elseif ($insertKind === 'video')
            {{ __('studio_ui.upload.limit_video', ['limit' => $limitLabel($uploadLimits['video'])]) }}
        @elseif ($insertKind === 'audio')
            {{ __('studio_ui.upload.limit_audio', ['limit' => $limitLabel($uploadLimits['audio'])]) }}
        @elseif ($insertKind === 'file')
            {{ __('studio_ui.upload.limit_document', ['limit' => $limitLabel($uploadLimits['document'])]) }}
        @else
            {{ __('studio_ui.upload.limit_all', [
                'image' => $limitLabel($uploadLimits['image']),
                'video' => $limitLabel($uploadLimits['video']),
                'audio' => $limitLabel($uploadLimits['audio']),
                'document' => $limitLabel($uploadLimits['document']),
            ]) }}
        @endif
    </div>

    <label
        x-show="state === 'idle' || state === 'too_large' || state === 'failed'"
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 px-4 py-8 text-center hover:border-zinc-400 dark:border-zinc-700"
    >
        <flux:icon name="arrow-up-tray" class="size-6 text-zinc-400" />
        <span class="text-sm font-medium">{{ __('studio_ui.upload.choose') }}</span>
        <input type="file" class="sr-only" accept="{{ $accept }}" x-on:change="choose($event)" data-upload-input />
    </label>

    <template x-if="file && state !== 'idle'">
        <div class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="min-w-0 truncate font-medium" dir="auto" x-text="file.name"></span>
                <span class="shrink-0 text-xs text-zinc-500" x-text="size(file.size)"></span>
            </div>
            <div x-show="state === 'uploading'" class="mt-2">
                <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-zinc-900 transition-all dark:bg-white" :style="`width: ${progress}%`"></div>
                </div>
                <div class="mt-1 flex items-center justify-between text-xs text-zinc-500">
                    <span x-text="`{{ __('studio_ui.upload.uploading') }} ${progress}%`"></span>
                    <button type="button" x-on:click="cancel()" class="font-medium underline-offset-4 hover:underline">{{ __('studio_ui.upload.cancel') }}</button>
                </div>
            </div>
            <div x-show="state === 'ready'" class="mt-2 flex items-center justify-between text-xs">
                <span class="text-green-700 dark:text-green-400">✓ {{ __('studio_ui.upload.ready') }}</span>
                <button type="button" x-on:click="clear()" class="text-zinc-500 underline-offset-4 hover:underline">{{ __('studio_ui.upload.remove') }}</button>
            </div>
        </div>
    </template>

    <p x-show="message" x-text="message" class="text-sm text-red-600" data-upload-message></p>
    @error('assetUpload')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

    <div class="grid gap-3 sm:grid-cols-2">
        <flux:select wire:model="assetRightsStatus" :label="__('media.rights_status')">
            @foreach ($rightsStatuses as $status)
                <option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model="assetCaption" :label="__('media.caption')" maxlength="1000" />
    </div>

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <flux:button type="button" wire:click="closeMediaDialog" variant="ghost">{{ __('studio.cancel') }}</flux:button>

        <flux:button type="button" x-show="state === 'failed'" x-on:click="upload()" variant="primary">{{ __('studio_ui.upload.retry') }}</flux:button>
        <flux:button type="button" x-show="state === 'uploading'" variant="primary" disabled>
            <span x-text="`{{ __('studio_ui.upload.uploading') }} ${progress}%`"></span>
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
