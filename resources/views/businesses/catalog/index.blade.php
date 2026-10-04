@extends('layouts.app')
@section('title', __('business.catalog.title').' — '.$business->name)

@section('content')
<style>
.cat{max-width:1220px;margin:auto;padding:26px 16px 70px}.cat-head{display:flex;justify-content:space-between;gap:16px;align-items:end;flex-wrap:wrap}.cat-head h1{font-size:32px;margin:4px 0}.muted{color:#667085;line-height:1.8}.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:0;border-radius:14px;padding:10px 14px;font-weight:900;cursor:pointer}.primary{background:#4f46e5;color:#fff}.light{background:#fff;color:#334155;border:1px solid #cbd5e1}.green{background:#047857;color:#fff}
.panel{background:#fff;border:1px solid #e4e7ec;border-radius:24px;margin-top:18px;padding:20px;box-shadow:0 7px 24px rgba(15,23,42,.04)}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.listings{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px;margin-top:14px}.listing{border:1px solid #e2e8f0;border-radius:20px;padding:18px;background:#fff}.badges{display:flex;gap:6px;flex-wrap:wrap}.badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:12px;font-weight:850}.listing h3{font-size:20px;margin:10px 0 6px}.prices{margin-top:12px;display:grid;gap:5px}.price{font-size:14px;color:#334155}.price-form{margin-top:14px;padding-top:14px;border-top:1px dashed #cbd5e1}.price-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}
label span{display:block;font-size:13px;font-weight:850;margin-bottom:6px}input,select,textarea{width:100%;border:1.5px solid #aab4c4;border-radius:12px;padding:11px;background:#fff}.empty{border:2px dashed #cbd5e1;border-radius:20px;padding:30px;text-align:center;color:#64748b}
@media(max-width:800px){.grid,.price-grid{grid-template-columns:1fr}}
</style>
<main class="cat">
    @if(session('status'))<div class="panel" style="background:#ecfdf5;color:#065f46;font-weight:900">✓ {{ session('status') }}</div>@endif
    @if($errors->any())<div class="panel" style="background:#fff1f2;color:#9f1239"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <header class="cat-head">
        <div>
            <a href="{{ route('businesses.show',$business) }}" style="text-decoration:none;color:#4f46e5;font-weight:900">← {{ $business->name }}</a>
            <h1>{{ __('business.catalog.heading') }}</h1>
            <p class="muted">{{ __('business.catalog.intro') }}</p>
        </div>
        @if($canUsePlanner && $business->contextBinding?->context)
            <a class="btn light" href="{{ route('planner.index',['context'=>$business->contextBinding->context->uuid]) }}">{{ __('business.catalog.routines') }}</a>
        @endif
    </header>

    @if($canManage)
        <section class="panel">
            <h2 style="margin-top:0">{{ __('business.catalog.taxonomy') }}</h2>
            <p class="muted">{{ __('business.catalog.taxonomy_help') }}</p>
            <form method="POST" action="{{ route('businesses.catalog.categories.store',$business) }}">
                @csrf
                <div class="grid">
                    <label><span>{{ __('business.catalog.category_name') }}</span><input name="name" required maxlength="180"></label>
                    <label><span>{{ __('business.catalog.slug_optional') }}</span><input name="slug" maxlength="180"></label>
                    <label><span>{{ __('business.catalog.parent_category') }}</span>
                        <select name="parent_id">
                            <option value="">{{ __('business.catalog.root') }}</option>
                            @foreach($business->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <button class="btn light" style="margin-top:12px">＋ {{ __('business.catalog.add_category') }}</button>
            </form>
        </section>

        <section class="panel">
            <h2 style="margin-top:0">＋ {{ __('business.catalog.new_listing') }}</h2>
            <p class="muted">{{ __('business.catalog.new_listing_help') }}</p>
            <form method="POST" action="{{ route('businesses.catalog.listings.store',$business) }}">
                @csrf
                <div class="grid">
                    <label><span>{{ __('business.catalog.listing_type') }}</span>
                        <select name="listing_type" required>
                            <option value="good">{{ __('business.catalog.types.good') }}</option>
                            <option value="service">{{ __('business.catalog.types.service') }}</option>
                            <option value="property">{{ __('business.catalog.types.property') }}</option>
                            <option value="other">{{ __('business.catalog.types.other') }}</option>
                        </select>
                    </label>
                    <label><span>{{ __('business.catalog.category') }}</span>
                        <select name="category_id">
                            <option value="">{{ __('business.catalog.no_category') }}</option>
                            @foreach($business->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label><span>{{ __('business.catalog.visibility') }}</span>
                        <select name="visibility"><option value="private">{{ __('business.catalog.visibilities.private') }}</option><option value="members">{{ __('business.catalog.visibilities.members') }}</option><option value="public">{{ __('business.catalog.visibilities.public') }}</option></select>
                    </label>
                </div>
                <label style="display:block;margin-top:12px"><span>{{ __('business.catalog.listing_title') }}</span><input name="title" required maxlength="220"></label>
                <label style="display:block;margin-top:12px"><span>{{ __('business.catalog.short_description') }}</span><input name="short_description" maxlength="500"></label>
                <label style="display:block;margin-top:12px"><span>{{ __('business.catalog.description') }}</span><textarea name="description" rows="4"></textarea></label>
                <button class="btn primary" style="margin-top:12px">{{ __('business.catalog.create_draft') }}</button>
            </form>
        </section>
    @endif

    <section class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
            <div><h2 style="margin:0">{{ __('business.catalog.listings') }}</h2><p class="muted" style="margin:5px 0 0">{{ __('business.catalog.versions_help') }}</p></div>
            <span class="badge">{{ $business->listings->count() }}</span>
        </div>
        <div class="listings">
            @forelse($business->listings as $listing)
                @php($version = $listing->currentVersion)
                <article class="listing">
                    <div class="badges">
                        <span class="badge">{{ $listing->listing_type }}</span>
                        <span class="badge">{{ $listing->status }}</span>
                        <span class="badge">{{ $listing->visibility }}</span>
                        @if($listing->category)<span class="badge">{{ $listing->category->name }}</span>@endif
                    </div>
                    <h3>{{ $version?->title ?? __('business.catalog.untitled') }}</h3>
                    @if($version?->short_description)<p class="muted">{{ $version->short_description }}</p>@endif

                    @if($version?->propertyDetails)
                        <div class="badges">
                            @if($version->propertyDetails->transaction_mode)<span class="badge">{{ $version->propertyDetails->transaction_mode }}</span>@endif
                            @if($version->propertyDetails->property_class)<span class="badge">{{ $version->propertyDetails->property_class }}</span>@endif
                            @if($version->propertyDetails->construction_area)<span class="badge">{{ $version->propertyDetails->construction_area }} m²</span>@endif
                            @if($version->propertyDetails->bedrooms !== null)<span class="badge">{{ $version->propertyDetails->bedrooms }} {{ __('business.catalog.bed') }}</span>@endif
                        </div>
                    @endif

                    <div class="prices">
                        @foreach($listing->prices->take(4) as $price)
                            <div class="price">
                                <strong>{{ $price->price_type }}</strong>:
                                {{ \App\Support\MoneyAmount::format((int)$price->amount_minor, (int)$price->monetaryUnit->exponent) }}
                                {{ $price->monetaryUnit->code }}
                                @if($price->basis) / {{ $price->basis }} @endif
                                <span class="badge">{{ $price->visibility }}</span>
                                @if($price->reason)<div class="muted" style="font-size:12px">{{ $price->reason }}</div>@endif
                            </div>
                        @endforeach
                    </div>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                        @if($canManage)
                            <a class="btn primary" href="{{ route('businesses.catalog.listings.edit',[$business,$listing]) }}">
                                {{ __('business.catalog.manage_listing') }}
                            </a>
                        @endif
                        <a class="btn light" href="{{ route('businesses.catalog.listings.preview',[$business,$listing]) }}">
                            {{ __('business_listing.preview') }}
                        </a>
                    </div>
                    @if($listing->publishedVersion)
                        <div style="margin-top:13px;color:#047857;font-weight:850">✓ {{ __('business.catalog.published_version') }} #{{ $listing->publishedVersion->version_number }}</div>
                        @php($marketOffer = $marketOffersByListing->get($listing->uuid))
                        @if($marketOffer && $canUseMarket)
                            <div style="margin-top:10px;padding:10px 12px;border-radius:13px;background:#ecfdf5;color:#065f46">
                                <strong>{{ __('business.catalog.active_market') }}</strong>
                                <a href="{{ route('intents.matches',$marketOffer) }}" style="margin-inline-start:8px;color:inherit;font-weight:900">
                                    {{ __('business.catalog.review_matches') }}
                                </a>
                            </div>
                        @elseif($canManage && $canUseMarket)
                            <form method="POST" action="{{ route('businesses.catalog.listings.market.store',[$business,$listing]) }}" style="margin-top:12px;padding-top:12px;border-top:1px dashed #cbd5e1">
                                @csrf
                                <label>
                                    <span>{{ __('business.catalog.market_concept_optional') }}</span>
                                    <input name="concept_label" placeholder="{{ __('business.catalog.market_concept_placeholder') }}">
                                </label>
                                <button class="btn green" style="margin-top:8px">
                                    {{ __('business.catalog.publish_market') }}
                                </button>
                            </form>
                        @endif
                    @endif
                </article>
            @empty
                <div class="empty">{{ __('business.catalog.empty') }}</div>
            @endforelse
        </div>
    </section>

    @if($business->kind === 'real_estate' && $business->publicIntakePortals->isNotEmpty())
        <section class="panel">
            <h2 style="margin-top:0">{{ __('business.catalog.real_estate_channels') }}</h2>
            <p class="muted">{{ __('business.catalog.real_estate_help') }}</p>
            @foreach($business->publicIntakePortals as $portal)
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                    <a class="btn light" href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}">{{ __('business.catalog.office_cases') }}</a>
                    @if($business->visibility === 'public')
                        <a class="btn light" target="_blank" rel="noopener" href="{{ route('public.businesses.show', ['business' => $business->slug]) }}">{{ __('business.catalog.public_website') }}</a>
                    @endif
                </div>
            @endforeach
        </section>
    @endif
</main>
@endsection
