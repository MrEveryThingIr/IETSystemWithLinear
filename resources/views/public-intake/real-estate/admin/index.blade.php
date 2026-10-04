@extends('layouts.app')

@section('title', __('public_real_estate.office.title'))

@section('content')
@php($statusText = __('public_real_estate.office.statuses'))
<style>
    .re-wrap{max-width:1450px;margin:auto;padding:10px 0 28px}.re-crumbs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.re-crumbs a{color:#047857;font-weight:900;text-decoration:none}.re-top{display:flex;justify-content:space-between;gap:15px;align-items:end;flex-wrap:wrap}
    .re-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:22px 0}.re-card,.re-filters,.re-tablebox,.re-bridge{background:#fff;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 5px 20px rgba(15,23,42,.04)}.re-bridge{padding:16px 18px;margin:18px 0;display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap}.re-bridge p{margin:4px 0 0;color:#64748b;line-height:1.75}.re-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.re-actions form{margin:0}
    .dark .re-card,.dark .re-filters,.dark .re-tablebox,.dark .re-bridge{background:#18181b;border-color:#3f3f46}.re-card{padding:18px}.re-card b{font-size:30px;display:block;margin-top:7px}.re-filters{padding:18px;margin-bottom:18px}
    .re-fgrid{display:grid;grid-template-columns:2fr repeat(3,1fr) auto;gap:10px;align-items:end}.re-filters input,.re-filters select{width:100%;box-sizing:border-box;border:1.5px solid #94a3b8!important;background:white!important;color:#0f172a!important;border-radius:12px;padding:11px 12px}
    .re-filters label span{display:block;font-size:13px;font-weight:800;margin-bottom:6px;color:#475569}.re-btn{border:0;border-radius:12px;padding:11px 16px;font-weight:900;cursor:pointer;text-decoration:none;display:inline-block}.re-primary{background:#047857;color:#fff}.re-dark{background:#0f172a;color:#fff}.re-light{background:#fff;color:#334155;border:1px solid #cbd5e1}
    .re-tablebox{overflow:hidden}.re-scroll{overflow:auto}.re-table{width:100%;border-collapse:collapse;min-width:1050px}.re-table th{background:#f8fafc;text-align:start;font-size:12px;color:#475569;padding:14px;border-bottom:1px solid #e2e8f0}.re-table td{padding:14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}.re-table tr:hover td{background:#f0fdf4;color:#0f172a}.re-badge{display:inline-block;border-radius:999px;padding:5px 10px;background:#f1f5f9;color:#0f172a;font-size:12px;font-weight:800}.re-pager{padding:16px}
    @media(max-width:900px){.re-cards{grid-template-columns:repeat(2,1fr)}.re-fgrid{grid-template-columns:1fr 1fr}}@media(max-width:600px){.re-cards,.re-fgrid{grid-template-columns:1fr}}
</style>
<main class="re-wrap">
    @if($canUseBusinessSurface || ($business && $canOperateBusiness))
        <nav class="re-crumbs">
            @if($canUseBusinessSurface)
                <a href="{{ route('businesses.index', ['kind' => 'real_estate']) }}">← {{ __('public_real_estate.office.businesses') }}</a>
            @endif
            @if($business && $canOperateBusiness)
                @if($canUseBusinessSurface)<span>·</span>@endif
                <a href="{{ route('businesses.show', $business) }}">{{ $business->name }}</a>
            @endif
        </nav>
    @endif

    <div class="re-top">
        <div>
            <div style="color:#047857;font-weight:900">{{ __('public_real_estate.office.business_prefix') }} {{ $business?->name ?? $portal->title }}</div>
            <h1 style="font-size:32px;margin:5px 0">{{ __('public_real_estate.office.cases_heading') }}</h1>
            <p style="color:#64748b;margin:0">{{ __('public_real_estate.office.cases_help') }}</p>
        </div>
        <div class="re-actions">
            <a class="re-btn re-light" target="_blank" rel="noopener" href="{{ route('public.real-estate.show', $portal) }}">{{ __('public_real_estate.office.open_public_form') }}</a>
        </div>
    </div>

    @if($business || $canManage)
        <section class="re-bridge">
            <div>
                <strong>
                    {{ $business
                        ? __('public_real_estate.office.bridge_business', ['name' => $business->name])
                        : __('public_real_estate.office.migrate_title') }}
                </strong>
                <p>
                    {{ $business
                        ? __('public_real_estate.office.bridge_help')
                        : __('public_real_estate.office.migrate_help') }}
                </p>
            </div>
            <div class="re-actions">
                @if($business && $canOperateBusiness)
                    <a class="re-btn re-light" href="{{ route('businesses.show',$business) }}">{{ __('public_real_estate.office.open_business') }}</a>
                    <a class="re-btn re-light" href="{{ route('businesses.catalog.index',$business) }}">{{ __('public_real_estate.office.catalog') }}</a>
                @elseif(! $business && $canManage && $canUseBusinessSurface)
                    <form method="POST" action="{{ route('office.real-estate.adopt-business',['portal'=>$portal->uuid]) }}">
                        @csrf
                        <button class="re-btn" style="background:#4f46e5;color:#fff">{{ __('public_real_estate.office.register_business') }}</button>
                    </form>
                @endif
            </div>
        </section>
    @endif
    <section class="re-cards">
        <div class="re-card">{{ __('public_real_estate.office.stats.all') }} <b>{{ number_format($stats['total']) }}</b></div>
        <div class="re-card">{{ __('public_real_estate.office.stats.new') }} <b>{{ number_format($stats['new']) }}</b></div>
        <div class="re-card">{{ __('public_real_estate.office.stats.offers') }} <b>{{ number_format($stats['offers']) }}</b></div>
        <div class="re-card">{{ __('public_real_estate.office.stats.needs') }} <b>{{ number_format($stats['needs']) }}</b></div>
    </section>
    <form method="GET" class="re-filters">
        <div class="re-fgrid">
            <label><span>{{ __('public_real_estate.office.filters.search') }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ __('public_real_estate.office.filters.search_placeholder') }}"></label>
            <label><span>{{ __('public_real_estate.office.filters.case_type') }}</span><select name="intent"><option value="">{{ __('public_real_estate.office.filters.all') }}</option><option value="offer" @selected(request('intent')==='offer')>{{ __('public_real_estate.office.filters.offer') }}</option><option value="need" @selected(request('intent')==='need')>{{ __('public_real_estate.office.filters.need') }}</option></select></label>
            <label><span>{{ __('public_real_estate.office.filters.transaction') }}</span><select name="transaction_mode"><option value="">{{ __('public_real_estate.office.filters.all') }}</option><option value="sale" @selected(request('transaction_mode')==='sale')>{{ __('public_real_estate.office.filters.sale') }}</option><option value="rent" @selected(request('transaction_mode')==='rent')>{{ __('public_real_estate.office.filters.rent') }}</option></select></label>
            <label><span>{{ __('public_real_estate.office.filters.status') }}</span><select name="status"><option value="">{{ __('public_real_estate.office.filters.all') }}</option>@foreach($statusText as $v=>$t)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $t }}</option>@endforeach</select></label>
            <div style="display:flex;gap:7px"><button class="re-btn re-primary">{{ __('public_real_estate.office.filters.apply') }}</button><a class="re-btn re-light" href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}">{{ __('public_real_estate.office.filters.clear') }}</a></div>
        </div>
    </form>
    <section class="re-tablebox"><div class="re-scroll"><table class="re-table"><thead><tr>
        <th>{{ __('public_real_estate.office.table.case') }}</th><th>{{ __('public_real_estate.office.table.contact') }}</th><th>{{ __('public_real_estate.office.table.type') }}</th><th>{{ __('public_real_estate.office.table.property_area') }}</th><th>{{ __('public_real_estate.office.table.area') }}</th><th>{{ __('public_real_estate.office.table.price') }}</th><th>{{ __('public_real_estate.office.filters.status') }}</th><th></th>
    </tr></thead><tbody>
        @forelse($cases as $case)
            <tr>
                <td><b style="font-family:monospace">{{ $case->reference_code }}</b><br><small style="color:#94a3b8"><x-app.local-datetime :value="$case->created_at" /></small></td>
                <td><b>{{ $case->contact_name }}</b><br><span dir="ltr" style="color:#64748b">{{ $case->phone }}</span></td>
                <td><span class="re-badge">{{ $case->intent==='offer' ? __('public_real_estate.office.table.offer') : __('public_real_estate.office.table.need') }}</span><br><small>{{ $case->transaction_mode==='sale' ? __('public_real_estate.office.table.sale') : __('public_real_estate.office.table.rent') }}</small></td>
                <td><b>{{ $case->property_subtype ?: $case->property_class }}</b><br><small style="color:#64748b">{{ $case->public_area ?: '—' }}</small></td>
                <td>{{ $case->land_area ?: '—' }} m²</td>
                <td>@if($case->asking_price){{ number_format((float)$case->asking_price) }} {{ __('public_real_estate.office.table.toman') }}@elseif($case->deposit_amount || $case->monthly_rent_amount){{ __('public_real_estate.office.table.deposit') }} {{ number_format((float)($case->deposit_amount ?? 0)) }}<br><small>{{ __('public_real_estate.office.table.monthly_rent') }} {{ number_format((float)($case->monthly_rent_amount ?? 0)) }}</small>@else — @endif</td>
                <td>
                    <span class="re-badge">{{ $statusText[$case->status] ?? $case->status }}</span>
                    @if($case->businessListing)
                        <br><small style="color:#047857;font-weight:900">{{ __('public_real_estate.office.table.in_catalog') }}</small>
                    @endif
                </td>
                <td><a class="re-btn re-dark" href="{{ route('office.real-estate.show',['portal'=>$portal->uuid,'case'=>$case]) }}">{{ __('public_real_estate.office.table.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;padding:50px"><b>{{ __('public_real_estate.office.table.empty') }}</b></td></tr>
        @endforelse
    </tbody></table></div>@if($cases->hasPages())<div class="re-pager">{{ $cases->links() }}</div>@endif</section>
</main>
@endsection
