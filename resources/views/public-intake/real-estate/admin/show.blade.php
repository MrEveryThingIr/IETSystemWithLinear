@extends('layouts.app')

@section('title', __('public_real_estate.office.case_title'))

@section('content')
@php
    $statusText = __('public_real_estate.office.statuses');
    $items = [
        __('public_real_estate.office.fields.phone') => $case->phone,
        __('public_real_estate.office.fields.case_type') => $case->intent === 'offer'
            ? __('public_real_estate.office.fields.property_offer')
            : __('public_real_estate.office.fields.property_need'),
        __('public_real_estate.office.fields.transaction') => $case->transaction_mode === 'sale'
            ? __('public_real_estate.office.fields.sale')
            : __('public_real_estate.office.fields.rent'),
        __('public_real_estate.office.fields.property_type') => $case->property_subtype ?: $case->property_class,
        __('public_real_estate.office.fields.area') => $case->public_area,
        __('public_real_estate.office.fields.exact_address') => $case->exact_address,
        __('public_real_estate.office.fields.land_area') => $case->land_area
            ? $case->land_area.' '.__('public_real_estate.office.fields.square_meter')
            : null,
        __('public_real_estate.office.fields.construction_area') => $case->construction_area
            ? $case->construction_area.' '.__('public_real_estate.office.fields.square_meter')
            : null,
        __('public_real_estate.office.fields.built_year') => $case->built_year,
        __('public_real_estate.office.fields.building_age') => $case->building_age_years !== null
            ? $case->building_age_years.' '.__('public_real_estate.office.fields.years')
            : null,
        __('public_real_estate.office.fields.condition') => $case->building_condition,
        __('public_real_estate.office.fields.bedrooms') => $case->bedrooms,
        __('public_real_estate.office.fields.heating') => $case->heating_system,
        __('public_real_estate.office.fields.cooling') => $case->cooling_system,
        __('public_real_estate.office.fields.parking') => $case->has_parking === null
            ? null
            : ($case->has_parking
                ? __('public_real_estate.office.fields.yes')
                : __('public_real_estate.office.fields.no')),
        __('public_real_estate.office.fields.parking_type') => $case->parking_type,
        __('public_real_estate.office.fields.sale_price') => $case->asking_price
            ? number_format((float) $case->asking_price).' '.__('public_real_estate.office.fields.toman')
            : null,
        __('public_real_estate.office.fields.deposit') => $case->deposit_amount
            ? number_format((float) $case->deposit_amount).' '.__('public_real_estate.office.fields.toman')
            : null,
        __('public_real_estate.office.fields.monthly_rent') => $case->monthly_rent_amount
            ? number_format((float) $case->monthly_rent_amount).' '.__('public_real_estate.office.fields.toman')
            : null,
    ];
@endphp
<style>
    .re-case-wrap{max-width:1050px;margin:auto;padding:10px 0 28px}.re-panel{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:22px;box-shadow:0 5px 20px rgba(15,23,42,.05)}.re-bridge{margin:0 0 18px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:18px;padding:14px 16px;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.re-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.re-actions form{margin:0}.dark .re-panel{background:#18181b;border-color:#3f3f46}.re-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.re-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:15px;color:#0f172a}.re-item small{display:block;color:#64748b;font-weight:800}.re-item b{display:block;margin-top:6px}.re-case-wrap select{border:1.5px solid #94a3b8!important;background:#fff!important;color:#0f172a!important;border-radius:12px;padding:10px}.re-btn{border:0;border-radius:12px;padding:10px 14px;font-weight:900;text-decoration:none}.re-primary{background:#047857;color:#fff}@media(max-width:800px){.re-grid{grid-template-columns:1fr 1fr}}@media(max-width:520px){.re-grid{grid-template-columns:1fr}}
</style>
<main class="re-case-wrap">
    <a href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}" style="color:#047857;font-weight:900;text-decoration:none">← {{ __('public_real_estate.office.case.back') }}</a>
    <div style="display:flex;justify-content:space-between;gap:15px;align-items:end;flex-wrap:wrap;margin:14px 0 20px">
        <div><h1 style="margin:0;font-size:32px">{{ $case->contact_name }}</h1><div style="font-family:monospace;color:#64748b;margin-top:5px">{{ $case->reference_code }}</div></div>
        <div class="re-actions">
            @if($canManage)<form method="POST" action="{{ route('office.real-estate.status',['portal'=>$portal->uuid,'case'=>$case]) }}">@csrf @method('PATCH')<select name="status">@foreach($statusText as $v=>$t)<option value="{{ $v }}" @selected($case->status===$v)>{{ $t }}</option>@endforeach</select> <button class="re-btn re-primary">{{ __('public_real_estate.office.case.save_status') }}</button></form>@endif
        </div>
    </div>

    @if($business || $canManage)
        <section class="re-bridge">
            <div>
                <strong>
                    {{ $business
                        ? __('public_real_estate.office.bridge_business', ['name' => $business->name])
                        : __('public_real_estate.office.case.legacy_title') }}
                </strong>
                <div style="color:#475569;margin-top:4px">
                    {{ $business
                        ? __('public_real_estate.office.case.business_help')
                        : __('public_real_estate.office.case.legacy_help') }}
                </div>
            </div>
            <div class="re-actions">
                @if($business)
                    @if($canOperateBusiness)
                        <a class="re-btn" style="background:#fff;color:#4338ca;border:1px solid #c7d2fe" href="{{ route('businesses.show',$business) }}">{{ __('public_real_estate.office.case.open_business') }}</a>
                    @endif
                    @if($case->businessListing && $canOperateBusiness)
                        <a class="re-btn" style="background:#ecfdf5;color:#047857" href="{{ route('businesses.catalog.index',$business) }}">{{ __('public_real_estate.office.case.view_catalog') }}</a>
                    @elseif(! $case->businessListing && $canManageBusiness && $case->intent === 'offer')
                        <form method="POST" action="{{ route('businesses.real-estate.cases.promote',[$business,$portal,$case]) }}">
                            @csrf
                            <button class="re-btn" style="background:#4f46e5;color:#fff">{{ __('public_real_estate.office.case.add_catalog') }}</button>
                        </form>
                    @endif
                @elseif($canManage && $canUseBusinessSurface)
                    <form method="POST" action="{{ route('office.real-estate.adopt-business',['portal'=>$portal->uuid]) }}">
                        @csrf
                        <button class="re-btn" style="background:#4f46e5;color:#fff">{{ __('public_real_estate.office.case.register_business') }}</button>
                    </form>
                @endif
            </div>
        </section>
    @endif
    <section class="re-panel"><div class="re-grid">@foreach($items as $title=>$value)@if($value!==null && $value!=='')<div class="re-item"><small>{{ $title }}</small><b>{{ $value }}</b></div>@endif @endforeach</div>@if($case->notes)<div class="re-item" style="margin-top:14px"><small>{{ __('public_real_estate.office.case.notes') }}</small><b style="white-space:pre-line;line-height:1.9">{{ $case->notes }}</b></div>@endif</section>
    @include('public-intake.real-estate.admin.partials.media')
</main>
@endsection
