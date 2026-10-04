@extends('layouts.app')
@section('title', __('business.clients.title').' — '.$business->name)

@section('content')
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
            <h1>{{ __('business.clients.heading') }}</h1>
            <p class="muted">{{ __('business.clients.intro') }}</p>
        </div>
        <a class="btn light" href="{{ route('businesses.catalog.index',$business) }}">{{ __('business.clients.catalog') }}</a>
    </header>

    @if($canManage)
        <section class="panel">
            <h2 style="margin-top:0">＋ {{ __('business.clients.add_title') }}</h2>
            <form method="POST" action="{{ route('businesses.clients.store',$business) }}">
                @csrf
                <div class="grid">
                    <label><span>{{ __('business.clients.name') }}</span><input name="display_name" required maxlength="160"></label>
                    <label><span>{{ __('business.clients.primary_phone') }}</span><input name="phone" required dir="ltr"></label>
                    <label><span>{{ __('business.clients.secondary_phone') }}</span><input name="secondary_phone" dir="ltr"></label>
                    <label><span>{{ __('business.clients.source') }}</span><input name="source" placeholder="{{ __('business.clients.source_placeholder') }}"></label>
                    <label><span>{{ __('business.clients.city') }}</span><input name="city"></label>
                    <label><span>{{ __('business.clients.district') }}</span><input name="district"></label>
                </div>
                <label style="display:block;margin-top:12px"><span>{{ __('business.clients.street') }}</span><input name="street"></label>
                <label style="display:block;margin-top:12px"><span>{{ __('business.clients.notes') }}</span><textarea name="notes" rows="3"></textarea></label>
                <button class="btn primary" style="margin-top:12px">{{ __('business.clients.save') }}</button>
            </form>
        </section>
    @endif

    <section class="panel">
        <form method="GET" class="search">
            <label><span>{{ __('business.clients.search') }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ __('business.clients.search_placeholder') }}"></label>
            <button class="btn primary">{{ __('business.clients.search') }}</button>
            @if(request('q'))<a class="btn light" href="{{ route('businesses.clients.index',$business) }}">{{ __('business.clients.clear') }}</a>@endif
        </form>
    </section>

    <section class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px">
            <h2 style="margin:0">{{ __('business.clients.directory') }}</h2>
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
                            <div class="muted" style="margin-top:6px">{{ collect([$address->city,$address->district,$address->street])->filter()->join(' · ') }}</div>
                        @endforeach
                    </div>
                    @if($canManage && $canUseMarket && $client->status === 'active')
                        @php($marketNeed = $marketNeedsByContact->get($client->uuid))
                        <div style="min-width:min(100%,360px)">
                            @if($marketNeed)
                                <a class="btn primary" href="{{ route('intents.matches',$marketNeed) }}">
                                    {{ __('business.clients.active_need') }}
                                </a>
                            @else
                            <details>
                                <summary class="btn primary" style="list-style:none">{{ __('business.clients.publish_need') }}</summary>
                                <form method="POST" action="{{ route('businesses.clients.needs.store',[$business,$client]) }}" style="margin-top:10px;padding:12px;border:1px dashed #cbd5e1;border-radius:14px">
                                    @csrf
                                    <label><span>{{ __('business.clients.need_concept') }}</span><input name="concept_label" required placeholder="{{ __('business.clients.need_concept_placeholder') }}"></label>
                                    <label style="display:block;margin-top:8px"><span>{{ __('business.clients.need_title') }}</span><input name="title"></label>
                                    <div class="grid" style="margin-top:8px">
                                        <label><span>{{ __('business.clients.type') }}</span>
                                            <select name="subject_kind">
                                                <option value="service">{{ __('business.clients.types.service') }}</option>
                                                <option value="good">{{ __('business.clients.types.good') }}</option>
                                                <option value="property">{{ __('business.clients.types.property') }}</option>
                                                <option value="other">{{ __('business.clients.types.other') }}</option>
                                            </select>
                                        </label>
                                        <label><span>{{ __('business.clients.arrangement') }}</span>
                                            <select name="arrangement_kind">
                                                <option value="service">{{ __('business.clients.types.service') }}</option>
                                                <option value="ownership_transfer">{{ __('business.clients.arrangements.ownership_transfer') }}</option>
                                                <option value="temporary_use">{{ __('business.clients.arrangements.temporary_use') }}</option>
                                                <option value="other">{{ __('business.clients.types.other') }}</option>
                                            </select>
                                        </label>
                                        <label><span>{{ __('business.clients.location') }}</span><input name="location_text"></label>
                                    </div>
                                    <label style="display:block;margin-top:8px"><span>{{ __('business.clients.description') }}</span><textarea name="description" rows="2">{{ $client->notes }}</textarea></label>
                                    <button class="btn primary" style="margin-top:8px">{{ __('business.clients.publish_and_match') }}</button>
                                </form>
                            </details>
                            @endif
                            <form method="POST" action="{{ route('businesses.clients.archive',[$business,$client]) }}" onsubmit="return confirm('{{ __('business.clients.archive_confirm') }}')" style="margin-top:8px">
                                @csrf @method('PATCH')
                                <button class="btn danger">{{ __('business.clients.archive') }}</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="muted" style="text-align:center;padding:30px">{{ __('business.clients.empty') }}</div>
            @endforelse
        </div>
        @if($clients->hasPages())<div style="margin-top:16px">{{ $clients->links() }}</div>@endif
    </section>
</main>
@endsection
