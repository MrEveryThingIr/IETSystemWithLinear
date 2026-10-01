@extends('layouts.app')
@section('title', app()->getLocale() === 'fa' ? 'ساخت کسب‌وکار' : 'Create business')

@section('content')
<style>
*{box-sizing:border-box}body{margin:0;background:linear-gradient(180deg,#eef2ff,#f8fafc 280px);color:#172033;font-family:inherit}
        .wrap{max-width:900px;margin:auto;padding:28px 16px 60px}
        .hero{background:linear-gradient(125deg,#7c3aed,#db2777);color:#fff;border-radius:30px;padding:28px;box-shadow:0 20px 60px rgba(124,58,237,.2)}
        .hero h1{font-size:32px;margin:5px 0 8px}.hero p{font-size:17px;line-height:2;margin:0}
        .panel{background:#fff;border:1px solid #e4e7ec;border-radius:28px;padding:24px;margin-top:18px;box-shadow:0 8px 25px rgba(15,23,42,.05)}
        .step{display:flex;gap:13px;align-items:center;margin-bottom:18px}.bubble{width:48px;height:48px;display:grid;place-items:center;border-radius:15px;background:#ede9fe;font-size:23px}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
        label span{display:block;font-size:14px;font-weight:900;margin:0 0 7px;color:#344054}
        input,select,textarea{width:100%;border:1.5px solid #9aa6b6!important;border-radius:15px!important;background:#fff!important;padding:13px 14px!important;font-size:16px!important}
        input:focus,select:focus,textarea:focus{outline:4px solid #e0e7ff!important;border-color:#6366f1!important}
        .btn{border:0;border-radius:16px;padding:14px 20px;background:#4f46e5;color:#fff;font-size:17px;font-weight:950;cursor:pointer}
        .errors{background:#fff1f2;border:1px solid #fecdd3;border-radius:16px;padding:15px;color:#9f1239;margin-top:16px}
        @media(max-width:700px){.grid{grid-template-columns:1fr}.hero h1{font-size:27px}}
</style>
])

<main class="wrap">
    <header class="hero">
        <div style="font-weight:900;opacity:.9">شروع ساده</div>
        <h1>کسب‌وکار شما چیست؟</h1>
        <p>فعلاً فقط اطلاعات اصلی را ثبت کنید. تلفن‌ها، آدرس‌ها، اعضا و تخصص‌ها را در صفحه بعدی کامل می‌کنیم.</p>
    </header>

    @if($errors->any())
        <div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('businesses.store') }}">
        @csrf
        <section class="panel">
            <div class="step"><div class="bubble">🏪</div><div><h2 style="margin:0">معرفی کسب‌وکار</h2><div style="color:#667085;margin-top:4px">اطلاعاتی که مردم و اعضای شما با آن کسب‌وکار را می‌شناسند.</div></div></div>

            <div class="grid">
                <label><span>نام کسب‌وکار *</span><input name="name" value="{{ old('name') }}" required placeholder="مثلاً املاک نمونه"></label>
                <label><span>نوع کسب‌وکار *</span><select name="kind" required>
                    @foreach($kindLabels as $value=>$label)<option value="{{ $value }}" @selected(old('kind')===$value)>{{ $label }}</option>@endforeach
                </select></label>
                <label><span>نام رسمی / حقوقی</span><input name="legal_name" value="{{ old('legal_name') }}" placeholder="اختیاری"></label>
                <label><span>سال تأسیس</span><input type="number" name="founded_year" value="{{ old('founded_year') }}" placeholder="مثلاً ۱۳۹۸"></label>
            </div>

            <label style="display:block;margin-top:14px"><span>معرفی کوتاه</span><input name="short_intro" value="{{ old('short_intro') }}" maxlength="300" placeholder="در یک جمله بگویید چه کاری انجام می‌دهید"></label>
            <label style="display:block;margin-top:14px"><span>توضیحات بیشتر</span><textarea name="description" rows="5" placeholder="اختیاری">{{ old('description') }}</textarea></label>

            <label style="display:block;margin-top:14px"><span>فعلاً چه کسانی این کسب‌وکار را ببینند؟</span>
                <select name="visibility">
                    <option value="private">فقط اعضای مرتبط</option>
                    <option value="members">اعضای مجموعه</option>
                    <option value="public">عمومی</option>
                </select>
            </label>

            <input type="hidden" name="status" value="active">

            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn">ساخت کسب‌وکار و ادامه ←</button>
                <a href="{{ route('businesses.index') }}" style="display:inline-flex;align-items:center;text-decoration:none;border:1px solid #d0d5dd;border-radius:16px;padding:14px 20px;font-weight:900;color:#475467;background:#fff">انصراف و بازگشت</a>
            </div>
        </section>
    </form>
</main>
@endsection
