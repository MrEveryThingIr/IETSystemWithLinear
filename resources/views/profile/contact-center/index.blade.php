<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>راه‌های ارتباطی و آدرس‌ها</title>
    @vite('resources/css/app.css')
    <style>
        :root{
            --ink:#172033;--muted:#667085;--line:#d7deea;--paper:#fff;
            --purple:#7c3aed;--blue:#2563eb;--green:#059669;--orange:#ea580c;
        }
        *{box-sizing:border-box}
        body{margin:0;background:linear-gradient(180deg,#eef2ff 0,#f8fafc 260px);color:var(--ink);font-family:inherit}
        .wrap{max-width:1180px;margin:auto;padding:30px 16px 60px}
        .hero{
            position:relative;overflow:hidden;border-radius:32px;padding:32px;
            background:linear-gradient(125deg,#4338ca,#7c3aed 50%,#db2777);
            color:#fff;box-shadow:0 22px 60px rgba(79,70,229,.22)
        }
        .hero:after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.11);left:-70px;top:-100px}
        .hero h1{font-size:34px;margin:8px 0 10px;font-weight:950}
        .hero p{font-size:17px;line-height:2;margin:0;max-width:760px;color:#f5f3ff}
        .account-email{margin-top:20px;display:inline-flex;gap:10px;align-items:center;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.22);border-radius:16px;padding:11px 15px;font-size:15px}
        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:20px 0}
        .stat{background:#fff;border:1px solid #e4e7ec;border-radius:22px;padding:20px;box-shadow:0 6px 22px rgba(15,23,42,.05)}
        .stat strong{display:block;font-size:31px;margin-top:5px}.stat span{color:var(--muted);font-weight:800}
        .section{background:#fff;border:1px solid #e4e7ec;border-radius:28px;margin-top:18px;box-shadow:0 8px 26px rgba(15,23,42,.05);overflow:hidden}
        .section-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:23px 25px;border-bottom:1px solid #edf0f5;flex-wrap:wrap}
        .section-title{display:flex;gap:13px;align-items:center}
        .icon{width:50px;height:50px;display:grid;place-items:center;border-radius:16px;font-size:24px}
        .icon.contact{background:#ede9fe}.icon.address{background:#dcfce7}
        .section h2{font-size:24px;margin:0}.section-head p{margin:4px 0 0;color:var(--muted);font-size:14px}
        .body{padding:24px}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        label span{display:block;font-size:14px;font-weight:850;color:#344054;margin:0 0 7px}
        input,select,textarea{
            width:100%;border:1.5px solid #aeb8c8!important;background:#fff!important;border-radius:15px!important;
            padding:12px 14px!important;font-size:16px!important;color:#172033!important;box-shadow:0 1px 2px rgba(16,24,40,.03)
        }
        input:focus,select:focus,textarea:focus{outline:4px solid #e0e7ff!important;border-color:#6366f1!important}
        input::placeholder,textarea::placeholder{color:#98a2b3}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:14px;padding:12px 17px;font-size:15px;font-weight:900;cursor:pointer;text-decoration:none}
        .btn-purple{background:#6d28d9;color:#fff}.btn-green{background:#047857;color:#fff}.btn-light{background:#f8fafc;color:#344054;border:1px solid #d0d5dd}.btn-danger{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
        .list{display:grid;gap:12px;margin-bottom:22px}
        .item{border:1px solid #dfe5ee;border-radius:20px;padding:17px;background:linear-gradient(135deg,#fff,#fbfdff)}
        .item-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .item-main{display:flex;gap:12px;align-items:flex-start}
        .bubble{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:#eff6ff;font-size:20px}
        .value{font-size:18px;font-weight:950;direction:ltr;text-align:right;unicode-bidi:plaintext}
        .meta{display:flex;gap:7px;flex-wrap:wrap;margin-top:7px}
        .badge{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:900;background:#f2f4f7;color:#475467}
        .badge.primary{background:#fef3c7;color:#92400e}.badge.verified{background:#dcfce7;color:#166534}.badge.private{background:#fee2e2;color:#991b1b}.badge.public{background:#dbeafe;color:#1d4ed8}
        details.editor{margin-top:13px;border-top:1px dashed #d0d5dd;padding-top:12px}
        details.editor summary{cursor:pointer;color:#4f46e5;font-weight:900}
        .form-box{border:2px dashed #c7d2fe;background:#f8faff;border-radius:20px;padding:18px;margin-top:12px}
        .form-box.green{border-color:#a7f3d0;background:#f0fdf4}
        .form-box h3{margin:0 0 14px;font-size:18px}
        .check{display:flex;align-items:center;gap:9px;padding:10px 0}.check input{width:20px!important;height:20px!important}
        .address-line{font-size:17px;font-weight:850;line-height:1.9}
        .status{margin:18px 0;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:16px;padding:13px 16px;font-weight:900}
        .errors{margin:18px 0;background:#fff1f2;border:1px solid #fecdd3;color:#9f1239;border-radius:16px;padding:14px 17px}
        .empty{text-align:center;border:2px dashed #d0d5dd;border-radius:20px;padding:28px;color:#667085}
        .privacy-note{font-size:13px;color:#667085;line-height:1.8;margin-top:10px}
        @media(max-width:760px){
            .hero{padding:24px}.hero h1{font-size:28px}.stats,.grid,.grid3{grid-template-columns:1fr}
            .section-head,.body{padding:18px}.value{font-size:16px}
        }
    </style>
</head>
<body>
@php
    $kindLabels = [
        'email'=>'ایمیل','mobile'=>'موبایل','phone'=>'تلفن','website'=>'وب‌سایت',
        'whatsapp'=>'واتساپ','telegram'=>'تلگرام','other'=>'سایر',
    ];
    $kindIcons = [
        'email'=>'✉️','mobile'=>'📱','phone'=>'☎️','website'=>'🌐',
        'whatsapp'=>'💬','telegram'=>'✈️','other'=>'🔗',
    ];
    $visibilityLabels = [
        'private'=>'فقط خودم/مدیران',
        'contacts'=>'افراد مرتبط',
        'members'=>'اعضای مجموعه',
        'public'=>'عمومی',
    ];
    $addressTypes = [
        'residence'=>'محل سکونت','work'=>'محل کار','branch'=>'شعبه','billing'=>'صورتحساب',
        'shipping'=>'ارسال','project_site'=>'محل پروژه','other'=>'سایر',
    ];
@endphp

<main class="wrap">
    <header class="hero">
        <div style="font-weight:900;opacity:.92">پروفایل من</div>
        <h1>راه‌های ارتباطی و آدرس‌ها</h1>
        <p>هر شماره، ایمیل یا آدرس را با کاربرد خودش نگه دارید. شما تعیین می‌کنید کدام مورد اصلی است و چه کسانی اجازه دیدن آن را دارند.</p>
        <div class="account-email">
            <span>🔐 ایمیل ورود به حساب:</span>
            <strong dir="ltr">{{ $user->email }}</strong>
            @if($user->email_verified_at)
                <span style="background:#dcfce7;color:#166534;border-radius:999px;padding:3px 8px;font-weight:900">تأییدشده</span>
            @else
                <span style="background:#fef3c7;color:#92400e;border-radius:999px;padding:3px 8px;font-weight:900">تأییدنشده</span>
            @endif
        </div>
    </header>

    @if(session('status'))
        <div class="status">✓ {{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="errors">
            <strong>چند مورد نیاز به اصلاح دارد:</strong>
            <ul>
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <section class="stats">
        <div class="stat"><span>راه‌های ارتباطی</span><strong>{{ $contactPoints->count() }}</strong></div>
        <div class="stat"><span>آدرس‌های ذخیره‌شده</span><strong>{{ $addresses->count() }}</strong></div>
        <div class="stat"><span>موارد عمومی</span><strong>{{ $contactPoints->where('visibility','public')->count() + $addresses->where('visibility','public')->count() }}</strong></div>
    </section>

    <section class="section">
        <div class="section-head">
            <div class="section-title">
                <div class="icon contact">📞</div>
                <div><h2>راه‌های ارتباطی</h2><p>موبایل، تلفن کاری، ایمیل دوم، سایت و...</p></div>
            </div>
        </div>

        <div class="body">
            <div class="list">
                @forelse($contactPoints as $point)
                    <article class="item">
                        <div class="item-top">
                            <div class="item-main">
                                <div class="bubble">{{ $kindIcons[$point->kind] ?? '🔗' }}</div>
                                <div>
                                    <div style="font-size:13px;color:#667085;font-weight:800">
                                        {{ $point->label ?: ($kindLabels[$point->kind] ?? $point->kind) }}
                                    </div>
                                    <div class="value">{{ $point->value }}</div>
                                    <div class="meta">
                                        @if($point->is_primary)<span class="badge primary">⭐ اصلی</span>@endif
                                        @if($point->is_verified)<span class="badge verified">✓ تأییدشده</span>@else<span class="badge">تأییدنشده</span>@endif
                                        <span class="badge {{ $point->visibility === 'public' ? 'public' : ($point->visibility === 'private' ? 'private' : '') }}">
                                            {{ $visibilityLabels[$point->visibility] ?? $point->visibility }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('profile.contacts.contact.destroy',$point) }}" onsubmit="return confirm('این راه ارتباطی حذف شود؟')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger">حذف</button>
                            </form>
                        </div>

                        <details class="editor">
                            <summary>ویرایش</summary>
                            <form method="POST" action="{{ route('profile.contacts.contact.update',$point) }}" class="form-box">
                                @csrf @method('PUT')
                                <div class="grid3">
                                    <label><span>نوع</span><select name="kind">
                                        @foreach($kindLabels as $v=>$text)<option value="{{ $v }}" @selected($point->kind===$v)>{{ $text }}</option>@endforeach
                                    </select></label>
                                    <label><span>عنوان دلخواه</span><input name="label" value="{{ $point->label }}" placeholder="مثلاً موبایل کاری"></label>
                                    <label><span>چه کسانی ببینند؟</span><select name="visibility">
                                        @foreach($visibilityLabels as $v=>$text)<option value="{{ $v }}" @selected($point->visibility===$v)>{{ $text }}</option>@endforeach
                                    </select></label>
                                </div>
                                <label style="display:block;margin-top:12px"><span>مقدار</span><input name="value" value="{{ $point->value }}" required></label>
                                <label class="check"><input type="checkbox" name="is_primary" value="1" @checked($point->is_primary)><span style="margin:0">این مورد اصلی باشد</span></label>
                                <button class="btn btn-purple">ذخیره تغییرات</button>
                            </form>
                        </details>
                    </article>
                @empty
                    <div class="empty">هنوز شماره تماس یا راه ارتباطی دیگری ثبت نکرده‌اید.</div>
                @endforelse
            </div>

            <div class="form-box">
                <h3>＋ افزودن راه ارتباطی</h3>
                <form method="POST" action="{{ route('profile.contacts.contact.store') }}">
                    @csrf
                    <div class="grid3">
                        <label><span>نوع</span><select name="kind" required>
                            @foreach($kindLabels as $v=>$text)<option value="{{ $v }}">{{ $text }}</option>@endforeach
                        </select></label>
                        <label><span>عنوان</span><input name="label" placeholder="مثلاً موبایل شخصی"></label>
                        <label><span>نمایش برای</span><select name="visibility" required>
                            @foreach($visibilityLabels as $v=>$text)<option value="{{ $v }}" @selected($v==='private')>{{ $text }}</option>@endforeach
                        </select></label>
                    </div>
                    <label style="display:block;margin-top:12px"><span>شماره / ایمیل / آدرس وب</span><input name="value" required placeholder="مثلاً ۰۹۱۲... یا work@example.com"></label>
                    <label class="check"><input type="checkbox" name="is_primary" value="1"><span style="margin:0">به‌عنوان مورد اصلی این نوع ذخیره شود</span></label>
                    <button class="btn btn-purple">＋ افزودن</button>
                </form>
                <div class="privacy-note">تغییر شماره یا ایمیلِ تأییدشده، وضعیت تأیید آن را خودکار بازنشانی می‌کند؛ تأیید نباید با یک تیک دستی جعل شود.</div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <div class="section-title">
                <div class="icon address">📍</div>
                <div><h2>آدرس‌ها</h2><p>خانه، محل کار، شعبه، آدرس ارسال یا محل پروژه</p></div>
            </div>
        </div>

        <div class="body">
            <div class="list">
                @forelse($addresses as $address)
                    <article class="item">
                        <div class="item-top">
                            <div class="item-main">
                                <div class="bubble" style="background:#ecfdf5">🏠</div>
                                <div>
                                    <div style="font-size:13px;color:#667085;font-weight:800">
                                        {{ $address->label ?: ($addressTypes[$address->type] ?? $address->type) }}
                                    </div>
                                    <div class="address-line">
                                        {{ collect([$address->province,$address->city,$address->district,$address->street,$address->alley])->filter()->join('، ') }}
                                        @if($address->building_no) پلاک {{ $address->building_no }} @endif
                                        @if($address->unit) واحد {{ $address->unit }} @endif
                                    </div>
                                    <div class="meta">
                                        @if($address->is_primary)<span class="badge primary">⭐ اصلی</span>@endif
                                        <span class="badge {{ $address->visibility === 'public' ? 'public' : ($address->visibility === 'private' ? 'private' : '') }}">
                                            {{ $visibilityLabels[$address->visibility] ?? $address->visibility }}
                                        </span>
                                        @if($address->postal_code)<span class="badge">کدپستی: {{ $address->postal_code }}</span>@endif
                                    </div>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('profile.contacts.address.destroy',$address) }}" onsubmit="return confirm('این آدرس حذف شود؟')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger">حذف</button>
                            </form>
                        </div>

                        <details class="editor">
                            <summary>ویرایش آدرس</summary>
                            <form method="POST" action="{{ route('profile.contacts.address.update',$address) }}" class="form-box green">
                                @csrf @method('PUT')
                                <div class="grid3">
                                    <label><span>نوع</span><select name="type">
                                        @foreach($addressTypes as $v=>$text)<option value="{{ $v }}" @selected($address->type===$v)>{{ $text }}</option>@endforeach
                                    </select></label>
                                    <label><span>عنوان</span><input name="label" value="{{ $address->label }}"></label>
                                    <label><span>نمایش برای</span><select name="visibility">
                                        @foreach($visibilityLabels as $v=>$text)<option value="{{ $v }}" @selected($address->visibility===$v)>{{ $text }}</option>@endforeach
                                    </select></label>
                                    <label><span>کشور</span><input name="country_code" value="{{ $address->country_code ?: 'IR' }}" maxlength="2"></label>
                                    <label><span>استان</span><input name="province" value="{{ $address->province }}"></label>
                                    <label><span>شهر *</span><input name="city" value="{{ $address->city }}" required></label>
                                    <label><span>محله / منطقه</span><input name="district" value="{{ $address->district }}"></label>
                                    <label><span>خیابان</span><input name="street" value="{{ $address->street }}"></label>
                                    <label><span>کوچه</span><input name="alley" value="{{ $address->alley }}"></label>
                                    <label><span>پلاک</span><input name="building_no" value="{{ $address->building_no }}"></label>
                                    <label><span>واحد</span><input name="unit" value="{{ $address->unit }}"></label>
                                    <label><span>کدپستی</span><input name="postal_code" value="{{ $address->postal_code }}"></label>
                                </div>
                                <label class="check"><input type="checkbox" name="is_primary" value="1" @checked($address->is_primary)><span style="margin:0">این آدرس اصلی این نوع باشد</span></label>
                                <button class="btn btn-green">ذخیره تغییرات</button>
                            </form>
                        </details>
                    </article>
                @empty
                    <div class="empty">هنوز آدرسی ثبت نشده است.</div>
                @endforelse
            </div>

            <div class="form-box green">
                <h3>＋ افزودن آدرس</h3>
                <form method="POST" action="{{ route('profile.contacts.address.store') }}">
                    @csrf
                    <div class="grid3">
                        <label><span>نوع آدرس</span><select name="type" required>
                            @foreach($addressTypes as $v=>$text)<option value="{{ $v }}">{{ $text }}</option>@endforeach
                        </select></label>
                        <label><span>عنوان دلخواه</span><input name="label" placeholder="مثلاً خانه تهران"></label>
                        <label><span>نمایش برای</span><select name="visibility" required>
                            @foreach($visibilityLabels as $v=>$text)<option value="{{ $v }}" @selected($v==='private')>{{ $text }}</option>@endforeach
                        </select></label>
                        <label><span>کشور</span><input name="country_code" value="IR" maxlength="2"></label>
                        <label><span>استان</span><input name="province" placeholder="تهران"></label>
                        <label><span>شهر *</span><input name="city" required placeholder="تهران"></label>
                        <label><span>محله / منطقه</span><input name="district"></label>
                        <label><span>خیابان</span><input name="street"></label>
                        <label><span>کوچه</span><input name="alley"></label>
                        <label><span>پلاک</span><input name="building_no"></label>
                        <label><span>واحد</span><input name="unit"></label>
                        <label><span>کدپستی</span><input name="postal_code"></label>
                    </div>
                    <label class="check"><input type="checkbox" name="is_primary" value="1"><span style="margin:0">این آدرس اصلی این نوع باشد</span></label>
                    <button class="btn btn-green">＋ افزودن آدرس</button>
                </form>
            </div>
        </div>
    </section>
</main>
</body>
</html>
