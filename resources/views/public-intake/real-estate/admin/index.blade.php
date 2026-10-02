@extends('layouts.app')

@section('title', app()->getLocale() === 'fa' ? 'دفتر املاک' : 'Real Estate office')

@section('content')
@php
    $fa = app()->getLocale() === 'fa';
    $statusText = $fa
        ? ['new'=>'جدید','contacted'=>'تماس گرفته شد','qualified'=>'واجد شرایط','in_progress'=>'در حال پیگیری','closed'=>'بسته‌شده','rejected'=>'ردشده']
        : ['new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','in_progress'=>'In progress','closed'=>'Closed','rejected'=>'Rejected'];
@endphp
<style>
    .re-wrap{max-width:1450px;margin:auto;padding:10px 0 28px}.re-top{display:flex;justify-content:space-between;gap:15px;align-items:end;flex-wrap:wrap}
    .re-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:22px 0}.re-card,.re-filters,.re-tablebox{background:#fff;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 5px 20px rgba(15,23,42,.04)}
    .dark .re-card,.dark .re-filters,.dark .re-tablebox{background:#18181b;border-color:#3f3f46}.re-card{padding:18px}.re-card b{font-size:30px;display:block;margin-top:7px}.re-filters{padding:18px;margin-bottom:18px}
    .re-fgrid{display:grid;grid-template-columns:2fr repeat(3,1fr) auto;gap:10px;align-items:end}.re-filters input,.re-filters select{width:100%;box-sizing:border-box;border:1.5px solid #94a3b8!important;background:white!important;color:#0f172a!important;border-radius:12px;padding:11px 12px}
    .re-filters label span{display:block;font-size:13px;font-weight:800;margin-bottom:6px;color:#475569}.re-btn{border:0;border-radius:12px;padding:11px 16px;font-weight:900;cursor:pointer;text-decoration:none;display:inline-block}.re-primary{background:#047857;color:#fff}.re-dark{background:#0f172a;color:#fff}.re-light{background:#fff;color:#334155;border:1px solid #cbd5e1}
    .re-tablebox{overflow:hidden}.re-scroll{overflow:auto}.re-table{width:100%;border-collapse:collapse;min-width:1050px}.re-table th{background:#f8fafc;text-align:start;font-size:12px;color:#475569;padding:14px;border-bottom:1px solid #e2e8f0}.re-table td{padding:14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}.re-table tr:hover td{background:#f0fdf4;color:#0f172a}.re-badge{display:inline-block;border-radius:999px;padding:5px 10px;background:#f1f5f9;color:#0f172a;font-size:12px;font-weight:800}.re-pager{padding:16px}
    @media(max-width:900px){.re-cards{grid-template-columns:repeat(2,1fr)}.re-fgrid{grid-template-columns:1fr 1fr}}@media(max-width:600px){.re-cards,.re-fgrid{grid-template-columns:1fr}}
</style>
<main class="re-wrap">
    <div class="re-top">
        <div>
            <div style="color:#047857;font-weight:900">{{ $fa ? 'دفتر املاک' : 'Real Estate office' }}</div>
            <h1 style="font-size:32px;margin:5px 0">{{ $fa ? 'پرونده‌های' : 'Cases for' }} {{ $portal->title }}</h1>
            <p style="color:#64748b;margin:0">{{ $fa ? 'جست‌وجو، فیلتر و پیگیری پرونده‌های ثبت‌شده' : 'Search, filter, and follow submitted cases.' }}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if($business)
                <a class="re-btn re-light" href="{{ route('businesses.show',$business) }}">{{ $fa ? 'باز کردن کسب‌وکار' : 'Open business' }}</a>
                <a class="re-btn re-light" href="{{ route('businesses.catalog.index',$business) }}">{{ $fa ? 'کاتالوگ کسب‌وکار' : 'Business catalog' }}</a>
            @elseif($canManage)
                <form method="POST" action="{{ route('office.real-estate.adopt-business',['portal'=>$portal->uuid]) }}">
                    @csrf
                    <button class="re-btn re-primary">{{ $fa ? 'انتقال این دفتر به Business' : 'Adopt this office into Business' }}</button>
                </form>
            @endif
            <a class="re-btn re-light" target="_blank" rel="noopener" href="{{ route('public.real-estate.show', $portal) }}">{{ $fa ? 'باز کردن فرم عمومی' : 'Open public form' }}</a>
        </div>
    </div>
    <section class="re-cards">
        <div class="re-card">{{ $fa ? 'همه پرونده‌ها' : 'All cases' }} <b>{{ number_format($stats['total']) }}</b></div>
        <div class="re-card">{{ $fa ? 'جدید' : 'New' }} <b>{{ number_format($stats['new']) }}</b></div>
        <div class="re-card">{{ $fa ? 'عرضه ملک' : 'Property offers' }} <b>{{ number_format($stats['offers']) }}</b></div>
        <div class="re-card">{{ $fa ? 'متقاضی' : 'Property needs' }} <b>{{ number_format($stats['needs']) }}</b></div>
    </section>
    <form method="GET" class="re-filters">
        <div class="re-fgrid">
            <label><span>{{ $fa ? 'جست‌وجو' : 'Search' }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ $fa ? 'نام، تلفن، کد، محله، آدرس...' : 'Name, phone, code, area, address...' }}"></label>
            <label><span>{{ $fa ? 'نوع پرونده' : 'Case type' }}</span><select name="intent"><option value="">{{ $fa ? 'همه' : 'All' }}</option><option value="offer" @selected(request('intent')==='offer')>{{ $fa ? 'عرضه' : 'Offer' }}</option><option value="need" @selected(request('intent')==='need')>{{ $fa ? 'تقاضا' : 'Need' }}</option></select></label>
            <label><span>{{ $fa ? 'معامله' : 'Transaction' }}</span><select name="transaction_mode"><option value="">{{ $fa ? 'همه' : 'All' }}</option><option value="sale" @selected(request('transaction_mode')==='sale')>{{ $fa ? 'خرید/فروش' : 'Buy / sell' }}</option><option value="rent" @selected(request('transaction_mode')==='rent')>{{ $fa ? 'رهن/اجاره' : 'Rent / lease' }}</option></select></label>
            <label><span>{{ $fa ? 'وضعیت' : 'Status' }}</span><select name="status"><option value="">{{ $fa ? 'همه' : 'All' }}</option>@foreach($statusText as $v=>$t)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $t }}</option>@endforeach</select></label>
            <div style="display:flex;gap:7px"><button class="re-btn re-primary">{{ $fa ? 'اعمال' : 'Apply' }}</button><a class="re-btn re-light" href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}">{{ $fa ? 'پاک' : 'Clear' }}</a></div>
        </div>
    </form>
    <section class="re-tablebox"><div class="re-scroll"><table class="re-table"><thead><tr>
        <th>{{ $fa ? 'پرونده' : 'Case' }}</th><th>{{ $fa ? 'مراجعه‌کننده' : 'Contact' }}</th><th>{{ $fa ? 'نوع' : 'Type' }}</th><th>{{ $fa ? 'ملک / محدوده' : 'Property / area' }}</th><th>{{ $fa ? 'متراژ' : 'Area' }}</th><th>{{ $fa ? 'قیمت' : 'Price' }}</th><th>{{ $fa ? 'وضعیت' : 'Status' }}</th><th></th>
    </tr></thead><tbody>
        @forelse($cases as $case)
            <tr>
                <td><b style="font-family:monospace">{{ $case->reference_code }}</b><br><small style="color:#94a3b8"><x-app.local-datetime :value="$case->created_at" /></small></td>
                <td><b>{{ $case->contact_name }}</b><br><span dir="ltr" style="color:#64748b">{{ $case->phone }}</span></td>
                <td><span class="re-badge">{{ $case->intent==='offer' ? ($fa ? 'عرضه' : 'Offer') : ($fa ? 'تقاضا' : 'Need') }}</span><br><small>{{ $case->transaction_mode==='sale' ? ($fa ? 'خرید/فروش' : 'Buy / sell') : ($fa ? 'رهن/اجاره' : 'Rent / lease') }}</small></td>
                <td><b>{{ $case->property_subtype ?: $case->property_class }}</b><br><small style="color:#64748b">{{ $case->public_area ?: '—' }}</small></td>
                <td>{{ $case->land_area ?: '—' }} m²</td>
                <td>@if($case->asking_price){{ number_format((float)$case->asking_price) }} {{ $fa ? 'تومان' : 'toman' }}@elseif($case->deposit_amount || $case->monthly_rent_amount){{ $fa ? 'رهن' : 'Deposit' }} {{ number_format((float)($case->deposit_amount ?? 0)) }}<br><small>{{ $fa ? 'اجاره' : 'Rent' }} {{ number_format((float)($case->monthly_rent_amount ?? 0)) }}</small>@else — @endif</td>
                <td>
                    <span class="re-badge">{{ $statusText[$case->status] ?? $case->status }}</span>
                    @if($case->businessListing)
                        <br><small style="color:#047857;font-weight:900">{{ $fa ? 'در کاتالوگ' : 'In catalog' }}</small>
                    @endif
                </td>
                <td><a class="re-btn re-dark" href="{{ route('office.real-estate.show',['portal'=>$portal->uuid,'case'=>$case]) }}">{{ $fa ? 'مشاهده' : 'View' }}</a></td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;padding:50px"><b>{{ $fa ? 'پرونده‌ای پیدا نشد' : 'No cases found' }}</b></td></tr>
        @endforelse
    </tbody></table></div>@if($cases->hasPages())<div class="re-pager">{{ $cases->links() }}</div>@endif</section>
</main>
@endsection
