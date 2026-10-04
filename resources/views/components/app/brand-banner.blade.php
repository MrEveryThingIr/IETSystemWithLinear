@props(['compact' => false])

<section {{ $attributes->class([
    'overflow-hidden rounded-[2rem] border border-indigo-200/70 bg-gradient-to-br from-slate-950 via-indigo-950 to-violet-950 text-white shadow-xl shadow-indigo-950/10',
    'p-6 sm:p-8' => $compact,
    'p-8 sm:p-12' => ! $compact,
]) }}>
    <div class="max-w-4xl">
        <div class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-bold tracking-wide text-indigo-100">
            {{ __('brand.platform') }}
        </div>
        <h1 @class(['mt-4 font-black tracking-tight', 'text-3xl sm:text-4xl' => $compact, 'text-4xl sm:text-6xl' => ! $compact])>
            <span class="bg-gradient-to-r from-cyan-300 via-white to-fuchsia-300 bg-clip-text text-transparent">Everything</span>
            <span class="ms-2 text-white">{{ __('brand.for_everyone') }}</span>
        </h1>
        <p @class(['mt-3 font-bold text-indigo-100', 'text-lg' => $compact, 'text-xl sm:text-2xl' => ! $compact])>
            {{ __('brand.local_motto') }}
        </p>
        @if(app()->getLocale() !== 'en')
            <p class="mt-1 text-sm font-medium tracking-wide text-white/55">{{ __('brand.english_motto') }}</p>
        @endif
        <p class="mt-5 max-w-2xl leading-8 text-white/70">{{ __('brand.platform_short') }}</p>
    </div>
</section>
