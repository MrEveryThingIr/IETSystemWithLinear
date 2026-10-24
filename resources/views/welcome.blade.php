<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <x-app.time-bar />

    {{-- The full-screen clock: analog face, the time and date under it, Gregorian on hover or tap. --}}
    <section class="relative isolate flex min-h-[calc(100dvh-2.5rem)] flex-col items-center justify-center overflow-hidden bg-[radial-gradient(ellipse_at_top,rgb(224_231_255)_0%,rgb(250_250_250)_55%)] px-4 py-10 dark:bg-[radial-gradient(ellipse_at_top,rgb(30_27_75)_0%,rgb(9_9_11)_60%)]"
        data-clock-stage aria-label="{{ __('time_bar.home.clock') }}" {!! \App\Support\TimeBar\ClockSettings::attributes(request()->user()) !!}>
        <div class="absolute end-3 top-3 flex items-center gap-2 sm:end-5 sm:top-5">
            <x-app.locale-switcher />
            <button type="button" class="grid size-10 place-items-center rounded-full border border-zinc-200 bg-white/80 text-zinc-600 shadow-sm backdrop-blur hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-indigo-400 dark:border-zinc-700 dark:bg-zinc-900/80 dark:text-zinc-300 dark:hover:text-white"
                data-clock-fullscreen aria-pressed="false" data-label-enter="{{ __('time_bar.home.fullscreen') }}" data-label-exit="{{ __('time_bar.home.exit_fullscreen') }}" aria-label="{{ __('time_bar.home.fullscreen') }}" title="{{ __('time_bar.home.fullscreen') }}">
                <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-icon-enter><path d="M3.5 7.5v-4h4M16.5 7.5v-4h-4M3.5 12.5v4h4M16.5 12.5v4h-4"/></svg>
                <svg viewBox="0 0 20 20" class="size-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-icon-exit hidden><path d="M7.5 3.5v4h-4M12.5 3.5v4h4M7.5 16.5v-4h-4M12.5 16.5v-4h4"/></svg>
            </button>
        </div>

        <div class="size-[min(84vw,56dvh)] max-h-[34rem] max-w-[34rem]" data-clock-face-size>
            <x-clock.analog detailed class="size-full" />
        </div>

        <div class="group/digital relative mt-8 flex flex-col items-center text-center sm:mt-10">
            <button type="button" class="flex flex-col items-center rounded-2xl px-4 py-2 outline-none focus-visible:ring-2 focus-visible:ring-indigo-400" aria-describedby="home-clock-gregorian">
                <time class="text-5xl font-extralight tabular-nums tracking-tight text-zinc-900 sm:text-7xl dark:text-white" data-clock-time data-seconds></time>
                <span class="mt-2 text-base font-medium text-zinc-600 sm:text-lg dark:text-zinc-300" data-clock-date></span>
            </button>
            <div id="home-clock-gregorian" role="tooltip"
                class="pointer-events-none absolute top-full z-10 mt-2 hidden w-max max-w-[min(24rem,calc(100vw-2rem))] rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm shadow-xl group-hover/digital:block group-focus-within/digital:block dark:border-zinc-700 dark:bg-zinc-900">
                <span class="block text-[0.7rem] font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-300">{{ __('time_bar.bar.gregorian') }}</span>
                <span class="mt-0.5 block font-medium text-zinc-900 dark:text-white" data-clock-gregorian></span>
                <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400"><span class="sr-only">{{ __('time_bar.bar.timezone') }}: </span><span data-clock-zone></span></span>
            </div>
        </div>

        <a href="#home-content" class="absolute bottom-4 left-1/2 inline-flex -translate-x-1/2 items-center gap-1.5 rounded-full px-3 py-2 text-sm text-zinc-500 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-indigo-400 dark:text-zinc-400 dark:hover:text-white" data-clock-continue>
            {{ __('time_bar.home.continue') }}
            <svg viewBox="0 0 20 20" class="size-4 motion-safe:animate-bounce" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 8 5 5 5-5"/></svg>
        </a>
    </section>

    <main id="home-content" class="mx-auto flex max-w-5xl scroll-mt-4 items-start px-4 py-12 sm:px-6">
        <div class="w-full space-y-6">
            <x-app.brand-banner />
            <flux:card class="w-full space-y-6">
            <div class="space-y-3">
                <flux:badge color="indigo">{{ __('access.root.invitation_only') }}</flux:badge>
                <flux:heading size="xl">{{ config('app.name') }}</flux:heading>
                <flux:text>{{ __('access.root.help') }}</flux:text>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" class="w-full">{{ __('access.root.open') }}</flux:button>
                @else
                    <flux:button :href="route('login')" variant="primary" class="w-full">{{ __('ui.auth.login') }}</flux:button>
                @endauth
                <flux:button disabled variant="ghost" class="w-full">{{ __('access.root.registration_by_invitation') }}</flux:button>
            </div>
            </flux:card>

            @if (! empty($publicCards))
                <section class="space-y-4" data-home-public>
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight">{{ __('public_content.home.title') }}</h2>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('public_content.home.help') }}</p>
                        </div>
                        <flux:button :href="route('public-content.index')" size="sm" variant="ghost" icon-trailing="arrow-right" class="shrink-0 rtl:[&_svg]:rotate-180">{{ __('public_content.home.all') }}</flux:button>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($publicCards as $card)
                            <x-public-content.card :card="$card" />
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </main>
    @fluxScripts
</body>
</html>
