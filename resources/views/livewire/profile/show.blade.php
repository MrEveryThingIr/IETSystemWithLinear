<section class="space-y-8">
    @php
        $displayImage = $profile->displayImage;
        $displayName = $profile->display_name ?: ($profile->actor->user?->username ?? __('ui.profile.unnamed'));
    @endphp

    <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="h-28 bg-gradient-to-r from-zinc-100 via-zinc-50 to-zinc-100 dark:from-zinc-800 dark:via-zinc-900 dark:to-zinc-800"></div>
        <div class="px-5 pb-6 sm:px-8">
            <div class="-mt-14 flex flex-col gap-5 sm:flex-row sm:items-end">
                <div class="h-28 w-28 shrink-0 overflow-hidden rounded-3xl border-4 border-white bg-zinc-200 shadow-sm dark:border-zinc-900 dark:bg-zinc-800">
                    @if ($displayImage && $displayImage->asset?->isReadyForPublication())
                        <img
                            src="{{ route('profiles.images.show', [$profile, $displayImage]) }}"
                            alt="{{ __('ui.profile.avatar_alt', ['name' => $displayName]) }}"
                            class="h-full w-full object-cover"
                        >
                    @else
                        <div class="flex h-full w-full items-center justify-center text-3xl font-semibold text-zinc-500 dark:text-zinc-300">
                            {{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1 pb-1">
                    <h1 class="truncate text-2xl font-semibold tracking-tight sm:text-3xl" dir="auto">{{ $displayName }}</h1>
                    @if ($profile->headline)
                        <p class="mt-1 text-base text-zinc-600 dark:text-zinc-300" dir="auto">{{ $profile->headline }}</p>
                    @endif
                </div>

                @auth
                    @if ((int) auth()->user()?->actor?->id === (int) $profile->actor_id)
                        <flux:button :href="route('profile.edit')" variant="primary">{{ __('ui.profile.edit') }}</flux:button>
                    @endif
                @endauth
            </div>

            @if ($profile->location_text || $profile->website_url)
                <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                    @if ($profile->location_text)
                        <span dir="auto">{{ $profile->location_text }}</span>
                    @endif
                    @if ($profile->website_url)
                        <a href="{{ $profile->website_url }}" target="_blank" rel="noopener noreferrer nofollow" class="font-medium underline decoration-zinc-300 underline-offset-4 hover:decoration-current">
                            {{ __('ui.profile.website') }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if ($profile->bio)
        <article class="rounded-2xl border border-zinc-200 bg-white p-5 sm:p-7 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold">{{ __('ui.profile.about') }}</h2>
            <p class="mt-3 whitespace-pre-line leading-7 text-zinc-700 dark:text-zinc-200" dir="auto">{{ $profile->bio }}</p>
        </article>
    @endif
</section>
