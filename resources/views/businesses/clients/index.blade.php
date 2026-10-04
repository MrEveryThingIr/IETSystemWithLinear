@extends('layouts.app')
@section('title', (app()->getLocale() === 'fa' ? 'مشتریان — ' : 'Clients — ').$business->name)

@section('content')
@php($fa = app()->getLocale() === 'fa')
<style>
.crm{max-width:1180px;margin:auto;padding:26px 16px 70px}.head{display:flex;justify-content:space-between;gap:14px;align-items:end;flex-wrap:wrap}.head h1{font-size:32px;margin:5px 0}.muted{color:#667085;line-height:1.8}.panel{background:#fff;border:1px solid #e4e7ec;border-radius:24px;padding:20px;margin-top:18px;box-shadow:0 7px 24px rgba(15,23,42,.04)}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.clients{display:grid;gap:10px}.client{border:1px solid #e2e8f0;border-radius:18px;padding:16px;background:#fff;display:flex;justify-content:space-between;gap:16px;align-items:start;flex-wrap:wrap}.badges{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}.badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:12px;font-weight:850}
.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:0;border-radius:13px;padding:10px 14px;font-weight:900;cursor:pointer}.primary{background:#4f46e5;color:#fff}.light{background:#fff;color:#334155;border:1px solid #cbd5e1}.danger{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}
label span{display:block;font-size:13px;font-weight:850;margin-bottom:6px}input,select,textarea{width:100%;border:1.5px solid #aab4c4;border-radius:12px;padding:11px;background:#fff}.status{background:#ecfdf5;color:#065f46;font-weight:900}.search{display:flex;gap:8px;align-items:end}.search label{flex:1}
@media(max-width:800px){.grid{grid-template-columns:1fr}.search{align-items:stretch;flex-direction:column}}
</style>
<main class="crm">
    @if(session('status'))<div class="panel status">✓ {{ session('status') }}</div>@endif
    @if($errors->any())<div class="panel" style="background:#fff1f2;color:#9f1239"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <header class="head">
        <div>
            <a href="{{ route('businesses.show',$business) }}" style="text-decoration:none;color:#4f46e5;font-weight:900">← {{ $business->name }}</a>
            <h1>{{ $fa ? 'مشتریان و مخاطبان' : 'Clients & contacts' }}</h1>
            <p class="muted">{{ $fa ? 'افراد واقعی را بدون اجبار به ثبت‌نام در IET ثبت کنید. اگر بعداً عضو IET شدند، همین Contact می‌تواند به Actor مربوطه متصل شود.' : 'Record real-world people without requiring IET registration. If they later join IET, this Contact can be linked to their Actor.' }}</p>
        </div>
        <a class="btn light" href="{{ route('businesses.catalog.index',$business) }}">{{ $fa ? 'کاتالوگ' : 'Catalog' }}</a>
    </header>

    @if($canManage)
        <section class="panel">
            <h2 style="margin-top:0">{{ $fa ? '＋ ثبت مراجعه‌کننده / مشتری' : '+ Add client/contact' }}</h2>
            <form method="POST" action="{{ route('businesses.clients.store',$business) }}">
                @csrf
                <div class="grid">
                    <label><span>{{ $fa ? 'نام' : 'Name' }}</span><input name="display_name" required maxlength="160"></label>
                    <label><span>{{ $fa ? 'تلفن اصلی' : 'Primary phone' }}</span><input name="phone" required dir="ltr"></label>
                    <label><span>{{ $fa ? 'تلفن دوم' : 'Secondary phone' }}</span><input name="secondary_phone" dir="ltr"></label>
                    <label><span>{{ $fa ? 'منبع / معرف' : 'Source / referrer' }}</span><input name="source" placeholder="{{ $fa ? 'مثلاً مراجعه حضوری، معرفی علی...' : 'Walk-in, referral, campaign...' }}"></label>
                    <label><span>{{ $fa ? 'شهر' : 'City' }}</span><input name="city"></label>
                    <label><span>{{ $fa ? 'محله' : 'District' }}</span><input name="district"></label>
                </div>
                <label style="display:block;margin-top:12px"><span>{{ $fa ? 'خیابان / آدرس کوتاه' : 'Street / short address' }}</span><input name="street"></label>
                <label style="display:block;margin-top:12px"><span>{{ $fa ? 'یادداشت خصوصی' : 'Private notes' }}</span><textarea name="notes" rows="3"></textarea></label>
                <button class="btn primary" style="margin-top:12px">{{ $fa ? 'ذخیره مخاطب' : 'Save contact' }}</button>
            </form>
        </section>
    @endif

    <section class="panel">
        <form method="GET" class="search">
            <label><span>{{ $fa ? 'جست‌وجو' : 'Search' }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ $fa ? 'نام، تلفن، یادداشت، معرف...' : 'Name, phone, notes, source...' }}"></label>
            <button class="btn primary">{{ $fa ? 'جست‌وجو' : 'Search' }}</button>
            @if(request('q'))<a class="btn light" href="{{ route('businesses.clients.index',$business) }}">{{ $fa ? 'پاک کردن' : 'Clear' }}</a>@endif
        </form>
    </section>

    <section class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px">
            <h2 style="margin:0">{{ $fa ? 'دفتر مشتریان' : 'Client directory' }}</h2>
            <span class="badge">{{ $clients->total() }}</span>
        </div>
        <div class="clients">
            @forelse($clients as $client)
                <article class="client">
                    <div>
                        <strong style="font-size:18px">{{ $client->display_name }}</strong>
                        <div class="badges">
                            <span class="badge">{{ $client->status }}</span>
                            @if($client->source)<span class="badge">{{ $client->source }}</span>@endif
                            @foreach($client->contactPoints as $point)
                                <span class="badge" dir="ltr">{{ $point->value }}</span>
                            @endforeach
                        </div>
                        @if($client->notes)<p class="muted" style="margin:10px 0 0;white-space:pre-line">{{ $client->notes }}</p>@endif
                        @foreach($client->addresses as $address)
                            <div class="muted" style="margin-top:6px">{{ collect([$address->city,$address->district,$address->street])->filter()->join('، ') }}</div>
                        @endforeach
                    </div>
                    @if($canManage && $canUseMarket && $client->status === 'active')
                        @php($marketNeed = $marketNeedsByContact->get($client->uuid))
                        <div style="min-width:min(100%,360px)">
                            @if($marketNeed)
                                <a class="btn primary" href="{{ route('intents.matches',$marketNeed) }}">
                                    {{ $fa ? 'نیاز فعال — دیدن تطبیق‌ها' : 'Active Need — review matches' }}
                                </a>
                            @else
                            <details>
                                <summary class="btn primary" style="list-style:none">{{ $fa ? 'ثبت نیاز این مشتری در بازار' : 'Publish a Need for this client' }}</summary>
                                <form method="POST" action="{{ route('businesses.clients.needs.store',[$business,$client]) }}" style="margin-top:10px;padding:12px;border:1px dashed #cbd5e1;border-radius:14px">
                                    @csrf
                                    <label><span>{{ $fa ? 'مفهوم نیاز' : 'Need concept' }}</span><input name="concept_label" required placeholder="{{ $fa ? 'مثلاً ملک مسکونی، تعمیر کولر، خرید دریل' : 'e.g. residential property, AC repair, cordless drill' }}"></label>
                                    <label style="display:block;margin-top:8px"><span>{{ $fa ? 'عنوان' : 'Title' }}</span><input name="title"></label>
                                    <div class="grid" style="margin-top:8px">
                                        <label><span>{{ $fa ? 'نوع' : 'Type' }}</span>
                                            <select name="subject_kind">
                                                <option value="service">{{ $fa ? 'خدمت' : 'Service' }}</option>
                                                <option value="good">{{ $fa ? 'کالا' : 'Good' }}</option>
                                                <option value="property">{{ $fa ? 'ملک' : 'Property' }}</option>
                                                <option value="other">{{ $fa ? 'سایر' : 'Other' }}</option>
                                            </select>
                                        </label>
                                        <label><span>{{ $fa ? 'روش' : 'Arrangement' }}</span>
                                            <select name="arrangement_kind">
                                                <option value="service">{{ $fa ? 'خدمت' : 'Service' }}</option>
                                                <option value="ownership_transfer">{{ $fa ? 'خرید / انتقال مالکیت' : 'Buy / ownership' }}</option>
                                                <option value="temporary_use">{{ $fa ? 'اجاره / استفاده موقت' : 'Rent / temporary use' }}</option>
                                                <option value="other">{{ $fa ? 'سایر' : 'Other' }}</option>
                                            </select>
                                        </label>
                                        <label><span>{{ $fa ? 'محل' : 'Location' }}</span><input name="location_text"></label>
                                    </div>
                                    <label style="display:block;margin-top:8px"><span>{{ $fa ? 'توضیح' : 'Description' }}</span><textarea name="description" rows="2">{{ $client->notes }}</textarea></label>
                                    <button class="btn primary" style="margin-top:8px">{{ $fa ? 'انتشار نیاز و دیدن تطبیق‌ها' : 'Publish Need & see matches' }}</button>
                                </form>
                            </details>
                            @endif
                            <form method="POST" action="{{ route('businesses.clients.archive',[$business,$client]) }}" onsubmit="return confirm('{{ $fa ? 'این مخاطب بایگانی شود؟' : 'Archive this contact?' }}')" style="margin-top:8px">
                                @csrf @method('PATCH')
                                <button class="btn danger">{{ $fa ? 'بایگانی' : 'Archive' }}</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="muted" style="text-align:center;padding:30px">{{ $fa ? 'هنوز مخاطبی ثبت نشده است.' : 'No clients/contacts yet.' }}</div>
            @endforelse
        </div>
        @if($clients->hasPages())<div style="margin-top:16px">{{ $clients->links() }}</div>@endif
    </section>
</main>
@endsection
