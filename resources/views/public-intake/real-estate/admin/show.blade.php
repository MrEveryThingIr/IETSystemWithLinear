<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $case->reference_code }}</title>
    @vite('resources/css/app.css')
    <style>
        body{background:#f1f5f9;color:#0f172a}.wrap{max-width:1050px;margin:auto;padding:28px 16px}
        .panel{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:22px;box-shadow:0 5px 20px rgba(15,23,42,.05)}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:15px}
        .item small{display:block;color:#64748b;font-weight:800}.item b{display:block;margin-top:6px}
        select{border:1.5px solid #94a3b8!important;background:#fff!important;border-radius:12px;padding:10px}
        button,.btn{border:0;border-radius:12px;padding:10px 14px;font-weight:900;text-decoration:none}.primary{background:#047857;color:#fff}
        @media(max-width:800px){.grid{grid-template-columns:1fr 1fr}}@media(max-width:520px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="wrap">
    <a href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}" style="color:#047857;font-weight:900;text-decoration:none">← بازگشت به دفتر پرونده‌ها</a>
    <div style="display:flex;justify-content:space-between;gap:15px;align-items:end;flex-wrap:wrap;margin:14px 0 20px">
        <div><h1 style="margin:0;font-size:32px">{{ $case->contact_name }}</h1><div style="font-family:monospace;color:#64748b;margin-top:5px">{{ $case->reference_code }}</div></div>
        @if($canManage)
            <form method="POST" action="{{ route('office.real-estate.status',['portal'=>$portal->uuid,'case'=>$case]) }}">
                @csrf @method('PATCH')
                <select name="status">
                    @foreach(['new'=>'جدید','contacted'=>'تماس گرفته شد','qualified'=>'واجد شرایط','in_progress'=>'در حال پیگیری','closed'=>'بسته‌شده','rejected'=>'ردشده'] as $v=>$t)
                        <option value="{{ $v }}" @selected($case->status===$v)>{{ $t }}</option>
                    @endforeach
                </select>
                <button class="primary">ذخیره وضعیت</button>
            </form>
        @endif
    </div>

    @php
        $items=[
            'تلفن'=>$case->phone,
            'نوع پرونده'=>$case->intent==='offer'?'عرضه ملک':'متقاضی ملک',
            'نوع معامله'=>$case->transaction_mode==='sale'?'خرید / فروش':'رهن / اجاره',
            'نوع ملک'=>$case->property_subtype ?: $case->property_class,
            'محله / محدوده'=>$case->public_area,
            'آدرس دقیق'=>$case->exact_address,
            'مساحت زمین'=>$case->land_area ? $case->land_area.' متر²' : null,
            'زیربنا'=>$case->construction_area ? $case->construction_area.' متر²' : null,
            'سال ساخت'=>$case->built_year,
            'سن تقریبی بنا'=>$case->building_age_years !== null ? $case->building_age_years.' سال' : null,
            'وضعیت بنا'=>$case->building_condition,
            'تعداد خواب'=>$case->bedrooms,
            'گرمایش'=>$case->heating_system,
            'سرمایش'=>$case->cooling_system,
            'پارکینگ'=>$case->has_parking===null?null:($case->has_parking?'دارد':'ندارد'),
            'نوع پارکینگ'=>$case->parking_type,
            'قیمت فروش'=>$case->asking_price ? number_format((float)$case->asking_price).' تومان' : null,
            'رهن'=>$case->deposit_amount ? number_format((float)$case->deposit_amount).' تومان' : null,
            'اجاره ماهانه'=>$case->monthly_rent_amount ? number_format((float)$case->monthly_rent_amount).' تومان' : null,
        ];
    @endphp

    <section class="panel">
        <div class="grid">
            @foreach($items as $title=>$value)
                @if($value!==null && $value!=='')
                    <div class="item"><small>{{ $title }}</small><b>{{ $value }}</b></div>
                @endif
            @endforeach
        </div>
        @if($case->notes)
            <div class="item" style="margin-top:14px"><small>توضیحات</small><b style="white-space:pre-line;line-height:1.9">{{ $case->notes }}</b></div>
        @endif
    </section>
    @include('public-intake.real-estate.admin.partials.media')
</main>
</body>
</html>
