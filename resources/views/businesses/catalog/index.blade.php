@extends('layouts.app')
@section('title', (app()->getLocale() === 'fa' ? 'کاتالوگ — ' : 'Catalog — ').$business->name)

@section('content')
@php($fa = app()->getLocale() === 'fa')
<style>
.cat{max-width:1220px;margin:auto;padding:26px 16px 70px}.cat-head{display:flex;justify-content:space-between;gap:16px;align-items:end;flex-wrap:wrap}.cat-head h1{font-size:32px;margin:4px 0}.muted{color:#667085;line-height:1.8}.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:0;border-radius:14px;padding:10px 14px;font-weight:900;cursor:pointer}.primary{background:#4f46e5;color:#fff}.light{background:#fff;color:#334155;border:1px solid #cbd5e1}.green{background:#047857;color:#fff}
.panel{background:#fff;border:1px solid #e4e7ec;border-radius:24px;margin-top:18px;padding:20px;box-shadow:0 7px 24px rgba(15,23,42,.04)}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.listings{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px;margin-top:14px}.listing{border:1px solid #e2e8f0;border-radius:20px;padding:18px;background:#fff}.badges{display:flex;gap:6px;flex-wrap:wrap}.badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:12px;font-weight:850}.listing h3{font-size:20px;margin:10px 0 6px}.prices{margin-top:12px;display:grid;gap:5px}.price{font-size:14px;color:#334155}
label span{display:block;font-size:13px;font-weight:850;margin-bottom:6px}input,select,textarea{width:100%;border:1.5px solid #aab4c4;border-radius:12px;padding:11px;background:#fff}.empty{border:2px dashed #cbd5e1;border-radius:20px;padding:30px;text-align:center;color:#64748b}
@media(max-width:800px){.grid{grid-template-columns:1fr}}
</style>
<main class="cat">
    @if(session('status'))<div class="panel" style="background:#ecfdf5;color:#065f46;font-weight:900">✓ {{ session('status') }}</div>@endif
    @if($errors->any())<div class="panel" style="background:#fff1f2;color:#9f1239"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <header class="cat-head">
        <div>
            <a href="{{ route('businesses.show',$business) }}" style="text-decoration:none;color:#4f46e5;font-weight:900">← {{ $business->name }}</a>
            <h1>{{ $fa ? 'کاتالوگ کسب‌وکار' : 'Business catalog' }}</h1>
            <p class="muted">{{ $fa ? 'کالا، خدمت، ملک و هر چیز قابل ارائه در یک مدل مشترک؛ جزئیات تخصصی فقط در لایه نوع مربوطه قرار می‌گیرد.' : 'Goods, services, properties, and other offerings share one listing model; specialized fields live only in their vertical extension.' }}</p>
        </div>
        <a class="btn light" href="{{ route('planner.index',['context'=>$business->contextBinding?->context?->uuid]) }}">{{ $fa ? 'برنامه‌های کاری' : 'Business routines' }}</a>
    </header>

    @if($canManage)
        <section class="panel">
            <h2 style="margin-top:0">{{ $fa ? 'دسته‌بندی کاتالوگ' : 'Catalog taxonomy' }}</h2>
            <p class="muted">{{ $fa ? 'دسته‌ها ساختار سازمان‌دهی هستند؛ قیمت، مشتری و زمان‌بندی داخل دسته ذخیره نمی‌شوند.' : 'Categories organize the catalog; transaction data such as price, customer, and schedule does not belong in categories.' }}</p>
            <form method="POST" action="{{ route('businesses.catalog.categories.store',$business) }}">
                @csrf
                <div class="grid">
                    <label><span>{{ $fa ? 'نام دسته' : 'Category name' }}</span><input name="name" required maxlength="180"></label>
                    <label><span>{{ $fa ? 'نامک (اختیاری)' : 'Slug (optional)' }}</span><input name="slug" maxlength="180"></label>
                    <label><span>{{ $fa ? 'دسته مادر' : 'Parent category' }}</span>
                        <select name="parent_id">
                            <option value="">{{ $fa ? 'ریشه' : 'Root' }}</option>
                            @foreach($business->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <button class="btn light" style="margin-top:12px">{{ $fa ? '＋ افزودن دسته' : '+ Add category' }}</button>
            </form>
        </section>

        <section class="panel">
            <h2 style="margin-top:0">{{ $fa ? '＋ ارائه جدید' : '+ New listing' }}</h2>
            <p class="muted">{{ $fa ? 'یک پیش‌نویس ساده بسازید. برای ملک، جزئیات تخصصی از کانال املاک یا ویرایشگر تخصصی تکمیل می‌شود.' : 'Create a simple draft. Property-specific details can be completed from the real-estate intake channel or a specialized editor.' }}</p>
            <form method="POST" action="{{ route('businesses.catalog.listings.store',$business) }}">
                @csrf
                <div class="grid">
                    <label><span>{{ $fa ? 'نوع ارائه' : 'Listing type' }}</span>
                        <select name="listing_type" required>
                            <option value="good">{{ $fa ? 'کالا' : 'Good' }}</option>
                            <option value="service">{{ $fa ? 'خدمت' : 'Service' }}</option>
                            <option value="property">{{ $fa ? 'ملک' : 'Property' }}</option>
                            <option value="other">{{ $fa ? 'سایر' : 'Other' }}</option>
                        </select>
                    </label>
                    <label><span>{{ $fa ? 'دسته' : 'Category' }}</span>
                        <select name="category_id">
                            <option value="">{{ $fa ? 'بدون دسته' : 'No category' }}</option>
                            @foreach($business->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label><span>{{ $fa ? 'نمایش' : 'Visibility' }}</span>
                        <select name="visibility"><option value="private">{{ $fa ? 'خصوصی' : 'Private' }}</option><option value="members">{{ $fa ? 'اعضای کسب‌وکار' : 'Business members' }}</option><option value="public">{{ $fa ? 'عمومی' : 'Public' }}</option></select>
                    </label>
                </div>
                <label style="display:block;margin-top:12px"><span>{{ $fa ? 'عنوان' : 'Title' }}</span><input name="title" required maxlength="220"></label>
                <label style="display:block;margin-top:12px"><span>{{ $fa ? 'معرفی کوتاه' : 'Short description' }}</span><input name="short_description" maxlength="500"></label>
                <label style="display:block;margin-top:12px"><span>{{ $fa ? 'توضیحات' : 'Description' }}</span><textarea name="description" rows="4"></textarea></label>
                <button class="btn primary" style="margin-top:12px">{{ $fa ? 'ساخت پیش‌نویس' : 'Create draft' }}</button>
            </form>
        </section>
    @endif

    <section class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
            <div><h2 style="margin:0">{{ $fa ? 'ارائه‌ها' : 'Listings' }}</h2><p class="muted" style="margin:5px 0 0">{{ $fa ? 'نسخه منتشرشده ثابت می‌ماند؛ ویرایش‌های بعدی نسخه جدید می‌سازند.' : 'Published versions stay frozen; later edits create a new version.' }}</p></div>
            <span class="badge">{{ $business->listings->count() }}</span>
        </div>
        <div class="listings">
            @forelse($business->listings as $listing)
                @php($version = $listing->currentVersion)
                <article class="listing">
                    <div class="badges">
                        <span class="badge">{{ $listing->listing_type }}</span>
                        <span class="badge">{{ $listing->status }}</span>
                        <span class="badge">{{ $listing->visibility }}</span>
                        @if($listing->category)<span class="badge">{{ $listing->category->name }}</span>@endif
                    </div>
                    <h3>{{ $version?->title ?? ($fa ? 'بدون عنوان' : 'Untitled') }}</h3>
                    @if($version?->short_description)<p class="muted">{{ $version->short_description }}</p>@endif

                    @if($version?->propertyDetails)
                        <div class="badges">
                            @if($version->propertyDetails->transaction_mode)<span class="badge">{{ $version->propertyDetails->transaction_mode }}</span>@endif
                            @if($version->propertyDetails->property_class)<span class="badge">{{ $version->propertyDetails->property_class }}</span>@endif
                            @if($version->propertyDetails->construction_area)<span class="badge">{{ $version->propertyDetails->construction_area }} m²</span>@endif
                            @if($version->propertyDetails->bedrooms !== null)<span class="badge">{{ $version->propertyDetails->bedrooms }} {{ $fa ? 'خواب' : 'bed' }}</span>@endif
                        </div>
                    @endif

                    <div class="prices">
                        @foreach($listing->prices->take(4) as $price)
                            <div class="price">
                                <strong>{{ $price->price_type }}</strong>:
                                {{ AppSupportMoneyAmount::format((int)$price->amount_minor, (int)$price->monetaryUnit->exponent) }}
                                {{ $price->monetaryUnit->code }}
                                <span class="badge">{{ $price->visibility }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if($canManage && $version && $version->published_at === null)
                        <form method="POST" action="{{ route('businesses.catalog.listings.publish',[$business,$listing]) }}" style="margin-top:14px">
                            @csrf
                            <button class="btn green">{{ $fa ? 'انتشار این نسخه و قفل تاریخچه' : 'Publish and freeze this version' }}</button>
                        </form>
                    @elseif($listing->publishedVersion)
                        <div style="margin-top:13px;color:#047857;font-weight:850">✓ {{ $fa ? 'نسخه منتشرشده' : 'Published version' }} #{{ $listing->publishedVersion->version_number }}</div>
                    @endif
                </article>
            @empty
                <div class="empty">{{ $fa ? 'هنوز کالا، خدمت یا ملکی در این کسب‌وکار ثبت نشده است.' : 'No goods, services, or properties have been added yet.' }}</div>
            @endforelse
        </div>
    </section>

    @if($business->kind === 'real_estate' && $business->publicIntakePortals->isNotEmpty())
        <section class="panel">
            <h2 style="margin-top:0">{{ $fa ? 'کانال‌های دفتر املاک' : 'Real-estate office channels' }}</h2>
            <p class="muted">{{ $fa ? 'این‌ها کانال‌های تخصصی همین کسب‌وکار هستند، نه یک سامانه جدا. پرونده عرضه ملک را بعد از بررسی به Listing تبدیل کنید.' : 'These are specialized channels of this Business, not a separate subsystem. Reviewed property offers can be promoted into Listings.' }}</p>
            @foreach($business->publicIntakePortals as $portal)
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                    <a class="btn light" href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}">{{ $fa ? 'پرونده‌های دفتر' : 'Office cases' }}</a>
                    <a class="btn light" target="_blank" rel="noopener" href="{{ route('public.real-estate.show',$portal) }}">{{ $fa ? 'فرم عمومی' : 'Public intake form' }}</a>
                </div>
            @endforeach
        </section>
    @endif
</main>
@endsection
