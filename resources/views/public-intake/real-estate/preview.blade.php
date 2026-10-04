<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>{{ __('public_real_estate.preview.title') }} — {{ $portal?->business?->name ?? $portal?->title }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<main class="mx-auto max-w-2xl px-4 py-10">
    @if($portal?->business)
        <div class="mb-4 flex items-center justify-between gap-3">
            <a href="{{ route('public.businesses.show', ['business' => $portal->business->slug]) }}" class="font-bold text-emerald-700 no-underline">← {{ __('public_real_estate.preview.back_business') }}</a>
            <x-app.locale-switcher />
        </div>
    @endif
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-10">
        <div class="rounded-2xl bg-emerald-50 p-5 text-emerald-900">
            <p class="text-xl font-black">✓ {{ __('public_real_estate.preview.success') }}</p>
            <p class="mt-2 leading-7">
                {{ $portal?->success_message ?: __('public_real_estate.preview.default_message') }}
            </p>
        </div>

        <div class="mt-6 flex items-center justify-between gap-4 rounded-2xl border border-slate-200 p-4">
            <span class="text-sm text-slate-500">{{ __('public_real_estate.preview.reference') }}</span>
            <strong class="font-mono text-lg" dir="ltr">{{ $case->reference_code }}</strong>
        </div>

        <dl class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-4">
                <dt class="text-xs text-slate-500">{{ __('public_real_estate.preview.name') }}</dt>
                <dd class="mt-1 font-bold">{{ $case->contact_name }}</dd>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <dt class="text-xs text-slate-500">{{ __('public_real_estate.preview.phone') }}</dt>
                <dd class="mt-1 font-bold" dir="ltr">{{ $case->phone }}</dd>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <dt class="text-xs text-slate-500">{{ __('public_real_estate.preview.intent') }}</dt>
                <dd class="mt-1 font-bold">{{ $case->intent === 'offer' ? __('public_real_estate.preview.offer') : __('public_real_estate.preview.need') }}</dd>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <dt class="text-xs text-slate-500">{{ __('public_real_estate.preview.transaction') }}</dt>
                <dd class="mt-1 font-bold">{{ $case->transaction_mode === 'sale' ? __('public_real_estate.preview.sale') : __('public_real_estate.preview.rent') }}</dd>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <dt class="text-xs text-slate-500">{{ __('public_real_estate.preview.area') }}</dt>
                <dd class="mt-1 font-bold">{{ $case->public_area ?: '—' }}</dd>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
                <dt class="text-xs text-slate-500">{{ __('public_real_estate.preview.dimensions') }}</dt>
                <dd class="mt-1 font-bold">
                    {{ $case->land_area ?: '—' }} / {{ $case->construction_area ?: '—' }} متر²
                </dd>
            </div>
        </dl>

        @if($case->media->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="font-black text-slate-900">{{ __('public_real_estate.preview.media') }}</div>
                <div class="mt-2 text-sm leading-7 text-slate-600">
                    {{ __('public_real_estate.preview.media_summary', [
                        'images' => $case->media->where('kind', 'image')->count(),
                        'videos' => $case->media->where('kind', 'video')->count(),
                        'audios' => $case->media->where('kind', 'audio')->count(),
                    ]) }}
                </div>
            </div>
        @endif

        <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-900">
            {{ __('public_real_estate.preview.one_time') }}
        </div>
    </section>
</main>
</body>
</html>
