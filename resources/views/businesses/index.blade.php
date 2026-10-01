@extends('layouts.app')
@section('title', app()->getLocale() === 'fa' ? 'کسب‌وکارهای من' : 'My businesses')

@section('content')
<style>
*{box-sizing:border-box}body{margin:0;background:#f6f8fc;color:#172033;font-family:inherit}
        .wrap{max-width:1180px;margin:auto;padding:30px 16px 60px}
        .hero{border-radius:32px;padding:32px;background:linear-gradient(125deg,#0f766e,#0ea5e9 55%,#6366f1);color:#fff;box-shadow:0 22px 60px rgba(14,116,144,.2)}
        .hero h1{font-size:35px;margin:5px 0 10px;font-weight:950}.hero p{font-size:17px;line-height:2;margin:0;max-width:760px}
        .top{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
        .btn{display:inline-flex;text-decoration:none;border:0;border-radius:16px;padding:13px 18px;font-size:16px;font-weight:950;cursor:pointer}
        .btn-white{background:#fff;color:#0f766e}.btn-primary{background:#4f46e5;color:#fff}
        .cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;margin-top:22px}
        .card{background:#fff;border:1px solid #e4e7ec;border-radius:25px;padding:22px;box-shadow:0 8px 25px rgba(15,23,42,.05)}
        .kind{display:inline-flex;padding:6px 10px;border-radius:999px;background:#e0f2fe;color:#075985;font-weight:900;font-size:13px}
        .card h2{font-size:23px;margin:14px 0 5px}.muted{color:#667085;line-height:1.8}
        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:18px 0}
        .stat{background:#f8fafc;border-radius:15px;padding:11px;text-align:center}.stat strong{font-size:21px;display:block}
        .empty{background:#fff;border:2px dashed #cbd5e1;border-radius:24px;padding:42px;text-align:center;margin-top:24px}.empty h2{font-size:25px}
</style>
<main class="wrap">
    <header class="hero">
        <div class="top">
            <div>
                <div style="font-weight:900;opacity:.9">مرکز کسب‌وکار</div>
                <h1>کسب‌وکارهای من</h1>
                <p>فروشگاه، دفتر خدمات، کارگاه، دفتر املاک یا هر مجموعه‌ای که با مشتری، کالا یا خدمت سروکار دارد.</p>
            </div>
            <a class="btn btn-white" href="{{ route('businesses.create') }}">＋ ساخت کسب‌وکار</a>
        </div>
    </header>

    @if($businesses->isEmpty())
        <section class="empty">
            <div style="font-size:50px">🏪</div>
            <h2>هنوز کسب‌وکاری نساخته‌اید</h2>
            <p class="muted">اولین کسب‌وکار را در چند مرحله ساده معرفی کنید.</p>
            <a class="btn btn-primary" href="{{ route('businesses.create') }}">شروع معرفی کسب‌وکار</a>
        </section>
    @else
        <section class="cards">
            @foreach($businesses as $business)
                <article class="card">
                    <span class="kind">{{ $kindLabels[$business->kind] ?? $business->kind }}</span>
                    <h2>{{ $business->name }}</h2>
                    <div class="muted">{{ $business->short_intro ?: 'هنوز معرفی کوتاه ثبت نشده است.' }}</div>

                    <div class="stats">
                        <div class="stat"><strong>{{ $business->active_members_count }}</strong><span>اعضا</span></div>
                        <div class="stat"><strong>{{ $business->contact_points_count }}</strong><span>تماس‌ها</span></div>
                        <div class="stat"><strong>{{ $business->addresses_count }}</strong><span>آدرس‌ها</span></div>
                    </div>

                    <a class="btn btn-primary" href="{{ route('businesses.show',$business) }}">باز کردن کسب‌وکار ←</a>
                </article>
            @endforeach
        </section>
    @endif
</main>
@endsection
