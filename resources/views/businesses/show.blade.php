@extends('layouts.app')
@section('title', $business->name)

@section('content')
<style>
:root{--ink:#172033;--muted:#667085;--line:#d8dee9;--purple:#6d28d9;--blue:#2563eb;--green:#047857;--orange:#c2410c}
        *{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:var(--ink);font-family:inherit}
        .wrap{max-width:1220px;margin:auto;padding:28px 16px 70px}
        .hero{background:linear-gradient(125deg,#0f766e,#0284c7 48%,#4f46e5);color:#fff;border-radius:32px;padding:30px;box-shadow:0 22px 60px rgba(2,132,199,.18)}
        .hero h1{font-size:35px;margin:7px 0}.hero p{font-size:17px;line-height:1.9;margin:0;max-width:780px}
        .hero-row,.row{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
        .chip{display:inline-flex;padding:6px 10px;border-radius:999px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.2);font-weight:900;font-size:13px}
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:18px 0}
        .stat{background:#fff;border:1px solid #e4e7ec;border-radius:20px;padding:18px;box-shadow:0 6px 20px rgba(15,23,42,.04)}
        .stat span{color:var(--muted);font-weight:850}.stat strong{display:block;font-size:29px;margin-top:5px}
        .panel{background:#fff;border:1px solid #e4e7ec;border-radius:27px;margin-top:17px;box-shadow:0 8px 24px rgba(15,23,42,.05);overflow:hidden}
        .head{padding:21px 23px;border-bottom:1px solid #edf0f5;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
        .head h2{margin:0;font-size:23px}.head p{color:var(--muted);margin:5px 0 0}
        .body{padding:22px}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:13px}.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:13px}
        label span{display:block;font-size:14px;font-weight:900;margin-bottom:7px;color:#344054}
        input,select,textarea{width:100%;border:1.5px solid #9daabc!important;border-radius:14px!important;padding:12px 13px!important;font-size:15px!important;background:#fff!important;color:#172033!important}
        input:focus,select:focus,textarea:focus{outline:4px solid #e0e7ff!important;border-color:#6366f1!important}
        .btn{border:0;border-radius:14px;padding:11px 15px;font-weight:950;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
        .primary{background:#4f46e5;color:#fff}.green{background:#047857;color:#fff}.light{background:#f8fafc;color:#344054;border:1px solid #d0d5dd}.danger{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
        .status{margin:16px 0;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:16px;padding:13px 16px;color:#065f46;font-weight:900}
        .list{display:grid;gap:11px}.item{border:1px solid #dde3ec;border-radius:19px;padding:16px;background:#fbfcfe}
        .badge{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:900;background:#f2f4f7;color:#475467}.owner{background:#fef3c7;color:#92400e}.manager{background:#dbeafe;color:#1d4ed8}.profession{background:#ede9fe;color:#6d28d9}
        .quick{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
        .quick-card{border-radius:19px;padding:17px;color:#fff}.q1{background:linear-gradient(135deg,#7c3aed,#a855f7)}.q2{background:linear-gradient(135deg,#059669,#10b981)}.q3{background:linear-gradient(135deg,#ea580c,#f59e0b)}
        .quick-card strong{font-size:18px;display:block}.quick-card span{font-size:13px;opacity:.92}
        .section-form{margin-top:16px;padding:17px;border-radius:18px;background:#f8fafc;border:1px dashed #cbd5e1}
        @media(max-width:800px){.stats,.quick,.grid,.grid3{grid-template-columns:1fr}.hero h1{font-size:28px}.body,.head{padding:17px}}
</style>
<main class="wrap">
    @if(session('status'))<div class="status">✓ {{ session('status') }}</div>@endif
    @if($errors->any())<div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:16px;padding:14px;color:#9f1239;margin-bottom:15px"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <header class="hero">
        <div class="hero-row">
            <div>
                <div class="chip">{{ $kindLabels[$business->kind] ?? $business->kind }}</div>
                <h1>{{ $business->name }}</h1>
                <p>{{ $business->short_intro ?: 'معرفی کوتاه این کسب‌وکار هنوز کامل نشده است.' }}</p>
            </div>
            <div style="text-align:left">
                <div class="chip">کد {{ $business->code }}</div>
                @if($canOperate)
                    <div style="margin-top:8px;font-size:13px;opacity:.9">مالک: {{ $business->owner->user?->username ?? $business->owner->user?->email ?? ('Actor #'.$business->owner->getKey()) }}</div>
                @endif
            </div>
        </div>
    </header>

    @php
        if (! $canOperate) {
            $businessNextLabel = __('workflow.business.next_continue');
            $businessNextHref = null;
        } elseif ($business->contactPoints->isEmpty() && $business->addresses->isEmpty()) {
            $businessNextLabel = __('workflow.business.next_profile');
            $businessNextHref = '#business-profile';
        } elseif ($business->businessContacts->isEmpty()) {
            $businessNextLabel = __('workflow.business.next_client');
            $businessNextHref = route('businesses.clients.index', $business);
        } elseif ($business->listings->isEmpty()) {
            $businessNextLabel = __('workflow.business.next_catalog');
            $businessNextHref = route('businesses.catalog.index', $business);
        } elseif ($canUsePlanner && $routineCount === 0 && $businessContext) {
            $businessNextLabel = __('workflow.business.next_work');
            $businessNextHref = route('planner.create', ['context' => $businessContext->uuid]);
        } else {
            $businessNextLabel = __('workflow.business.next_continue');
            $businessNextHref = $canUsePlanner && $businessContext
                ? route('planner.index', ['context' => $businessContext->uuid])
                : null;
        }
    @endphp

    <div style="margin-top:18px">
        <x-app.workflow-shell
            :purpose="__('workflow.business.purpose')"
            :state="__('workflow.business.state_active')"
            :next-action="$businessNextLabel"
            :audience="__('workflow.business.audience')"
            :consequence="__('workflow.business.consequence')"
            :result="__('workflow.business.result')"
            :steps="$businessSections"
            :step-hrefs="$businessSectionLinks"
            current-step="overview"
            :progressive="false"
        >
            @if ($canOperate)
                <x-slot:actions>
                    @if ($businessNextHref)
                        <flux:button :href="$businessNextHref" variant="primary">
                            {{ $businessNextLabel }}
                        </flux:button>
                    @endif
                    <flux:button :href="route('businesses.clients.index', $business)" variant="ghost">
                        {{ __('workflow.business.sections.clients') }}
                    </flux:button>
                    <flux:button :href="route('businesses.catalog.index', $business)" variant="ghost">
                        {{ __('workflow.business.sections.catalog') }}
                    </flux:button>
                </x-slot:actions>

                <x-slot:advanced>
                    <div class="flex flex-wrap gap-2">
                        @if ($canUsePlanner && $businessContext)
                            <flux:button :href="route('planner.index', ['context' => $businessContext->uuid])" size="sm" variant="ghost">
                                {{ __('workflow.business.sections.work') }}
                            </flux:button>
                        @endif
                        @if ($canUseDeals)
                            <flux:button :href="route('deals.index')" size="sm" variant="ghost">
                                {{ __('workflow.business.sections.deals') }}
                            </flux:button>
                        @endif
                        @if ($canUseMoney)
                            <flux:button :href="route('money.index')" size="sm" variant="ghost">
                                {{ __('workflow.business.sections.money') }}
                            </flux:button>
                        @endif
                        <flux:button href="#team-settings" size="sm" variant="ghost">
                            {{ __('workflow.business.sections.manage') }}
                        </flux:button>
                    </div>
                </x-slot:advanced>
            @endif

            <x-slot:help>{{ __('workflow.business.help') }}</x-slot:help>
        </x-app.workflow-shell>
    </div>

    @if($canOperate)
    <section class="stats">
        <div class="stat"><span>اعضای فعال</span><strong>{{ $business->memberships->count() }}</strong></div>
        <div class="stat"><span>مشتری / مخاطب</span><strong>{{ $business->businessContacts->count() }}</strong></div>
        <div class="stat"><span>کالا / خدمت / ملک</span><strong>{{ $business->listings->count() }}</strong></div>
        <div class="stat"><span>برنامه‌های کاری</span><strong>{{ $routineCount }}</strong></div>
    </section>

    <section class="quick">
        <a class="quick-card q1" style="text-decoration:none" href="{{ route('businesses.clients.index',$business) }}">
            <strong>👤 مشتریان و مخاطبان</strong>
            <span>CRM ساده برای افراد واقعی، حتی بدون حساب IET</span>
        </a>
        <a class="quick-card q2" style="text-decoration:none" href="{{ route('businesses.catalog.index',$business) }}">
            <strong>🧰 کاتالوگ و ارائه‌ها</strong>
            <span>کالا، خدمت، ملک و قیمت‌ها در یک زیرساخت مشترک</span>
        </a>
        @if($canUsePlanner)
            <a class="quick-card q3" style="text-decoration:none" href="{{ route('planner.index',['context'=>$businessContext->uuid]) }}">
                <strong>🗓 برنامه‌های کسب‌وکار</strong>
                <span>روتین‌ها، تقویم، اجرا و هزینه‌های برنامه‌ریزی‌شده</span>
            </a>
            <a class="quick-card q1" style="text-decoration:none" href="{{ route('planner.create',['context'=>$businessContext->uuid]) }}">
                <strong>＋ برنامه کاری جدید</strong>
                <span>برنامه مستقیماً در Context همین کسب‌وکار ساخته می‌شود</span>
            </a>
        @endif
    </section>
    @endif

    @if($canOperate)
        <section class="panel">
            <div class="head">
                <div>
                    <h2>💰 تسویه و جریان مالی کسب‌وکار</h2>
                    <p>روال‌های داخلی بر پایه واحد تسویه کسب‌وکار محاسبه می‌شوند؛ اتصال به پول واقعی فقط از طریق درگاه‌های تأییدشده انجام خواهد شد.</p>
                </div>
            </div>
            <div class="body">
                <div class="grid3">
                    <div class="item">
                        <strong>موقعیت اقتصادی این کسب‌وکار</strong>
                        <div style="font-size:26px;font-weight:950;margin-top:6px">
                            {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_net_minor'] ?? 0), 0) }} IET
                        </div>
                        <div class="muted" style="margin-top:5px">
                            طلب باز {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_receivable_minor'] ?? 0), 0) }}
                            · بدهی باز {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_payable_minor'] ?? 0), 0) }} IET
                        </div>
                        <div class="muted" style="margin-top:5px">
                            درآمد تسویه‌شده {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_realized_revenue_minor'] ?? 0), 0) }}
                            · هزینه تسویه‌شده {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_realized_expense_minor'] ?? 0), 0) }}
                            · خالص تحقق‌یافته {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_realized_profit_minor'] ?? 0), 0) }} IET
                        </div>
                        <div class="badges" style="margin-top:8px">
                            <span class="badge">{{ $economyProjection['market_intent_count'] ?? 0 }} بازار</span>
                            <span class="badge">{{ $economyProjection['deal_count'] ?? 0 }} معامله</span>
                            <span class="badge">{{ $economyProjection['contract_count'] ?? 0 }} قرارداد</span>
                        </div>
                    </div>
                    <div class="item">
                        <strong>واحد تسویه داخلی</strong>
                        <div style="font-size:26px;font-weight:950;margin-top:6px">{{ $business->defaultMonetaryUnit?->code ?? 'IET' }}</div>
                        <div class="muted" style="margin-top:5px">تعهدات و تسویه‌های داخلی این کسب‌وکار بر پایه واقعیت‌های مالی اصلی محاسبه می‌شوند.</div>
                    </div>
                    <div class="item">
                        <strong>واریز / برداشت پول واقعی</strong>
                        <div class="badge" style="margin-top:9px">placeholder</div>
                        <div class="muted" style="margin-top:5px">هیچ بانک یا پرداخت‌یار واقعی در این مرحله متصل نیست.</div>
                    </div>
                </div>
                <div class="badges" style="margin-top:12px">
                    @foreach($externalMoneyGateways as $gateway)
                        <span class="badge">{{ $gateway['label'] }} — {{ $gateway['status'] }}</span>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($canOperate && $business->kind === 'real_estate' && $business->publicIntakePortals->isNotEmpty())
        <section class="panel">
            <div class="head">
                <div>
                    <h2>🏠 کانال تخصصی املاک</h2>
                    <p>املاک یک قابلیت تخصصی همین کسب‌وکار است؛ پرونده‌های تأییدشده به کاتالوگ عمومی کسب‌وکار ارتقا پیدا می‌کنند.</p>
                </div>
            </div>
            <div class="body">
                @foreach($business->publicIntakePortals as $portal)
                    <div class="item row">
                        <div>
                            <strong>{{ $portal->title }}</strong>
                            <div class="muted" style="margin-top:5px">فرم مراجعه‌کننده و دفتر پیگیری موجود حفظ شده‌اند.</div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <a class="btn light" href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}">پرونده‌های دفتر</a>
                            <a class="btn light" target="_blank" rel="noopener" href="{{ route('public.real-estate.show',$portal) }}">فرم عمومی</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section id="business-profile" class="panel">
        <div class="head"><div><h2>🏪 مشخصات کسب‌وکار</h2><p>اطلاعات پایه و سطح نمایش</p></div></div>
        <div class="body">
            @if($canManage)
                <form method="POST" action="{{ route('businesses.update',$business) }}">
                    @csrf @method('PUT')
                    <div class="grid3">
                        <label><span>نام</span><input name="name" value="{{ $business->name }}" required></label>
                        <label><span>نوع</span><select name="kind">@foreach($kindLabels as $v=>$label)<option value="{{ $v }}" @selected($business->kind===$v)>{{ $label }}</option>@endforeach</select></label>
                        <label><span>نمایش</span><select name="visibility">@foreach($visibilityLabels as $v=>$label)<option value="{{ $v }}" @selected($business->visibility===$v)>{{ $label }}</option>@endforeach</select></label>
                        <label><span>نام رسمی</span><input name="legal_name" value="{{ $business->legal_name }}"></label>
                        <label><span>سال تأسیس</span><input type="number" name="founded_year" value="{{ $business->founded_year }}"></label>
                        <label><span>وضعیت</span><select name="status"><option value="active" @selected($business->status==='active')>فعال</option><option value="paused" @selected($business->status==='paused')>موقتاً متوقف</option></select></label>
                    </div>
                    <label style="display:block;margin-top:12px"><span>معرفی کوتاه</span><input name="short_intro" value="{{ $business->short_intro }}"></label>
                    <label style="display:block;margin-top:12px"><span>توضیحات</span><textarea name="description" rows="4">{{ $business->description }}</textarea></label>
                    <button class="btn primary" style="margin-top:13px">ذخیره مشخصات</button>
                </form>
            @else
                <p>{{ $business->description ?: 'توضیح بیشتری ثبت نشده است.' }}</p>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="head"><div><h2>📞 راه‌های ارتباطی کسب‌وکار</h2><p>اطلاعات تماس کسب‌وکار از اطلاعات شخصی اعضا جداست.</p></div></div>
        <div class="body">
            <div class="list">
                @forelse($business->contactPoints as $point)
                    <div class="item row">
                        <div><strong style="font-size:17px">{{ $point->label ?: $point->kind }}</strong><div dir="ltr" style="font-weight:900;margin-top:4px">{{ $point->value }}</div><span class="badge">{{ $point->visibility }}</span></div>
                        @if($canManage)<form method="POST" action="{{ route('businesses.contacts.destroy',[$business,$point]) }}">@csrf @method('DELETE')<button class="btn danger">حذف</button></form>@endif
                    </div>
                @empty
                    <div style="color:#667085">هنوز شماره یا ایمیل کسب‌وکار ثبت نشده است.</div>
                @endforelse
            </div>

            @if($canManage)
                <form class="section-form" method="POST" action="{{ route('businesses.contacts.store',$business) }}">
                    @csrf
                    <div class="grid3">
                        <label><span>نوع</span><select name="kind"><option value="mobile">موبایل</option><option value="phone">تلفن</option><option value="email">ایمیل</option><option value="website">وب‌سایت</option><option value="whatsapp">واتساپ</option></select></label>
                        <label><span>عنوان</span><input name="label" placeholder="مثلاً تلفن دفتر"></label>
                        <label><span>نمایش</span><select name="visibility"><option value="private">خصوصی</option><option value="members">اعضا</option><option value="public">عمومی</option></select></label>
                    </div>
                    <label style="display:block;margin-top:10px"><span>مقدار</span><input name="value" required></label>
                    <label style="display:flex;gap:8px;align-items:center;margin-top:10px"><input style="width:20px" type="checkbox" name="is_primary" value="1"><span style="margin:0">اصلی باشد</span></label>
                    <button class="btn green">＋ افزودن تماس</button>
                </form>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="head"><div><h2>📍 مکان‌ها و شعبه‌ها</h2><p>دفتر، شعبه، کارگاه، انبار یا محل پروژه</p></div></div>
        <div class="body">
            <div class="list">
                @forelse($business->addresses as $address)
                    <div class="item row">
                        <div><strong>{{ $address->label ?: $address->type }}</strong><div style="margin-top:4px">{{ collect([$address->province,$address->city,$address->district,$address->street])->filter()->join('، ') }}</div></div>
                        @if($canManage)<form method="POST" action="{{ route('businesses.addresses.destroy',[$business,$address]) }}">@csrf @method('DELETE')<button class="btn danger">حذف</button></form>@endif
                    </div>
                @empty
                    <div style="color:#667085">هنوز مکان یا شعبه‌ای ثبت نشده است.</div>
                @endforelse
            </div>

            @if($canManage)
                <form class="section-form" method="POST" action="{{ route('businesses.addresses.store',$business) }}">
                    @csrf
                    <div class="grid3">
                        <label><span>نوع</span><select name="type"><option value="work">محل کار</option><option value="branch">شعبه</option><option value="billing">صورتحساب</option><option value="shipping">ارسال</option><option value="project_site">محل پروژه</option><option value="other">سایر</option></select></label>
                        <label><span>عنوان</span><input name="label" placeholder="مثلاً شعبه مرکزی"></label>
                        <label><span>نمایش</span><select name="visibility"><option value="private">خصوصی</option><option value="members">اعضا</option><option value="public">عمومی</option></select></label>
                        <label><span>استان</span><input name="province"></label>
                        <label><span>شهر *</span><input name="city" required></label>
                        <label><span>محله</span><input name="district"></label>
                        <label><span>خیابان</span><input name="street"></label>
                        <label><span>کوچه</span><input name="alley"></label>
                        <label><span>پلاک</span><input name="building_no"></label>
                    </div>
                    <input type="hidden" name="country_code" value="IR">
                    <button class="btn green" style="margin-top:12px">＋ افزودن مکان</button>
                </form>
            @endif
        </div>
    </section>

    @if($canOperate)
    <section id="team-settings" class="panel">
        <div class="head">
            <div><h2>👥 اعضای کسب‌وکار</h2><p>عضویت با تخصص فرق دارد؛ گروه همکاری هم بعداً یک لایه جدا باقی می‌ماند.</p></div>
            <a class="btn light" href="{{ route('profile.professions.index') }}">تخصص‌های شخصی من</a>
        </div>
        <div class="body">
            <div class="list">
                @foreach($business->memberships as $membership)
                    <article class="item">
                        <div class="row">
                            <div>
                                <strong style="font-size:18px">{{ $membership->actor->user?->username ?? $membership->actor->user?->email ?? ('Actor #'.$membership->actor->getKey()) }}</strong>
                                <div style="margin-top:6px">
                                    <span class="badge {{ $membership->role }}">{{ $roleLabels[$membership->role] ?? $membership->role }}</span>
                                    @if($membership->job_title)<span class="badge">{{ $membership->job_title }}</span>@endif
                                    @foreach($membership->professions as $profession)<span class="badge profession">{{ $profession->name_fa ?: $profession->name }}</span>@endforeach
                                </div>
                            </div>
                            @if($canManage && $membership->role !== 'owner')
                                <form method="POST" action="{{ route('businesses.members.destroy',[$business,$membership]) }}" onsubmit="return confirm('این عضو از تیم فعال خارج شود؟')">@csrf @method('DELETE')<button class="btn danger">خروج از تیم</button></form>
                            @endif
                        </div>

                        @if($canManage && $membership->role !== 'owner')
                            <form method="POST" action="{{ route('businesses.members.update',[$business,$membership]) }}" class="section-form">
                                @csrf @method('PUT')
                                <div class="grid">
                                    <label><span>نقش مدیریتی</span><select name="role"><option value="member" @selected($membership->role==='member')>عضو</option><option value="manager" @selected($membership->role==='manager')>مدیر</option></select></label>
                                    <label><span>عنوان کاری</span><input name="job_title" value="{{ $membership->job_title }}" placeholder="مثلاً سرپرست اجرایی"></label>
                                </div>
                                <button class="btn light" style="margin-top:10px">ذخیره نقش</button>
                            </form>
                        @endif

                        @if($canManage)
                            <form method="POST" action="{{ route('businesses.members.professions.store',[$business,$membership]) }}" class="section-form">
                                @csrf
                                <div class="grid">
                                    <label><span>تخصص در این کسب‌وکار</span><select name="profession_id">
                                        @foreach($professions as $profession)
                                            <option value="{{ $profession->id }}">{{ $profession->parent ? (($profession->parent->name_fa ?: $profession->parent->name).' ← ') : '' }}{{ $profession->name_fa ?: $profession->name }}</option>
                                        @endforeach
                                    </select></label>
                                    <label style="display:flex;gap:8px;align-items:end;padding-bottom:10px"><input style="width:20px" type="checkbox" name="is_primary" value="1"><span style="margin:0">تخصص اصلی در این تیم</span></label>
                                </div>
                                <button class="btn light">＋ افزودن تخصص</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>

            @if($canManage)
                <form class="section-form" method="POST" action="{{ route('businesses.members.store',$business) }}">
                    @csrf
                    <h3 style="margin-top:0">＋ افزودن کاربر موجود به کسب‌وکار</h3>
                    <div class="grid3">
                        <label><span>ایمیل یا نام کاربری</span><input name="user" required></label>
                        <label><span>نقش</span><select name="role"><option value="member">عضو</option><option value="manager">مدیر</option></select></label>
                        <label><span>عنوان کاری</span><input name="job_title" placeholder="مثلاً برق‌کار"></label>
                    </div>
                    <button class="btn primary" style="margin-top:12px">افزودن عضو</button>
                </form>
            @endif

            @if($canManageOwnership)
                <form class="section-form" method="POST" action="{{ route('businesses.transfer-ownership',$business) }}">
                    @csrf
                    <h3 style="margin-top:0;color:#92400e">انتقال مالکیت</h3>
                    <p style="color:#667085">فقط به یک عضو فعال منتقل می‌شود. مالک فعلی بعد از انتقال «مدیر» می‌شود تا کسب‌وکار بدون مدیر نماند.</p>
                    <select name="actor_id" required>
                        <option value="">انتخاب مالک جدید...</option>
                        @foreach($business->memberships->where('role','!=','owner') as $candidate)
                            <option value="{{ $candidate->actor_id }}">{{ $candidate->actor->user?->username ?? $candidate->actor->user?->email ?? ('Actor #'.$candidate->actor->getKey()) }}</option>
                        @endforeach
                    </select>
                    <button class="btn" style="margin-top:10px;background:#b45309;color:#fff" onclick="return confirm('مالکیت منتقل شود؟')">انتقال مالکیت</button>
                </form>
            @endif
        </div>
    </section>
    @endif
</main>
@endsection
