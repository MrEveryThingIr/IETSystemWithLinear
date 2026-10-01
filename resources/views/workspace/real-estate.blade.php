@extends('layouts.app')
@section('title', app()->getLocale() === 'fa' ? 'دفاتر املاک' : 'Real Estate offices')

@section('content')
<style>
*{box-sizing:border-box}body{margin:0;background:#f7f9fc;color:#172033;font-family:inherit}.wrap{max-width:1050px;margin:auto;padding:25px 16px 60px}.hero{background:linear-gradient(125deg,#047857,#0ea5e9);color:#fff;border-radius:28px;padding:27px}.hero h1{font-size:31px;margin:5px 0}.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-top:18px}.card{background:#fff;border:1px solid #e4e7ec;border-radius:22px;padding:20px}.btn{display:inline-flex;margin-top:13px;background:#2563eb;color:#fff;text-decoration:none;border-radius:13px;padding:10px 14px;font-weight:900}
</style>
<main class="wrap"><header class="hero"><div style="font-weight:900;opacity:.9">عمودی تخصصی</div><h1>دفاتر املاک من</h1><div>فقط دفترهایی که اجازه دسترسی دارید اینجا دیده می‌شوند.</div></header><section class="cards">@forelse($portals as $portal)<article class="card"><div style="font-size:34px">🏠</div><h2>{{ $portal->title }}</h2><a class="btn" href="{{ route('office.real-estate.index',$portal) }}">باز کردن پرونده‌ها ←</a></article>@empty<article class="card"><h2>دفتری در دسترس نیست</h2><p style="color:#667085">برای این حساب هنوز دسترسی به دفتر املاک ثبت نشده است.</p></article>@endforelse</section></main>
@endsection
