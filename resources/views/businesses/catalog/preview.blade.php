@extends('layouts.app')
@section('title', __('business_listing.preview_page.label').' — '.$version->title)

@section('content')
@php
    $p = $version->propertyDetails;
    $prices = $listing->prices
        ->where('business_listing_version_id', $version->id)
        ->where('visibility', 'public')
        ->values();

    $displayPropertyValue = static function (string $key, mixed $value): mixed {
        if ($key === 'transaction_mode' && in_array($value, ['sale', 'rent', 'sale_or_rent'], true)) {
            return __('business_listing.property.'.$value);
        }

        if ($key === 'property_class' && in_array($value, ['residential', 'commercial', 'office', 'land', 'industrial', 'agricultural', 'mixed', 'other'], true)) {
            return __('business_listing.property.classes.'.$value);
        }

        if ($key === 'building_condition' && in_array($value, ['new', 'excellent', 'good', 'renovated', 'needs_renovation', 'old', 'teardown'], true)) {
            return __('business_listing.property.conditions.'.$value);
        }

        return $value;
    };
@endphp
<style>
.pv{max-width:1040px;margin:auto;padding:26px 16px 70px}.hero{background:#fff;border:1px solid #e2e8f0;border-radius:28px;overflow:hidden}.cover{height:360px;background:#0f172a}.cover img,.cover video{width:100%;height:100%;object-fit:cover}.body{padding:26px}.body h1{font-size:34px;margin:8px 0}.muted{color:#64748b;line-height:1.8}.badge{display:inline-flex;padding:6px 10px;border-radius:999px;background:#f1f5f9;font-size:12px;font-weight:850;margin:2px}.panel{background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:20px;margin-top:16px}.facts{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.fact{background:#f8fafc;border-radius:14px;padding:12px}.gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px}.gallery img,.gallery video{width:100%;height:190px;object-fit:cover;border-radius:16px;background:#0f172a}.notice{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:14px;padding:12px}.btn{display:inline-flex;padding:10px 14px;border-radius:13px;border:1px solid #cbd5e1;text-decoration:none;font-weight:850;color:#334155;background:#fff}@media(max-width:760px){.facts{grid-template-columns:1fr}.cover{height:240px}}
</style>
<main class="pv">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
        <a class="btn" href="{{ route('businesses.catalog.listings.edit',[$business,$listing]) }}">← {{ __('business_listing.editor_title') }}</a>
        <span class="badge">{{ __('business_listing.preview_page.label') }}</span>
    </div>

    <div class="notice">{{ __('business_listing.preview_page.private_notice') }}</div>

    @php($cover = $version->media->firstWhere('role','cover') ?? $version->media->first())
    <article class="hero" style="margin-top:16px">
        @if($cover)
            <div class="cover">
                @if(str_starts_with($cover->asset->mime_type,'image/'))
                    <img src="{{ route('businesses.catalog.listings.media.show',[$business,$listing,$cover]) }}" alt="{{ $cover->caption ?: $cover->asset->alt_text }}">
                @elseif(str_starts_with($cover->asset->mime_type,'video/'))
                    <video controls src="{{ route('businesses.catalog.listings.media.show',[$business,$listing,$cover]) }}"></video>
                @endif
            </div>
        @endif
        <div class="body">
            <div><span class="badge">{{ $listing->listing_type }}</span><span class="badge">{{ __('business_listing.availability.'.$listing->availability_status) }}</span></div>
            <h1>{{ $version->title }}</h1>
            @if($version->short_description)<p class="muted">{{ $version->short_description }}</p>@endif
            @if($version->description)<div style="white-space:pre-wrap;line-height:1.85">{{ $version->description }}</div>@endif
        </div>
    </article>

    @if($p)
        <section class="panel">
            <h2>{{ __('business_listing.preview_page.details') }}</h2>
            <div class="facts">
                @foreach([
                    'transaction_mode'=>__('business_listing.property.transaction_mode'),
                    'property_class'=>__('business_listing.property.property_class'),
                    'property_subtype'=>__('business_listing.property.property_subtype'),
                    'public_area'=>__('business_listing.property.public_area'),
                    'land_area'=>__('business_listing.property.land_area'),
                    'construction_area'=>__('business_listing.property.construction_area'),
                    'bedrooms'=>__('business_listing.property.bedrooms'),
                    'building_condition'=>__('business_listing.property.building_condition'),
                    'floor_number'=>__('business_listing.property.floor_number'),
                    'total_floors'=>__('business_listing.property.total_floors'),
                    'orientation'=>__('business_listing.property.orientation'),
                    'deed_type'=>__('business_listing.property.deed_type'),
                    'usage_type'=>__('business_listing.property.usage_type'),
                ] as $key=>$label)
                    @if($p->{$key} !== null && $p->{$key} !== '')
                        <div class="fact"><div class="muted" style="font-size:12px">{{ $label }}</div><strong>{{ $displayPropertyValue($key, $p->{$key}) }}</strong></div>
                    @endif
                @endforeach
            </div>
            @if($p->public_notes)<p style="white-space:pre-wrap">{{ $p->public_notes }}</p>@endif
            @if(($p->facilities ?? []) !== [])<div class="muted">{{ implode(' · ', $p->facilities) }}</div>@endif
        </section>
    @endif

    @if($prices->isNotEmpty())
        <section class="panel">
            <h2>{{ __('business_listing.preview_page.price') }}</h2>
            @foreach($prices as $price)
                <div style="padding:8px 0;border-top:1px solid #f1f5f9">
                    <strong>{{ $price->price_type }}</strong> ·
                    {{ \App\Support\MoneyAmount::format((int)$price->amount_minor,(int)$price->monetaryUnit->exponent) }}
                    {{ $price->monetaryUnit->code }}
                    @if($price->basis) / {{ $price->basis }} @endif
                </div>
            @endforeach
        </section>
    @endif

    @php($gallery = $version->media->filter(fn($m)=>$cover === null || $m->id !== $cover->id))
    @if($gallery->isNotEmpty())
        <section class="panel">
            <h2>{{ __('business_listing.preview_page.media') }}</h2>
            <div class="gallery">
                @foreach($gallery as $media)
                    <div>
                        @if(str_starts_with($media->asset->mime_type,'image/'))
                            <img src="{{ route('businesses.catalog.listings.media.show',[$business,$listing,$media]) }}" alt="{{ $media->caption ?: $media->asset->alt_text }}">
                        @elseif(str_starts_with($media->asset->mime_type,'video/'))
                            <video controls src="{{ route('businesses.catalog.listings.media.show',[$business,$listing,$media]) }}"></video>
                        @elseif(str_starts_with($media->asset->mime_type,'audio/'))
                            <audio controls style="width:100%" src="{{ route('businesses.catalog.listings.media.show',[$business,$listing,$media]) }}"></audio>
                        @endif
                        @if($media->caption)<div class="muted" style="margin-top:5px">{{ $media->caption }}</div>@endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
