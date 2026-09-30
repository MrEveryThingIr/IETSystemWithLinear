<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>{{ $portal->title }}</title>
    @vite('resources/css/app.css')
    <style>
        body{background:#f1f5f9;color:#0f172a;font-family:inherit}
        .wrap{max-width:900px;margin:auto;padding:28px 14px}
        .card{background:#fff;border:1px solid #e2e8f0;border-radius:28px;box-shadow:0 18px 45px rgba(15,23,42,.08);overflow:hidden}
        .hero{padding:32px;background:linear-gradient(120deg,#ecfdf5,#fff);border-bottom:1px solid #e2e8f0}
        .hero h1{font-size:32px;font-weight:900;margin:12px 0}
        .body{padding:24px}
        .section{border:1px solid #e2e8f0;border-radius:22px;padding:20px;margin:0 0 20px;background:#fff}
        .section.alt{background:#f8fafc}
        .section h2{font-size:18px;font-weight:900;margin:0 0 14px}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        label span{display:block;margin-bottom:7px;font-size:14px;font-weight:800;color:#334155}
        input,select,textarea{
            width:100%;box-sizing:border-box;border:1.5px solid #94a3b8 !important;background:#fff !important;
            color:#0f172a;border-radius:14px;padding:12px 14px;box-shadow:0 1px 2px rgba(15,23,42,.04);
        }
        input:focus,select:focus,textarea:focus{outline:3px solid #d1fae5;border-color:#059669 !important}
        input::placeholder,textarea::placeholder{color:#94a3b8}
        .hint{font-size:13px;line-height:1.8;color:#64748b;margin-top:7px}
        .privacy{background:#fffbeb;border:1px solid #fde68a;padding:14px;border-radius:16px;line-height:1.8}
        .agebox{background:#ecfdf5;border:2px solid #a7f3d0;border-radius:18px;padding:16px;margin-top:16px}
        .submit{width:100%;border:0 !important;background:#047857 !important;color:white !important;border-radius:16px;padding:15px;font-weight:900;font-size:18px;cursor:pointer}
        .errors{background:#fff1f2;border:1px solid #fecdd3;border-radius:16px;padding:14px;margin-bottom:20px}
        details{border:1px solid #e2e8f0;border-radius:22px;padding:18px;background:#f8fafc;margin-bottom:20px}
        summary{font-weight:900;cursor:pointer}
        @media(max-width:700px){.grid,.grid3{grid-template-columns:1fr}.hero{padding:24px}.body{padding:16px}.hero h1{font-size:26px}}
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <header class="hero">
            <div style="display:inline-block;background:#047857;color:#fff;border-radius:999px;padding:6px 13px;font-weight:800;font-size:13px">ثبت مستقیم پرونده</div>
            <h1>{{ $portal->welcome_heading ?: $portal->title }}</h1>
            @if($portal->welcome_body)
                <p style="line-height:1.9;color:#475569">{{ $portal->welcome_body }}</p>
            @endif
            <div class="privacy">اطلاعات تماس و آدرس دقیق شما عمومی نمی‌شود و فقط برای پیگیری دفتر و افراد مجاز نگهداری می‌شود.</div>
        </header>

        <form method="POST" enctype="multipart/form-data" action="{{ route('public.real-estate.store', $portal) }}" class="body">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

            @if($errors->any())
                <div class="errors">
                    <strong>لطفاً موارد زیر را اصلاح کنید:</strong>
                    <ul>
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <section class="section alt">
                <h2>۱) چه چیزی می‌خواهید ثبت کنید؟</h2>
                <div class="grid">
                    <label><span>من...</span>
                        <select name="intent" required>
                            <option value="">انتخاب کنید</option>
                            <option value="offer" @selected(old('intent')==='offer')>ملک برای ارائه دارم</option>
                            <option value="need" @selected(old('intent')==='need')>متقاضی ملک هستم</option>
                        </select>
                    </label>
                    <label><span>نوع معامله</span>
                        <select name="transaction_mode" required>
                            <option value="">انتخاب کنید</option>
                            <option value="sale" @selected(old('transaction_mode')==='sale')>خرید / فروش</option>
                            <option value="rent" @selected(old('transaction_mode')==='rent')>رهن / اجاره</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="section">
                <h2>۲) اطلاعات تماس</h2>
                <div class="grid">
                    <label><span>نام و نام خانوادگی *</span>
                        <input name="contact_name" required value="{{ old('contact_name') }}" autocomplete="name">
                    </label>
                    <label><span>شماره تماس *</span>
                        <input name="phone" required value="{{ old('phone') }}" inputmode="tel" placeholder="مثلاً ۰۹۱۲۱۲۳۴۵۶۷">
                    </label>
                </div>
            </section>

            <section class="section">
                <h2>۳) ملک و موقعیت</h2>
                <div class="grid">
                    <label><span>نوع ملک *</span>
                        <select name="property_class" required>
                            <option value="">انتخاب کنید</option>
                            <option value="residential" @selected(old('property_class')==='residential')>مسکونی</option>
                            <option value="commercial" @selected(old('property_class')==='commercial')>تجاری</option>
                            <option value="office" @selected(old('property_class')==='office')>اداری</option>
                            <option value="land" @selected(old('property_class')==='land')>زمین</option>
                            <option value="industrial" @selected(old('property_class')==='industrial')>صنعتی / انبار</option>
                            <option value="agricultural" @selected(old('property_class')==='agricultural')>کشاورزی / باغ</option>
                            <option value="mixed" @selected(old('property_class')==='mixed')>مختلط</option>
                            <option value="other" @selected(old('property_class')==='other')>سایر</option>
                        </select>
                    </label>
                    <label><span>زیرنوع</span>
                        <input name="property_subtype" value="{{ old('property_subtype') }}" placeholder="مثلاً آپارتمان، ویلایی، مغازه">
                    </label>
                    <label style="grid-column:1/-1"><span>آدرس دقیق — خصوصی</span>
                        <textarea name="exact_address" rows="2" placeholder="آدرس کامل برای استفاده داخلی دفتر">{{ old('exact_address') }}</textarea>
                    </label>
                    <label style="grid-column:1/-1"><span>محله / محدوده</span>
                        <input name="public_area" value="{{ old('public_area') }}" placeholder="مثلاً ولیعصر؛ بدون پلاک دقیق">
                    </label>
                </div>
            </section>

            <section class="section">
                <h2>۴) متراژ و ساختمان</h2>
                <div class="grid3">
                    <label><span>مساحت زمین (متر²)</span><input name="land_area" inputmode="decimal" value="{{ old('land_area') }}" placeholder="مثلاً ۲۴۰"></label>
                    <label><span>زیربنا (متر²)</span><input name="construction_area" inputmode="decimal" value="{{ old('construction_area') }}" placeholder="مثلاً ۱۸۰"></label>
                    <label><span>تعداد خواب</span><input name="bedrooms" inputmode="numeric" value="{{ old('bedrooms') }}" placeholder="مثلاً ۳"></label>
                    <label><span>عرض زمین</span><input name="width" inputmode="decimal" value="{{ old('width') }}" placeholder="مثلاً ۸"></label>
                    <label><span>طول زمین</span><input name="length" inputmode="decimal" value="{{ old('length') }}" placeholder="مثلاً ۳۰"></label>
                    <label><span>چند نبش / چند بر؟</span><input name="frontage_count" inputmode="numeric" value="{{ old('frontage_count') }}" placeholder="مثلاً ۲"></label>
                </div>

                <div class="agebox">
                    <strong>سال ساخت را می‌دانید یا فقط سن بنا را؟</strong>
                    <p class="hint">اگر بنا حدود ۳۰ ساله است، لازم نیست سال را حساب کنید؛ فقط عدد ۳۰ را در «سن تقریبی بنا» بنویسید.</p>
                    <div class="grid">
                        <label><span>سال ساخت شمسی</span>
                            <input name="built_year" inputmode="numeric" value="{{ old('built_year') }}" placeholder="مثلاً ۱۳۷۰ یا ۱۴۰۲">
                            <input type="hidden" name="built_year_calendar" value="jalali">
                        </label>
                        <label><span>سن تقریبی بنا (سال)</span>
                            <input name="building_age_years" inputmode="numeric" value="{{ old('building_age_years') }}" placeholder="مثلاً ۳۰">
                        </label>
                    </div>
                </div>

                <label style="display:block;margin-top:16px"><span>وضعیت واقعی بنا</span>
                    <select name="building_condition">
                        <option value="">انتخاب کنید</option>
                        <option value="new" @selected(old('building_condition')==='new')>نوساز</option>
                        <option value="excellent" @selected(old('building_condition')==='excellent')>بسیار خوب</option>
                        <option value="good" @selected(old('building_condition')==='good')>خوب</option>
                        <option value="renovated" @selected(old('building_condition')==='renovated')>بازسازی‌شده</option>
                        <option value="needs_renovation" @selected(old('building_condition')==='needs_renovation')>نیازمند بازسازی</option>
                        <option value="old" @selected(old('building_condition')==='old')>قدیمی</option>
                        <option value="teardown" @selected(old('building_condition')==='teardown')>کلنگی</option>
                    </select>
                </label>
            </section>

            <details>
                <summary>جزئیات بیشتر ملک</summary>
                <div class="grid" style="margin-top:16px">
                    <label><span>نوع کابینت</span><input name="cabinet_type" value="{{ old('cabinet_type') }}" placeholder="MDF، فلزی، چوب..."></label>
                    <label><span>کناف / سقف کاذب</span>
                        <select name="has_false_ceiling"><option value="">نامشخص</option><option value="1">دارد</option><option value="0">ندارد</option></select>
                    </label>
                    <label><span>گرمایش</span><input name="heating_system" value="{{ old('heating_system') }}" placeholder="پکیج، موتورخانه، بخاری..."></label>
                    <label><span>سرمایش</span><input name="cooling_system" value="{{ old('cooling_system') }}" placeholder="اسپلیت، کولر آبی..."></label>
                    <label><span>کف</span><input name="flooring_type" value="{{ old('flooring_type') }}" placeholder="سرامیک، سنگ، پارکت..."></label>
                    <label><span>حیاط / محوطه</span><input name="yard_finish" value="{{ old('yard_finish') }}" placeholder="ساده، محوطه‌سازی‌شده..."></label>
                    <label><span>پارکینگ</span><select name="has_parking"><option value="">نامشخص</option><option value="1">دارد</option><option value="0">ندارد</option></select></label>
                    <label><span>نوع پارکینگ</span><input name="parking_type" value="{{ old('parking_type') }}" placeholder="اختصاصی، مزاحم/تونلی..."></label>
                    <label><span>تعداد جای پارک</span><input name="parking_spaces" inputmode="numeric" value="{{ old('parking_spaces') }}"></label>
                    <label><span>ظرفیت خودرو</span><input name="car_capacity" inputmode="numeric" value="{{ old('car_capacity') }}"></label>
                    <label><span>ظرفیت موتور</span><input name="motorbike_capacity" inputmode="numeric" value="{{ old('motorbike_capacity') }}"></label>
                    <label><span>توضیح پارکینگ</span><input name="parking_note" value="{{ old('parking_note') }}"></label>
                    <label><span>پشت‌بام</span><input name="roof_finish" value="{{ old('roof_finish') }}" placeholder="ایزوگام، سرامیک، موزاییک..."></label>
                    <label><span>جان‌پناه دور تا دور</span><select name="roof_has_parapet"><option value="">نامشخص</option><option value="1">دارد</option><option value="0">ندارد</option></select></label>
                    <label><span>توالت فرنگی</span><select name="has_western_toilet"><option value="">نامشخص</option><option value="1">دارد</option><option value="0">ندارد</option></select></label>
                    <label><span>توالت ایرانی</span><select name="has_iranian_toilet"><option value="">نامشخص</option><option value="1">دارد</option><option value="0">ندارد</option></select></label>
                </div>
            </details>

            <section class="section">
                <h2>۵) قیمت</h2>
                <input type="hidden" name="price_unit" value="toman">
                <div class="grid3">
                    <label><span>قیمت فروش</span><input name="asking_price" inputmode="numeric" value="{{ old('asking_price') }}" placeholder="مثلاً ۹٬۵۰۰٬۰۰۰٬۰۰۰"></label>
                    <label><span>رهن / ودیعه</span><input name="deposit_amount" inputmode="numeric" value="{{ old('deposit_amount') }}"></label>
                    <label><span>اجاره ماهانه</span><input name="monthly_rent_amount" inputmode="numeric" value="{{ old('monthly_rent_amount') }}"></label>
                </div>
            </section>

            @include('public-intake.real-estate.partials.media-fields')

            <label style="display:block;margin-bottom:20px"><span>توضیحات تکمیلی</span>
                <textarea name="notes" rows="4" placeholder="هر نکته‌ای که برای دفتر مهم است...">{{ old('notes') }}</textarea>
            </label>

            <button class="submit" type="submit">ثبت پرونده</button>
            <p class="hint" style="text-align:center">این صفحه فقط برای ثبت پرونده است؛ هیچ فهرست پرونده‌ای برای مراجعه‌کننده نمایش داده نمی‌شود.</p>
        </form>
    </section>
</main>
</body>
</html>
