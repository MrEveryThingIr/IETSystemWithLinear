@extends('layouts.app')
@section('title', __('business.index.title'))

@section('content')
<style>
*{box-sizing:border-box}body{margin:0;background:#f6f8fc;color:#172033;font-family:inherit}
.wrap{max-width:1180px;margin:auto;padding:30px 16px 60px}.hero{border-radius:32px;padding:32px;background:linear-gradient(125deg,#0f766e,#0ea5e9 55%,#6366f1);color:#fff;box-shadow:0 22px 60px rgba(14,116,144,.2)}
.hero h1{font-size:35px;margin:5px 0 10px;font-weight:950}.hero p{font-size:17px;line-height:2;margin:0;max-width:780px}.top{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.btn{display:inline-flex;text-decoration:none;border:0;border-radius:16px;padding:13px 18px;font-size:15px;font-weight:950;cursor:pointer}.btn-white{background:#fff;color:#0f766e}.btn-primary{background:#4f46e5;color:#fff}.btn-light{background:#fff;color:#334155;border:1px solid #cbd5e1}
.filters{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}.filter{padding:9px 13px;border-radius:999px;background:#fff;border:1px solid #d8dee9;text-decoration:none;color:#334155;font-weight:850}.filter.active{background:#172033;color:#fff;border-color:#172033}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:22px}.card{background:#fff;border:1px solid #e4e7ec;border-radius:25px;padding:22px;box-shadow:0 8px 25px rgba(15,23,42,.05)}
.kind{display:inline-flex;padding:6px 10px;border-radius:999px;background:#e0f2fe;color:#075985;font-weight:900;font-size:13px}.card h2{font-size:23px;margin:14px 0 5px}.muted{color:#667085;line-height:1.8}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;margin:18px 0}.stat{background:#f8fafc;border-radius:15px;padding:10px;text-align:center}.stat strong{font-size:20px;display:block}.stat span{font-size:12px;color:#667085}
.actions{display:flex;gap:8px;flex-wrap:wrap}.empty{background:#fff;border:2px dashed #cbd5e1;border-radius:24px;padding:42px;text-align:center;margin-top:24px}.empty h2{font-size:25px}
@media(max-width:650px){.stats{grid-template-columns:repeat(2,1fr)}.hero{padding:24px}.hero h1{font-size:29px}}
</style>
<main class="wrap">
    <header class="hero">
        <div class="top">
            <div>
                <div style="font-weight:900;opacity:.9">{{ __('business.index.kicker') }}</div>
                <h1>{{ __('business.index.title') }}</h1>
                <p>{{ __('business.index.intro') }}</p>
            </div>
            <a class="btn btn-white" href="{{ route('businesses.create') }}">＋ {{ __('business.index.create') }}</a>
        </div>
    </header>

    <nav class="filters" aria-label="{{ __('business.index.filter_label') }}">
        <a class="filter {{ request('kind') ? '' : 'active' }}" href="{{ route('businesses.index') }}">{{ __('business.index.all') }}</a>
        @foreach($kindLabels as $kind => $label)
            <a class="filter {{ request('kind') === $kind ? 'active' : '' }}" href="{{ route('businesses.index', ['kind' => $kind]) }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if($businesses->isEmpty())
        <section class="empty">
            <div style="font-size:50px">🏪</div>
            <h2>{{ __('business.index.empty_title') }}</h2>
            <p class="muted">{{ __('business.index.empty_help') }}</p>
            <a class="btn btn-primary" href="{{ route('businesses.create') }}">{{ __('business.index.empty_cta') }}</a>
        </section>
    @else
        <section class="cards">
            @foreach($businesses as $business)
                <article class="card">
                    <span class="kind">{{ $kindLabels[$business->kind] ?? $business->kind }}</span>
                    <h2>{{ $business->name }}</h2>
                    <div class="muted">{{ $business->short_intro ?: __('business.index.no_intro') }}</div>
                    <div class="stats">
                        <div class="stat"><strong>{{ $business->active_members_count }}</strong><span>{{ __('business.index.team') }}</span></div>
                        <div class="stat"><strong>{{ $business->business_contacts_count }}</strong><span>{{ __('business.index.clients') }}</span></div>
                        <div class="stat"><strong>{{ $business->listings_count }}</strong><span>{{ __('business.index.listings') }}</span></div>
                        <div class="stat"><strong>{{ $business->public_intake_portals_count }}</strong><span>{{ __('business.index.intake_channels') }}</span></div>
                    </div>
                    <div class="actions">
                        <a class="btn btn-primary" href="{{ route('businesses.show',$business) }}">{{ __('business.index.open') }}</a>
                        <a class="btn btn-light" href="{{ route('businesses.catalog.index',$business) }}">{{ __('business.index.catalog') }}</a>
                        @if($business->visibility === 'public')
                            <a class="btn btn-light" target="_blank" rel="noopener" href="{{ route('public.businesses.show', ['business' => $business->slug]) }}">{{ __('public_business.footer') }}</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
    @endif
</main>
@endsection
