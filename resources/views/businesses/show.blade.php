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
        .site-link{display:inline-flex;margin-top:10px;border-radius:14px;padding:10px 14px;background:#fff;color:#0f766e;font-weight:950;text-decoration:none;box-shadow:0 6px 18px rgba(15,23,42,.12)}
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
                <p>{{ $business->short_intro ?: __('business.show.missing_intro') }}</p>
            </div>
            <div style="text-align:left">
                <div class="chip">{{ __('business.show.code') }} {{ $business->code }}</div>
                @if($canOperate)
                    <div style="margin-top:8px;font-size:13px;opacity:.9">{{ __('business.show.owner') }}: {{ $business->owner->user?->username ?? $business->owner->user?->email ?? ('Actor #'.$business->owner->getKey()) }}</div>
                @endif
                @if($publicSiteUrl)
                    <a class="site-link" target="_blank" rel="noopener" href="{{ $publicSiteUrl }}">🌐 {{ __('business.public_site.open') }}</a>
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
        <a class="stat" style="text-decoration:none;color:inherit" href="#team-settings"><span>{{ __('business.show.stats.members') }}</span><strong>{{ $business->memberships->count() }}</strong></a>
        <a class="stat" style="text-decoration:none;color:inherit" href="{{ route('businesses.clients.index', $business) }}"><span>{{ __('business.show.stats.clients') }}</span><strong>{{ $business->businessContacts->count() }}</strong></a>
        <a class="stat" style="text-decoration:none;color:inherit" href="{{ route('businesses.catalog.index', $business) }}"><span>{{ __('business.show.stats.listings') }}</span><strong>{{ $business->listings->count() }}</strong></a>
        @if($canUsePlanner)
            <a class="stat" style="text-decoration:none;color:inherit" href="{{ route('planner.index', ['context' => $businessContext->uuid]) }}"><span>{{ __('business.show.stats.routines') }}</span><strong>{{ $routineCount }}</strong></a>
        @endif
    </section>

    <section class="quick">
        <a class="quick-card q1" style="text-decoration:none" href="{{ route('businesses.clients.index',$business) }}">
            <strong>👤 {{ __('business.show.quick.clients') }}</strong>
            <span>{{ __('business.show.quick.clients_help') }}</span>
        </a>
        <a class="quick-card q2" style="text-decoration:none" href="{{ route('businesses.catalog.index',$business) }}">
            <strong>🧰 {{ __('business.show.quick.catalog') }}</strong>
            <span>{{ __('business.show.quick.catalog_help') }}</span>
        </a>
        @if($canUsePlanner)
            <a class="quick-card q3" style="text-decoration:none" href="{{ route('planner.index',['context'=>$businessContext->uuid]) }}">
                <strong>🗓 {{ __('business.show.quick.planner') }}</strong>
                <span>{{ __('business.show.quick.planner_help') }}</span>
            </a>
            <a class="quick-card q1" style="text-decoration:none" href="{{ route('planner.create',['context'=>$businessContext->uuid]) }}">
                <strong>＋ {{ __('business.show.quick.new_plan') }}</strong>
                <span>{{ __('business.show.quick.new_plan_help') }}</span>
            </a>
        @endif
    </section>
    @endif

    @if($canOperate)
    <section id="public-site" class="panel">
        <div class="head">
            <div>
                <h2>🌐 {{ __('business.public_site.title') }}</h2>
                <p>{{ __('business.public_site.help') }}</p>
            </div>
            @if($publicSiteUrl)
                <a class="btn green" target="_blank" rel="noopener" href="{{ $publicSiteUrl }}">{{ __('business.public_site.open') }}</a>
            @else
                <span class="badge">{{ __('business.public_site.not_live') }}</span>
            @endif
        </div>
        <div class="body">
            @if($publicSiteUrl)
                <div class="item">
                    <strong>{{ __('business.public_site.live') }}</strong>
                    <div class="muted" style="margin-top:6px;direction:ltr;text-align:left">{{ $publicSiteUrl }}</div>
                </div>
            @elseif($canManage)
                <div class="item">{{ __('business.public_site.publish_help') }}</div>
            @endif

            @if($canManage)
                <form class="section-form" method="POST" action="{{ route('businesses.public-site.update', $business) }}">
                    @csrf @method('PUT')
                    <h3 style="margin:0">{{ __('business.public_site.featured_title') }}</h3>
                    <p style="color:#667085;line-height:1.8">{{ __('business.public_site.featured_help') }}</p>
                    @if($publicSiteCandidates->isNotEmpty())
                        <div class="grid">
                            @foreach($publicSiteCandidates as $candidate)
                                <label class="item" style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
                                    <input style="width:auto!important;margin-top:4px" type="checkbox" name="featured_business_ids[]" value="{{ $candidate->id }}" @checked($featuredBusinessIds->contains((int) $candidate->id))>
                                    <span style="margin:0">
                                        <strong>{{ $candidate->name }}</strong>
                                        @if($candidate->short_intro)
                                            <small style="display:block;color:#667085;margin-top:4px">{{ $candidate->short_intro }}</small>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <button class="btn primary" style="margin-top:12px">{{ __('business.public_site.save') }}</button>
                    @else
                        <div class="item">{{ __('business.public_site.no_candidates') }}</div>
                    @endif
                </form>
            @endif
        </div>
    </section>
    @endif

    @if($canOperate && $canUseMoney)
        <section class="panel">
            <div class="head">
                <div>
                    <h2>💰 {{ __('business.show.money.title') }}</h2>
                    <p>{{ __('business.show.money.help') }}</p>
                </div>
            </div>
            <div class="body">
                <div class="grid3">
                    <div class="item">
                        <strong>{{ __('business.show.money.position') }}</strong>
                        <div style="font-size:26px;font-weight:950;margin-top:6px">
                            {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_net_minor'] ?? 0), 0) }} IET
                        </div>
                        <div class="muted" style="margin-top:5px">
                            {{ __('business.show.money.receivable') }} {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_receivable_minor'] ?? 0), 0) }}
                            · {{ __('business.show.money.payable') }} {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_payable_minor'] ?? 0), 0) }} IET
                        </div>
                        <div class="muted" style="margin-top:5px">
                            {{ __('business.show.money.revenue') }} {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_realized_revenue_minor'] ?? 0), 0) }}
                            · {{ __('business.show.money.expense') }} {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_realized_expense_minor'] ?? 0), 0) }}
                            · {{ __('business.show.money.profit') }} {{ \App\Support\MoneyAmount::format((int)($economyProjection['iet_realized_profit_minor'] ?? 0), 0) }} IET
                        </div>
                        <div class="badges" style="margin-top:8px">
                            <span class="badge">{{ $economyProjection['market_intent_count'] ?? 0 }} {{ __('business.show.money.market') }}</span>
                            <span class="badge">{{ $economyProjection['deal_count'] ?? 0 }} {{ __('business.show.money.deals') }}</span>
                            <span class="badge">{{ $economyProjection['contract_count'] ?? 0 }} {{ __('business.show.money.contracts') }}</span>
                        </div>
                    </div>
                    <div class="item">
                        <strong>{{ __('business.show.money.unit') }}</strong>
                        <div style="font-size:26px;font-weight:950;margin-top:6px">{{ $business->defaultMonetaryUnit?->code ?? 'IET' }}</div>
                        <div class="muted" style="margin-top:5px">{{ __('business.show.money.unit_help') }}</div>
                    </div>
                    <div class="item">
                        <strong>{{ __('business.show.money.external') }}</strong>
                        <div class="badge" style="margin-top:9px">{{ __('business.show.money.external_status') }}</div>
                        <div class="muted" style="margin-top:5px">{{ __('business.show.money.external_help') }}</div>
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
                    <h2>🏠 {{ __('business.show.real_estate.title') }}</h2>
                    <p>{{ __('business.show.real_estate.help') }}</p>
                </div>
            </div>
            <div class="body">
                @foreach($business->publicIntakePortals as $portal)
                    <div class="item row">
                        <div>
                            <strong>{{ $portal->title }}</strong>
                            <div class="muted" style="margin-top:5px">{{ __('business.show.real_estate.preserved') }}</div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <a class="btn light" href="{{ route('office.real-estate.index',['portal'=>$portal->uuid]) }}">{{ __('business.show.real_estate.cases') }}</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section id="business-profile" class="panel">
        <div class="head"><div><h2>🏪 {{ __('business.show.profile.title') }}</h2><p>{{ __('business.show.profile.help') }}</p></div></div>
        <div class="body">
            @if($canManage)
                <form method="POST" action="{{ route('businesses.update',$business) }}">
                    @csrf @method('PUT')
                    <div class="grid3">
                        <label><span>{{ __('business.show.profile.name') }}</span><input name="name" value="{{ $business->name }}" required></label>
                        <label><span>{{ __('business.show.profile.type') }}</span><select name="kind">@foreach($kindLabels as $v=>$label)<option value="{{ $v }}" @selected($business->kind===$v)>{{ $label }}</option>@endforeach</select></label>
                        <label><span>{{ __('business.show.profile.visibility') }}</span><select name="visibility">@foreach($visibilityLabels as $v=>$label)<option value="{{ $v }}" @selected($business->visibility===$v)>{{ $label }}</option>@endforeach</select></label>
                        <label><span>{{ __('business.show.profile.legal_name') }}</span><input name="legal_name" value="{{ $business->legal_name }}"></label>
                        <label><span>{{ __('business.show.profile.founded_year') }}</span><input type="number" name="founded_year" value="{{ $business->founded_year }}"></label>
                        <label><span>{{ __('business.show.profile.status') }}</span><select name="status"><option value="active" @selected($business->status==='active')>{{ __('business.show.profile.active') }}</option><option value="paused" @selected($business->status==='paused')>{{ __('business.show.profile.paused') }}</option></select></label>
                    </div>
                    <label style="display:block;margin-top:12px"><span>{{ __('business.show.profile.short_intro') }}</span><input name="short_intro" value="{{ $business->short_intro }}"></label>
                    <label style="display:block;margin-top:12px"><span>{{ __('business.show.profile.description') }}</span><textarea name="description" rows="4">{{ $business->description }}</textarea></label>
                    <button class="btn primary" style="margin-top:13px">{{ __('business.show.profile.save') }}</button>
                </form>
            @else
                <p>{{ $business->description ?: __('business.show.profile.empty_description') }}</p>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="head"><div><h2>📞 {{ __('business.show.contacts.title') }}</h2><p>{{ __('business.show.contacts.help') }}</p></div></div>
        <div class="body">
            <div class="list">
                @forelse($business->contactPoints as $point)
                    <div class="item row">
                        <div><strong style="font-size:17px">{{ $point->label ?: $point->kind }}</strong><div dir="ltr" style="font-weight:900;margin-top:4px">{{ $point->value }}</div><span class="badge">{{ $point->visibility }}</span></div>
                        @if($canManage)<form method="POST" action="{{ route('businesses.contacts.destroy',[$business,$point]) }}">@csrf @method('DELETE')<button class="btn danger">{{ __('business.show.contacts.delete') }}</button></form>@endif
                    </div>
                @empty
                    <div style="color:#667085">{{ __('business.show.contacts.empty') }}</div>
                @endforelse
            </div>

            @if($canManage)
                <form class="section-form" method="POST" action="{{ route('businesses.contacts.store',$business) }}">
                    @csrf
                    <div class="grid3">
                        <label><span>{{ __('business.show.contacts.type') }}</span><select name="kind"><option value="mobile">{{ __('business.show.contacts.kinds.mobile') }}</option><option value="phone">{{ __('business.show.contacts.kinds.phone') }}</option><option value="email">{{ __('business.show.contacts.kinds.email') }}</option><option value="website">{{ __('business.show.contacts.kinds.website') }}</option><option value="whatsapp">{{ __('business.show.contacts.kinds.whatsapp') }}</option></select></label>
                        <label><span>{{ __('business.show.contacts.label') }}</span><input name="label" placeholder="{{ __('business.show.contacts.label_placeholder') }}"></label>
                        <label><span>{{ __('business.show.contacts.visibility') }}</span><select name="visibility">@foreach($visibilityLabels as $v=>$label)<option value="{{ $v }}">{{ $label }}</option>@endforeach</select></label>
                    </div>
                    <label style="display:block;margin-top:10px"><span>{{ __('business.show.contacts.value') }}</span><input name="value" required></label>
                    <label style="display:flex;gap:8px;align-items:center;margin-top:10px"><input style="width:20px" type="checkbox" name="is_primary" value="1"><span style="margin:0">{{ __('business.show.contacts.primary') }}</span></label>
                    <button class="btn green">＋ {{ __('business.show.contacts.add') }}</button>
                </form>
            @endif
        </div>
    </section>

    <section class="panel">
        <div class="head"><div><h2>📍 {{ __('business.show.addresses.title') }}</h2><p>{{ __('business.show.addresses.help') }}</p></div></div>
        <div class="body">
            <div class="list">
                @forelse($business->addresses as $address)
                    <div class="item row">
                        <div><strong>{{ $address->label ?: $address->type }}</strong><div style="margin-top:4px">{{ collect([$address->province,$address->city,$address->district,$address->street])->filter()->join(' · ') }}</div></div>
                        @if($canManage)<form method="POST" action="{{ route('businesses.addresses.destroy',[$business,$address]) }}">@csrf @method('DELETE')<button class="btn danger">{{ __('business.show.addresses.delete') }}</button></form>@endif
                    </div>
                @empty
                    <div style="color:#667085">{{ __('business.show.addresses.empty') }}</div>
                @endforelse
            </div>

            @if($canManage)
                <form class="section-form" method="POST" action="{{ route('businesses.addresses.store',$business) }}">
                    @csrf
                    <div class="grid3">
                        <label><span>{{ __('business.show.addresses.type') }}</span><select name="type"><option value="work">{{ __('business.show.addresses.types.work') }}</option><option value="branch">{{ __('business.show.addresses.types.branch') }}</option><option value="billing">{{ __('business.show.addresses.types.billing') }}</option><option value="shipping">{{ __('business.show.addresses.types.shipping') }}</option><option value="project_site">{{ __('business.show.addresses.types.project_site') }}</option><option value="other">{{ __('business.show.addresses.types.other') }}</option></select></label>
                        <label><span>{{ __('business.show.addresses.label') }}</span><input name="label" placeholder="{{ __('business.show.addresses.label_placeholder') }}"></label>
                        <label><span>{{ __('business.show.addresses.visibility') }}</span><select name="visibility">@foreach($visibilityLabels as $v=>$label)<option value="{{ $v }}">{{ $label }}</option>@endforeach</select></label>
                        <label><span>{{ __('business.show.addresses.province') }}</span><input name="province"></label>
                        <label><span>{{ __('business.show.addresses.city') }} *</span><input name="city" required></label>
                        <label><span>{{ __('business.show.addresses.district') }}</span><input name="district"></label>
                        <label><span>{{ __('business.show.addresses.street') }}</span><input name="street"></label>
                        <label><span>{{ __('business.show.addresses.alley') }}</span><input name="alley"></label>
                        <label><span>{{ __('business.show.addresses.building_no') }}</span><input name="building_no"></label>
                    </div>
                    <input type="hidden" name="country_code" value="IR">
                    <button class="btn green" style="margin-top:12px">＋ {{ __('business.show.addresses.add') }}</button>
                </form>
            @endif
        </div>
    </section>

    @if($canOperate)
    <section id="team-settings" class="panel">
        <div class="head">
            <div><h2>👥 {{ __('business.show.team.title') }}</h2><p>{{ __('business.show.team.help') }}</p></div>
            <a class="btn light" href="{{ route('profile.professions.index') }}">{{ __('business.show.team.my_professions') }}</a>
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
                                <form method="POST" action="{{ route('businesses.members.destroy',[$business,$membership]) }}" onsubmit="return confirm(@js(__('business.show.team.remove_confirm')))">@csrf @method('DELETE')<button class="btn danger">{{ __('business.show.team.remove') }}</button></form>
                            @endif
                        </div>

                        @if($canManage && $membership->role !== 'owner')
                            <form method="POST" action="{{ route('businesses.members.update',[$business,$membership]) }}" class="section-form">
                                @csrf @method('PUT')
                                <div class="grid">
                                    <label><span>{{ __('business.show.team.management_role') }}</span><select name="role"><option value="member" @selected($membership->role==='member')>{{ __('business.show.team.member') }}</option><option value="manager" @selected($membership->role==='manager')>{{ __('business.show.team.manager') }}</option></select></label>
                                    <label><span>{{ __('business.show.team.job_title') }}</span><input name="job_title" value="{{ $membership->job_title }}" placeholder="{{ __('business.show.team.job_title_placeholder') }}"></label>
                                </div>
                                <button class="btn light" style="margin-top:10px">{{ __('business.show.team.save_role') }}</button>
                            </form>
                        @endif

                        @if($canManage)
                            <form method="POST" action="{{ route('businesses.members.professions.store',[$business,$membership]) }}" class="section-form">
                                @csrf
                                <div class="grid">
                                    <label><span>{{ __('business.show.team.profession') }}</span><select name="profession_id">
                                        @foreach($professions as $profession)
                                            <option value="{{ $profession->id }}">{{ $profession->parent ? (($profession->parent->name_fa ?: $profession->parent->name).' ← ') : '' }}{{ $profession->name_fa ?: $profession->name }}</option>
                                        @endforeach
                                    </select></label>
                                    <label style="display:flex;gap:8px;align-items:end;padding-bottom:10px"><input style="width:20px" type="checkbox" name="is_primary" value="1"><span style="margin:0">{{ __('business.show.team.primary_profession') }}</span></label>
                                </div>
                                <button class="btn light">＋ {{ __('business.show.team.add_profession') }}</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>

            @if($canManage)
                <form class="section-form" method="POST" action="{{ route('businesses.members.store',$business) }}">
                    @csrf
                    <h3 style="margin-top:0">＋ {{ __('business.show.team.add_user') }}</h3>
                    <div class="grid3">
                        <label><span>{{ __('business.show.team.user') }}</span><input name="user" required></label>
                        <label><span>{{ __('business.show.team.role') }}</span><select name="role"><option value="member">{{ __('business.show.team.member') }}</option><option value="manager">{{ __('business.show.team.manager') }}</option></select></label>
                        <label><span>{{ __('business.show.team.job_title') }}</span><input name="job_title" placeholder="{{ __('business.show.team.job_placeholder') }}"></label>
                    </div>
                    <button class="btn primary" style="margin-top:12px">{{ __('business.show.team.add_member') }}</button>
                </form>
            @endif

            @if($canManageOwnership)
                <form class="section-form" method="POST" action="{{ route('businesses.transfer-ownership',$business) }}">
                    @csrf
                    <h3 style="margin-top:0;color:#92400e">{{ __('business.show.team.transfer_title') }}</h3>
                    <p style="color:#667085">{{ __('business.show.team.transfer_help') }}</p>
                    <select name="actor_id" required>
                        <option value="">{{ __('business.show.team.select_owner') }}</option>
                        @foreach($business->memberships->where('role','!=','owner') as $candidate)
                            <option value="{{ $candidate->actor_id }}">{{ $candidate->actor->user?->username ?? $candidate->actor->user?->email ?? ('Actor #'.$candidate->actor->getKey()) }}</option>
                        @endforeach
                    </select>
                    <button class="btn" style="margin-top:10px;background:#b45309;color:#fff" onclick="return confirm(@js(__('business.show.team.transfer_confirm')))">{{ __('business.show.team.transfer') }}</button>
                </form>
            @endif
        </div>
    </section>
    @endif
</main>
@endsection
