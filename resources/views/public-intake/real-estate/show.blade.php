<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Localization::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>{{ $business?->name ?? $portal->title }} — {{ $portal->welcome_heading ?: __('public_business.real_estate.heading') }}</title>
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
    @if($business)
        <div style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            <a href="{{ route('public.businesses.show', ['business' => $business->slug]) }}" style="color:#047857;font-weight:900;text-decoration:none">← {{ $business->name }}</a>
            <div style="display:flex;align-items:center;gap:10px"><span style="font-size:13px;color:#64748b">{{ __('public_business.real_estate.title') }}</span><x-app.locale-switcher /></div>
        </div>
    @endif
    <section class="card">
        <header class="hero">
            <div style="display:inline-block;background:#047857;color:#fff;border-radius:999px;padding:6px 13px;font-weight:800;font-size:13px">{{ $business?->name ?? $portal->title }}</div>
            <h1>{{ $portal->welcome_heading ?: __('public_business.real_estate.heading') }}</h1>
            <p style="line-height:1.9;color:#475569">{{ $portal->welcome_body ?: __('public_real_estate.welcome_body') }}</p>
            <div class="privacy">{{ __('public_real_estate.privacy') }}</div>
        </header>

        <form method="POST" enctype="multipart/form-data" action="{{ route('public.real-estate.store', $portal) }}" class="body">
            @csrf
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

            @if($errors->any())
                <div class="errors">
                    <strong>{{ __('public_real_estate.fix_errors') }}</strong>
                    <ul>
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <section class="section alt">
                <h2>{{ __('public_real_estate.sections.intent') }}</h2>
                <div class="grid">
                    <label><span>{{ __('public_real_estate.intent.label') }}</span>
                        <select name="intent" required>
                            <option value="">{{ __('public_real_estate.select') }}</option>
                            <option value="offer" @selected(old('intent')==='offer')>{{ __('public_real_estate.intent.offer') }}</option>
                            <option value="need" @selected(old('intent')==='need')>{{ __('public_real_estate.intent.need') }}</option>
                        </select>
                    </label>
                    <label><span>{{ __('public_real_estate.transaction.label') }}</span>
                        <select name="transaction_mode" required>
                            <option value="">{{ __('public_real_estate.select') }}</option>
                            <option value="sale" @selected(old('transaction_mode')==='sale')>{{ __('public_real_estate.transaction.sale') }}</option>
                            <option value="rent" @selected(old('transaction_mode')==='rent')>{{ __('public_real_estate.transaction.rent') }}</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="section">
                <h2>{{ __('public_real_estate.sections.contact') }}</h2>
                <div class="grid">
                    <label><span>{{ __('public_real_estate.contact.name') }} *</span>
                        <input name="contact_name" required value="{{ old('contact_name') }}" autocomplete="name">
                    </label>
                    <label><span>{{ __('public_real_estate.contact.phone') }} *</span>
                        <input name="phone" required value="{{ old('phone') }}" inputmode="tel" placeholder="{{ __('public_real_estate.contact.phone_placeholder') }}">
                    </label>
                </div>
            </section>

            <section class="section">
                <h2>{{ __('public_real_estate.sections.property') }}</h2>
                <div class="grid">
                    <label><span>{{ __('public_real_estate.property.class') }} *</span>
                        <select name="property_class" required>
                            <option value="">{{ __('public_real_estate.select') }}</option>
                            <option value="residential" @selected(old('property_class')==='residential')>{{ __('public_real_estate.property.classes.residential') }}</option>
                            <option value="commercial" @selected(old('property_class')==='commercial')>{{ __('public_real_estate.property.classes.commercial') }}</option>
                            <option value="office" @selected(old('property_class')==='office')>{{ __('public_real_estate.property.classes.office') }}</option>
                            <option value="land" @selected(old('property_class')==='land')>{{ __('public_real_estate.property.classes.land') }}</option>
                            <option value="industrial" @selected(old('property_class')==='industrial')>{{ __('public_real_estate.property.classes.industrial') }}</option>
                            <option value="agricultural" @selected(old('property_class')==='agricultural')>{{ __('public_real_estate.property.classes.agricultural') }}</option>
                            <option value="mixed" @selected(old('property_class')==='mixed')>{{ __('public_real_estate.property.classes.mixed') }}</option>
                            <option value="other" @selected(old('property_class')==='other')>{{ __('public_real_estate.property.classes.other') }}</option>
                        </select>
                    </label>
                    <label><span>{{ __('public_real_estate.property.subtype') }}</span>
                        <input name="property_subtype" value="{{ old('property_subtype') }}" placeholder="{{ __('public_real_estate.property.subtype_placeholder') }}">
                    </label>
                    <label style="grid-column:1/-1"><span>{{ __('public_real_estate.property.exact_address') }}</span>
                        <textarea name="exact_address" rows="2" placeholder="{{ __('public_real_estate.property.exact_address_placeholder') }}">{{ old('exact_address') }}</textarea>
                    </label>
                    <label style="grid-column:1/-1"><span>{{ __('public_real_estate.property.public_area') }}</span>
                        <input name="public_area" value="{{ old('public_area') }}" placeholder="{{ __('public_real_estate.property.public_area_placeholder') }}">
                    </label>
                </div>
            </section>

            <section class="section">
                <h2>{{ __('public_real_estate.sections.building') }}</h2>
                <div class="grid3">
                    <label><span>{{ __('public_real_estate.building.land_area') }}</span><input name="land_area" inputmode="decimal" value="{{ old('land_area') }}" placeholder="240"></label>
                    <label><span>{{ __('public_real_estate.building.construction_area') }}</span><input name="construction_area" inputmode="decimal" value="{{ old('construction_area') }}" placeholder="180"></label>
                    <label><span>{{ __('public_real_estate.building.bedrooms') }}</span><input name="bedrooms" inputmode="numeric" value="{{ old('bedrooms') }}" placeholder="3"></label>
                    <label><span>{{ __('public_real_estate.building.width') }}</span><input name="width" inputmode="decimal" value="{{ old('width') }}" placeholder="8"></label>
                    <label><span>{{ __('public_real_estate.building.length') }}</span><input name="length" inputmode="decimal" value="{{ old('length') }}" placeholder="30"></label>
                    <label><span>{{ __('public_real_estate.building.frontage_count') }}</span><input name="frontage_count" inputmode="numeric" value="{{ old('frontage_count') }}" placeholder="2"></label>
                </div>

                <div class="agebox">
                    <strong>{{ __('public_real_estate.building.age_heading') }}</strong>
                    <p class="hint">{{ __('public_real_estate.building.age_help') }}</p>
                    <div class="grid">
                        <label><span>{{ __('public_real_estate.building.built_year') }}</span>
                            <input name="built_year" inputmode="numeric" value="{{ old('built_year') }}" placeholder="{{ __('public_real_estate.building.built_year_placeholder') }}">
                            <input type="hidden" name="built_year_calendar" value="jalali">
                        </label>
                        <label><span>{{ __('public_real_estate.building.age_years') }}</span>
                            <input name="building_age_years" inputmode="numeric" value="{{ old('building_age_years') }}" placeholder="30">
                        </label>
                    </div>
                </div>

                <label style="display:block;margin-top:16px"><span>{{ __('public_real_estate.building.condition') }}</span>
                    <select name="building_condition">
                        <option value="">{{ __('public_real_estate.select') }}</option>
                        <option value="new" @selected(old('building_condition')==='new')>{{ __('public_real_estate.building.conditions.new') }}</option>
                        <option value="excellent" @selected(old('building_condition')==='excellent')>{{ __('public_real_estate.building.conditions.excellent') }}</option>
                        <option value="good" @selected(old('building_condition')==='good')>{{ __('public_real_estate.building.conditions.good') }}</option>
                        <option value="renovated" @selected(old('building_condition')==='renovated')>{{ __('public_real_estate.building.conditions.renovated') }}</option>
                        <option value="needs_renovation" @selected(old('building_condition')==='needs_renovation')>{{ __('public_real_estate.building.conditions.needs_renovation') }}</option>
                        <option value="old" @selected(old('building_condition')==='old')>{{ __('public_real_estate.building.conditions.old') }}</option>
                        <option value="teardown" @selected(old('building_condition')==='teardown')>{{ __('public_real_estate.building.conditions.teardown') }}</option>
                    </select>
                </label>
            </section>

            <details>
                <summary>{{ __('public_real_estate.sections.details') }}</summary>
                <div class="grid" style="margin-top:16px">
                    <label><span>{{ __('public_real_estate.details.cabinet_type') }}</span><input name="cabinet_type" value="{{ old('cabinet_type') }}" placeholder=""></label>
                    <label><span>{{ __('public_real_estate.details.false_ceiling') }}</span>
                        <select name="has_false_ceiling"><option value="">{{ __('public_real_estate.unknown') }}</option><option value="1">{{ __('public_real_estate.yes') }}</option><option value="0">{{ __('public_real_estate.no') }}</option></select>
                    </label>
                    <label><span>{{ __('public_real_estate.details.heating') }}</span><input name="heating_system" value="{{ old('heating_system') }}" placeholder=""></label>
                    <label><span>{{ __('public_real_estate.details.cooling') }}</span><input name="cooling_system" value="{{ old('cooling_system') }}" placeholder=""></label>
                    <label><span>{{ __('public_real_estate.details.flooring') }}</span><input name="flooring_type" value="{{ old('flooring_type') }}" placeholder=""></label>
                    <label><span>{{ __('public_real_estate.details.yard') }}</span><input name="yard_finish" value="{{ old('yard_finish') }}" placeholder=""></label>
                    <label><span>{{ __('public_real_estate.details.parking') }}</span><select name="has_parking"><option value="">{{ __('public_real_estate.unknown') }}</option><option value="1">{{ __('public_real_estate.yes') }}</option><option value="0">{{ __('public_real_estate.no') }}</option></select></label>
                    <label><span>{{ __('public_real_estate.details.parking_type') }}</span><input name="parking_type" value="{{ old('parking_type') }}" placeholder=""></label>
                    <label><span>{{ __('public_real_estate.details.parking_spaces') }}</span><input name="parking_spaces" inputmode="numeric" value="{{ old('parking_spaces') }}"></label>
                    <label><span>{{ __('public_real_estate.details.car_capacity') }}</span><input name="car_capacity" inputmode="numeric" value="{{ old('car_capacity') }}"></label>
                    <label><span>{{ __('public_real_estate.details.motorbike_capacity') }}</span><input name="motorbike_capacity" inputmode="numeric" value="{{ old('motorbike_capacity') }}"></label>
                    <label><span>{{ __('public_real_estate.details.parking_note') }}</span><input name="parking_note" value="{{ old('parking_note') }}"></label>
                    <label><span>{{ __('public_real_estate.details.roof') }}</span><input name="roof_finish" value="{{ old('roof_finish') }}" placeholder=""></label>
                    <label><span>{{ __('public_real_estate.details.parapet') }}</span><select name="roof_has_parapet"><option value="">{{ __('public_real_estate.unknown') }}</option><option value="1">{{ __('public_real_estate.yes') }}</option><option value="0">{{ __('public_real_estate.no') }}</option></select></label>
                    <label><span>{{ __('public_real_estate.details.western_toilet') }}</span><select name="has_western_toilet"><option value="">{{ __('public_real_estate.unknown') }}</option><option value="1">{{ __('public_real_estate.yes') }}</option><option value="0">{{ __('public_real_estate.no') }}</option></select></label>
                    <label><span>{{ __('public_real_estate.details.iranian_toilet') }}</span><select name="has_iranian_toilet"><option value="">{{ __('public_real_estate.unknown') }}</option><option value="1">{{ __('public_real_estate.yes') }}</option><option value="0">{{ __('public_real_estate.no') }}</option></select></label>
                </div>
            </details>

            <section class="section">
                <h2>{{ __('public_real_estate.sections.price') }}</h2>
                <input type="hidden" name="price_unit" value="toman">
                <div class="grid3">
                    <label><span>{{ __('public_real_estate.price.sale') }}</span><input name="asking_price" inputmode="numeric" value="{{ old('asking_price') }}" placeholder="9500000000"></label>
                    <label><span>{{ __('public_real_estate.price.deposit') }}</span><input name="deposit_amount" inputmode="numeric" value="{{ old('deposit_amount') }}"></label>
                    <label><span>{{ __('public_real_estate.price.monthly_rent') }}</span><input name="monthly_rent_amount" inputmode="numeric" value="{{ old('monthly_rent_amount') }}"></label>
                </div>
            </section>

            @include('public-intake.real-estate.partials.media-fields')

            <label style="display:block;margin-bottom:20px"><span>{{ __('public_real_estate.notes') }}</span>
                <textarea name="notes" rows="4" placeholder="{{ __('public_real_estate.notes_placeholder') }}">{{ old('notes') }}</textarea>
            </label>

            <button class="submit" type="submit">{{ __('public_real_estate.submit') }}</button>
            <p class="hint" style="text-align:center">{{ __('public_real_estate.submission_hint') }}</p>
        </form>
    </section>
</main>
</body>
</html>
