<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $fa = app()->getLocale() === 'fa';
        $statusText = $fa
            ? ['new'=>'جدید','contacted'=>'تماس گرفته شد','qualified'=>'واجد شرایط','in_progress'=>'در حال پیگیری','closed'=>'بسته‌شده','rejected'=>'ردشده']
            : ['new'=>'New','contacted'=>'Contacted','qualified'=>'Qualified','in_progress'=>'In progress','closed'=>'Closed','rejected'=>'Rejected'];
        $items = [
            ($fa ? 'تلفن' : 'Phone') => $case->phone,
            ($fa ? 'نوع پرونده' : 'Case type') => $case->intent==='offer' ? ($fa ? 'عرضه ملک' : 'Property offer') : ($fa ? 'متقاضی ملک' : 'Property need'),
            ($fa ? 'نوع معامله' : 'Transaction') => $case->transaction_mode==='sale' ? ($fa ? 'خرید / فروش' : 'Buy / sell') : ($fa ? 'رهن / اجاره' : 'Rent / lease'),
            ($fa ? 'نوع ملک' : 'Property type') => $case->property_subtype ?: $case->property_class,
            ($fa ? 'محله / محدوده' : 'Area') => $case->public_area,
            ($fa ? 'آدرس دقیق' : 'Exact address') => $case->exact_address,
            ($fa ? 'مساحت زمین' : 'Land area') => $case->land_area ? $case->land_area.' m²' : null,
            ($fa ? 'زیربنا' : 'Construction area') => $case->construction_area ? $case->construction_area.' m²' : null,
            ($fa ? 'سال ساخت' : 'Built year') => $case->built_year,
            ($fa ? 'سن تقریبی بنا' : 'Approx. building age') => $case->building_age_years !== null ? $case->building_age_years.' '.($fa ? 'سال' : 'years') : null,
            ($fa ? 'وضعیت بنا' : 'Building condition') => $case->building_condition,
            ($fa ? 'تعداد خواب' : 'Bedrooms') => $case->bedrooms,
            ($fa ? 'گرمایش' : 'Heating') => $case->heating_system,
            ($fa ? 'سرمایش' : 'Cooling') => $case->cooling_system,
            ($fa ? 'پارکینگ' : 'Parking') => $case->has_parking===null ? null : ($case->has_parking ? ($fa ? 'دارد' : 'Yes') : ($fa ? 'ندارد' : 'No')),
            ($fa ? 'نوع پارکینگ' : 'Parking type') => $case->parking_type,
            ($fa ? 'قیمت فروش' : 'Sale price') => $case->asking_price ? number_format((float)$case->asking_price).' '.($fa ? 'تومان' : 'toman') : null,
            ($fa ? 'رهن' : 'Deposit') => $case->deposit_amount ? number_format((float)$case->deposit_amount).' '.($fa ? 'تومان' : 'toman') : null,
            ($fa ? 'اجاره ماهانه' : 'Monthly rent') => $case->monthly_rent_amount ? number_format((float)$case->monthly_rent_amount).' '.($fa ? 'تومان' : 'toman') : null,
        ];
    @endphp
    <title>{{ $case->reference_code }}</title>
    @vite('resources/css/app.css')
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#0f172a;font-family:inherit}.wrap{max-width:1050px;margin:auto;padding:28px 16px 60px}
        .panel,.re-panel{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:22px;box-shadow:0 5px 20px rgba(15,23,42,.05)}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:15px}
        .item small{display:block;color:#64748b;font-weight:800}.item b{display:block;margin-top:6px}.actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.actions form{margin:0}
        select{border:1.5px solid #94a3b8!important;background:#fff!important;color:#0f172a!important;border-radius:12px;padding:10px}
        button,.btn,.re-btn{border:0;border-radius:12px;padding:10px 14px;font-weight:900;text-decoration:none;cursor:pointer}.primary,.re-primary{background:#047857;color:#fff}.light{background:#fff;color:#334155;border:1px solid #cbd5e1}.indigo{background:#4f46e5;color:#fff}
        .bridge{margin:0 0 18px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:18px;padding:14px 16px;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
        @media(max-width:800px){.grid{grid-template-columns:1fr 1fr}}@media(max-width:520px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="wrap">
    <a href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}" style="color:#047857;font-weight:900;text-decoration:none">← {{ $fa ? 'بازگشت به دفتر پرونده‌ها' : 'Back to case office' }}</a>
    <div style="display:flex;justify-content:space-between;gap:15px;align-items:end;flex-wrap:wrap;margin:14px 0 20px">
        <div><h1 style="margin:0;font-size:32px">{{ $case->contact_name }}</h1><div style="font-family:monospace;color:#64748b;margin-top:5px">{{ $case->reference_code }}</div></div>
        @if($canManage)
            <form method="POST" action="{{ route('office.real-estate.status',['portal'=>$portal->uuid,'case'=>$case]) }}">
                @csrf @method('PATCH')
                <div class="actions">
                    <select name="status">@foreach($statusText as $v=>$t)<option value="{{ $v }}" @selected($case->status===$v)>{{ $t }}</option>@endforeach</select>
                    <button class="primary">{{ $fa ? 'ذخیره وضعیت' : 'Save status' }}</button>
                </div>
            </form>
        @endif
    </div>

    @if($business || $canManage)
        <section class="bridge">
            <div>
                <strong>{{ $fa ? 'پل اختیاری به کسب‌وکار' : 'Optional Business bridge' }}</strong>
                <div style="color:#475569;margin-top:4px">{{ $fa ? 'پرونده املاک مستقل می‌ماند؛ فقط در صورت نیاز آن را به کاتالوگ کسب‌وکار ارتقا دهید.' : 'The Real Estate case remains independent; promote it to the Business catalog only when useful.' }}</div>
            </div>
            <div class="actions">
                @if($business)
                    <a class="btn light" href="{{ route('businesses.show',$business) }}">{{ $fa ? 'کسب‌وکار' : 'Business' }}</a>
                    @if($case->businessListing)
                        <a class="btn light" href="{{ route('businesses.catalog.index',$business) }}">{{ $fa ? 'مشاهده در کاتالوگ' : 'View in catalog' }}</a>
                    @elseif($canManage && $case->intent === 'offer')
                        <form method="POST" action="{{ route('businesses.real-estate.cases.promote',[$business,$portal,$case]) }}">
                            @csrf
                            <button class="btn indigo">{{ $fa ? 'تبدیل به Listing کسب‌وکار' : 'Promote to Business Listing' }}</button>
                        </form>
                    @endif
                @elseif($canManage)
                    <form method="POST" action="{{ route('office.real-estate.adopt-business',['portal'=>$portal->uuid]) }}">
                        @csrf
                        <button class="btn indigo">{{ $fa ? 'اتصال اختیاری دفتر به Business' : 'Optionally connect office to Business' }}</button>
                    </form>
                @endif
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="grid">
            @foreach($items as $title=>$value)
                @if($value!==null && $value!=='')
                    <div class="item"><small>{{ $title }}</small><b>{{ $value }}</b></div>
                @endif
            @endforeach
        </div>
        @if($case->notes)
            <div class="item" style="margin-top:14px"><small>{{ $fa ? 'توضیحات' : 'Notes' }}</small><b style="white-space:pre-line;line-height:1.9">{{ $case->notes }}</b></div>
        @endif
    </section>

    @include('public-intake.real-estate.admin.partials.media')
</main>
</body>
</html>
