{{--
    Lesson video player: the browser's native controls (play, seek, volume,
    fullscreen) plus playback speed, captions on/off and keyboard shortcuts.
    Every URL passed in is an authorized, private route.

    tracks: list of ['src' => url, 'label' => string, 'language' => code, 'default' => bool]
--}}
@props([
    'src',
    'poster' => null,
    'tracks' => [],
    'durationMs' => null,
    'mediaClass' => null,
    'audio' => false,
])

@php
    $durationLabel = null;
    if ($durationMs) {
        $seconds = intdiv((int) $durationMs, 1000);
        $durationLabel = $seconds >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
    $hasTracks = count($tracks) > 0;
@endphp

<div
    {{ $attributes->merge(['class' => 'space-y-2']) }}
    x-data="{
        speed: 1,
        captions: {{ $hasTracks ? 'true' : 'false' }},
        media() { return this.$refs.media; },
        setSpeed(value) { this.speed = value; this.media().playbackRate = value; },
        applyCaptions() {
            Array.from(this.media().textTracks || []).forEach((track, index) => {
                track.mode = this.captions && index === 0 ? 'showing' : 'hidden';
            });
        },
        toggleCaptions() { this.captions = ! this.captions; this.applyCaptions(); },
        key(event) {
            const media = this.media();
            if (event.target.closest('select, button, input')) return;
            switch (event.key) {
                case ' ': case 'k': event.preventDefault(); media.paused ? media.play() : media.pause(); break;
                case 'ArrowLeft': case 'j': event.preventDefault(); media.currentTime = Math.max(0, media.currentTime - (event.key === 'j' ? 10 : 5)); break;
                case 'ArrowRight': case 'l': event.preventDefault(); media.currentTime = Math.min(media.duration || Infinity, media.currentTime + (event.key === 'l' ? 10 : 5)); break;
                case 'm': media.muted = ! media.muted; break;
                case 'f': if (media.requestFullscreen) { document.fullscreenElement ? document.exitFullscreen() : media.requestFullscreen(); } break;
                case 'c': this.toggleCaptions(); break;
            }
        },
    }"
    x-init="$nextTick(() => applyCaptions())"
    x-on:keydown="key($event)"
    data-video-player
>
    @if ($audio)
        <audio x-ref="media" controls preload="metadata" class="{{ $mediaClass ?? 'w-full' }}" src="{{ $src }}" tabindex="0">
            @foreach ($tracks as $track)
                <track kind="captions" src="{{ $track['src'] }}" srclang="{{ $track['language'] }}" label="{{ $track['label'] }}" @if ($track['default'] ?? false) default @endif />
            @endforeach
        </audio>
    @else
        <video
            x-ref="media"
            controls
            preload="metadata"
            playsinline
            tabindex="0"
            class="{{ $mediaClass ?? 'max-h-[42rem] w-full rounded-lg bg-black' }}"
            src="{{ $src }}"
            @if ($poster) poster="{{ $poster }}" @endif
        >
            @foreach ($tracks as $track)
                <track kind="captions" src="{{ $track['src'] }}" srclang="{{ $track['language'] }}" label="{{ $track['label'] }}" @if ($track['default'] ?? false) default @endif />
            @endforeach
        </video>
    @endif

    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
        <label class="inline-flex items-center gap-1">
            <span>{{ __('media.player.speed') }}</span>
            <select x-on:change="setSpeed(parseFloat($event.target.value))" class="rounded border border-zinc-300 bg-transparent px-1 py-0.5 text-xs dark:border-zinc-700" aria-label="{{ __('media.player.speed') }}" data-player-speed>
                @foreach ([0.75, 1, 1.25, 1.5, 1.75, 2] as $rate)
                    <option value="{{ $rate }}" @selected($rate === 1)>{{ $rate }}×</option>
                @endforeach
            </select>
        </label>
        @if ($hasTracks)
            <button type="button" x-on:click="toggleCaptions()" class="rounded border border-zinc-300 px-2 py-0.5 dark:border-zinc-700" :aria-pressed="captions.toString()" data-player-captions>
                <span x-text="captions ? @js(__('media.player.captions_on')) : @js(__('media.player.captions_off'))"></span>
            </button>
        @endif
        @if ($durationLabel)
            <span>{{ $durationLabel }}</span>
        @endif
        <span class="hidden sm:inline">· {{ __('media.player.keyboard_help') }}</span>
    </div>
</div>
