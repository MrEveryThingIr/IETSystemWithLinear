@extends('layouts.business-public')
@section('title', $business->name)

@section('content')
@php($portal = $business->publicIntakePortals->firstWhere('type', 'real_estate'))
<section class="relative overflow-hidden bg-gradient-to-br from-emerald-950 via-teal-900 to-sky-900 text-white">
    <div class="relative mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
        <div class="max-w-3xl">
            <div class="inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1 text-sm font-semibold">{{ __('public_business.business') }}</div>
            <h1 class="mt-5 text-4xl font-black tracking-tight sm:text-6xl">{{ $business->name }}</h1>
            <p class="mt-5 max-w-2xl text-lg leading-9 text-white/80">{{ $business->short_intro ?: __('public_business.default_intro') }}</p>
            @if($portal)
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('public.real-estate.show', $portal) }}" class="rounded-2xl bg-white px-5 py-3 font-black text-emerald-900 no-underline shadow-lg transition hover:-translate-y-0.5">{{ __('public_business.real_estate.submit') }}</a>
                    <a href="#offerings" class="rounded-2xl border border-white/25 bg-white/10 px-5 py-3 font-bold text-white no-underline">{{ __('public_business.view_offerings') }}</a>
                </div>
            @endif
        </div>
    </div>
</section>

<div class="mx-auto max-w-6xl space-y-12 px-4 py-12 sm:px-6">
    @if($business->description)
        <section>
            <div class="text-sm font-black uppercase tracking-[0.16em] text-emerald-700">{{ __('public_business.about') }}</div>
            <div class="mt-4 max-w-4xl whitespace-pre-line text-lg leading-9 text-slate-700">{{ $business->description }}</div>
        </section>
    @endif

    @if($business->contactPoints->isNotEmpty() || $business->addresses->isNotEmpty())
        <section class="grid gap-5 lg:grid-cols-2">
            @if($business->contactPoints->isNotEmpty())
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black">{{ __('public_business.contact') }}</h2>
                    <div class="mt-4 space-y-3">
                        @foreach($business->contactPoints as $point)
                            <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3">
                                <span class="text-sm text-slate-500">{{ $point->label ?: __('public_business.contact_value') }}</span>
                                <strong dir="ltr">{{ $point->value }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @if($business->addresses->isNotEmpty())
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black">{{ __('public_business.location') }}</h2>
                    <div class="mt-4 space-y-3">
                        @foreach($business->addresses as $address)
                            <div class="rounded-2xl bg-slate-50 px-4 py-3 leading-7">
                                {{ collect([$address->province, $address->city, $address->district, $address->street])->filter()->join('، ') }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif

    <section id="offerings">
        <div class="text-sm font-black uppercase tracking-[0.16em] text-emerald-700">{{ __('public_business.catalog') }}</div>
        <h2 class="mt-2 text-3xl font-black">{{ __('public_business.offerings') }}</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse($business->listings as $listing)
                @php($version = $listing->publishedVersion)
                <a href="{{ route('public.businesses.listings.show', ['business' => $business->slug, 'listing' => $listing->uuid]) }}" class="group rounded-3xl border border-slate-200 bg-white p-6 text-inherit no-underline shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ __('public_business.types.'.$listing->listing_type) }}</span>
                        @if($listing->category)<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $listing->category->name }}</span>@endif
                    </div>
                    <h3 class="mt-4 text-xl font-black group-hover:text-emerald-700">{{ $version?->title }}</h3>
                    @if($version?->short_description)<p class="mt-3 leading-7 text-slate-600">{{ $version->short_description }}</p>@endif
                    <div class="mt-5 font-bold text-emerald-700">{{ __('public_business.view_details') }} ←</div>
                </a>
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">{{ __('public_business.no_offerings') }}</div>
            @endforelse
        </div>
    </section>

    @if($portal)
        <section class="rounded-[2rem] bg-emerald-700 p-7 text-white shadow-xl lg:p-10">
            <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <div class="text-sm font-black text-emerald-100">{{ __('public_business.real_estate.title') }}</div>
                    <h2 class="mt-2 text-3xl font-black">{{ __('public_business.real_estate.heading') }}</h2>
                    <p class="mt-3 max-w-3xl leading-8 text-emerald-50/90">{{ __('public_business.real_estate.help') }}</p>
                </div>
                <a href="{{ route('public.real-estate.show', $portal) }}" class="rounded-2xl bg-white px-5 py-3 text-center font-black text-emerald-800 no-underline">{{ __('public_business.real_estate.submit') }}</a>
            </div>
        </section>
    @endif
</div>
@endsection
