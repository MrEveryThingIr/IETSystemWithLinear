@extends('layouts.app')
@section('title', app()->getLocale() === 'fa' ? 'تخصص‌های من' : 'My professions')

@section('content')
<style>
*{box-sizing:border-box}body{margin:0;background:linear-gradient(180deg,#fff7ed,#f8fafc 260px);color:#172033;font-family:inherit}
        .wrap{max-width:930px;margin:auto;padding:28px 16px 60px}
        .hero{background:linear-gradient(125deg,#ea580c,#db2777);color:#fff;border-radius:30px;padding:28px;box-shadow:0 20px 55px rgba(234,88,12,.18)}
        .hero h1{font-size:32px;margin:6px 0}.hero p{font-size:16px;line-height:2;margin:0}
        .panel{background:#fff;border:1px solid #e4e7ec;border-radius:26px;padding:22px;margin-top:17px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
        .item{border:1px solid #e1e6ee;border-radius:18px;padding:15px;margin-top:10px;display:flex;justify-content:space-between;gap:12px;align-items:center}
        .badge{display:inline-flex;border-radius:999px;background:#ede9fe;color:#6d28d9;padding:5px 9px;font-size:12px;font-weight:900}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
        label span{display:block;font-weight:900;font-size:14px;margin-bottom:7px}
        input,select{width:100%;border:1.5px solid #9daabc!important;border-radius:14px!important;padding:12px!important;background:#fff!important}
        .btn{border:0;border-radius:14px;padding:11px 15px;font-weight:950;cursor:pointer}.primary{background:#c2410c;color:#fff}.danger{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
        .status{background:#ecfdf5;border:1px solid #a7f3d0;border-radius:16px;padding:12px;color:#065f46;font-weight:900;margin-top:15px}
        @media(max-width:700px){.grid{grid-template-columns:1fr}}
</style>
<main class="wrap">
    <header class="hero"><div style="font-weight:900;opacity:.9">توانایی‌های حرفه‌ای</div><h1>تخصص‌های من</h1><p>تخصص با «عضویت در گروه» فرق دارد. اینجا می‌گویید چه کاری بلد هستید؛ بعد هر کسب‌وکار مشخص می‌کند در آن تیم با چه تخصصی فعالیت می‌کنید.</p></header>

    @if(session('status'))<div class="status">✓ {{ session('status') }}</div>@endif

    <section class="panel">
        <h2 style="margin-top:0">تخصص‌های ثبت‌شده</h2>
        @forelse($selected as $item)
            <div class="item">
                <div>
                    <strong style="font-size:18px">{{ $item->profession->name_fa ?: $item->profession->name }}</strong>
                    @if($item->profession->parent)<div style="color:#667085;margin-top:4px">{{ $item->profession->parent->name_fa ?: $item->profession->parent->name }}</div>@endif
                    <div style="margin-top:7px"><span class="badge">{{ $item->level }}</span>@if($item->years_experience !== null)<span class="badge">{{ $item->years_experience }} سال تجربه</span>@endif @if($item->is_primary)<span class="badge">⭐ اصلی</span>@endif</div>
                </div>
                <form method="POST" action="{{ route('profile.professions.destroy',$item) }}">@csrf @method('DELETE')<button class="btn danger">حذف</button></form>
            </div>
        @empty
            <p style="color:#667085">هنوز تخصصی ثبت نشده است.</p>
        @endforelse
    </section>

    <section class="panel">
        <h2 style="margin-top:0">＋ افزودن تخصص</h2>
        <form method="POST" action="{{ route('profile.professions.store') }}">
            @csrf
            <div class="grid">
                <label><span>حرفه / تخصص</span><select name="profession_id" required>@foreach($professions as $profession)<option value="{{ $profession->id }}">{{ $profession->parent ? (($profession->parent->name_fa ?: $profession->parent->name).' ← ') : '' }}{{ $profession->name_fa ?: $profession->name }}</option>@endforeach</select></label>
                <label><span>سطح</span><select name="level"><option value="beginner">تازه‌کار</option><option value="intermediate" selected>متوسط</option><option value="advanced">پیشرفته</option><option value="expert">متخصص</option></select></label>
                <label><span>سال تجربه</span><input type="number" name="years_experience" min="0" max="80"></label>
                <label><span>نمایش</span><select name="visibility"><option value="private">خصوصی</option><option value="contacts">افراد مرتبط</option><option value="members">اعضا</option><option value="public">عمومی</option></select></label>
            </div>
            <label style="display:flex;align-items:center;gap:8px;margin-top:12px"><input style="width:20px" type="checkbox" name="is_primary" value="1"><span style="margin:0">تخصص اصلی من</span></label>
            <button class="btn primary" style="margin-top:12px">ذخیره تخصص</button>
        </form>
    </section>
</main>
@endsection
