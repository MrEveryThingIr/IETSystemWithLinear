@extends('layouts.business-public')
@section('title', ($version?->title ?? __('public_business.untitled')).' — '.$business->name)

@section('content')
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
    <a href="{{ route('public.businesses.show', $business) }}" class="font-bold text-emerald-700 no-underline">← {{ $business->name }}</a>
    <article class="mt-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <div class="flex flex-wrap gap-2">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ __('public_business.types.'.$listing->listing_type) }}</span>
            @if($listing->category)<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $listing->category->name }}</span>@endif
        </div>
        <h1 class="mt-5 text-3xl font-black sm:text-5xl">{{ $version?->title ?? __('public_business.untitled') }}</h1>
        @if($version?->short_description)<p class="mt-5 text-lg leading-8 text-slate-600">{{ $version->short_description }}</p>@endif
        @if($version?->description)<div class="mt-8 whitespace-pre-line leading-8 text-slate-700">{{ $version->description }}</div>@endif
        @if($version?->propertyDetails)
            @php($property = $version->propertyDetails)
            <section class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    __('public_business.property.transaction') => $property->transaction_mode,
                    __('public_business.property.class') => $property->property_class,
                    __('public_business.property.area') => $property->construction_area ? $property->construction_area.' m²' : null,
                    __('public_business.property.bedrooms') => $property->bedrooms,
                ] as $label => $value)
                    @if($value !== null && $value !== '')
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-xs font-bold text-slate-500">{{ $label }}</div>
                            <div class="mt-2 font-black">{{ $value }}</div>
                        </div>
                    @endif
                @endforeach
            </section>
        @endif
        @if($listing->prices->isNotEmpty())
            <section class="mt-8 border-t border-slate-200 pt-8">
                <h2 class="text-xl font-black">{{ __('public_business.pricing') }}</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach($listing->prices->take(6) as $price)
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-sm text-slate-500">{{ $price->price_type }}</div>
                            <div class="mt-1 text-xl font-black">
                                {{ \App\Support\MoneyAmount::format((int)$price->amount_minor, (int)$price->monetaryUnit->exponent) }} {{ $price->monetaryUnit->code }}
                                @if($price->basis)<span class="text-sm font-medium text-slate-500">/ {{ $price->basis }}</span>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</div>
@endsection
