@extends('layouts.app')
@section('title', __('business.create.title'))

@section('content')
<style>
*{box-sizing:border-box}body{margin:0;background:linear-gradient(180deg,#eef2ff,#f8fafc 280px);color:#172033;font-family:inherit}
.wrap{max-width:900px;margin:auto;padding:28px 16px 60px}
.hero{background:linear-gradient(125deg,#7c3aed,#db2777);color:#fff;border-radius:30px;padding:28px;box-shadow:0 20px 60px rgba(124,58,237,.2)}
.hero h1{font-size:32px;margin:5px 0 8px}.hero p{font-size:17px;line-height:2;margin:0}
.panel{background:#fff;border:1px solid #e4e7ec;border-radius:28px;padding:24px;margin-top:18px;box-shadow:0 8px 25px rgba(15,23,42,.05)}
.step{display:flex;gap:13px;align-items:center;margin-bottom:18px}.bubble{width:48px;height:48px;display:grid;place-items:center;border-radius:15px;background:#ede9fe;font-size:23px}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
label span{display:block;font-size:14px;font-weight:900;margin:0 0 7px;color:#344054}
input,select,textarea{width:100%;border:1.5px solid #9aa6b6!important;border-radius:15px!important;background:#fff!important;padding:13px 14px!important;font-size:16px!important}
input:focus,select:focus,textarea:focus{outline:4px solid #e0e7ff!important;border-color:#6366f1!important}
.btn{border:0;border-radius:16px;padding:14px 20px;background:#4f46e5;color:#fff;font-size:17px;font-weight:950;cursor:pointer}
.errors{background:#fff1f2;border:1px solid #fecdd3;border-radius:16px;padding:15px;color:#9f1239;margin-top:16px}
@media(max-width:700px){.grid{grid-template-columns:1fr}.hero h1{font-size:27px}}
</style>

<main class="wrap">
    <header class="hero">
        <div style="font-weight:900;opacity:.9">{{ __('business.create.kicker') }}</div>
        <h1>{{ __('business.create.heading') }}</h1>
        <p>{{ __('business.create.intro') }}</p>
    </header>

    @if($errors->any())
        <div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('businesses.store') }}">
        @csrf
        <section class="panel">
            <div class="step">
                <div class="bubble">🏪</div>
                <div>
                    <h2 style="margin:0">{{ __('business.create.section') }}</h2>
                    <div style="color:#667085;margin-top:4px">{{ __('business.create.section_help') }}</div>
                </div>
            </div>

            <div class="grid">
                <label><span>{{ __('business.create.name') }} *</span><input name="name" value="{{ old('name') }}" required placeholder="{{ __('business.create.name_placeholder') }}"></label>
                <label><span>{{ __('business.create.type') }} *</span>
                    <select name="kind" required>
                        @foreach($kindLabels as $value=>$label)
                            <option value="{{ $value }}" @selected(old('kind')===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label><span>{{ __('business.create.legal_name') }}</span><input name="legal_name" value="{{ old('legal_name') }}" placeholder="{{ __('business.create.legal_placeholder') }}"></label>
                <label><span>{{ __('business.create.founded_year') }}</span><input type="number" name="founded_year" value="{{ old('founded_year') }}" placeholder="{{ __('business.create.year_placeholder') }}"></label>
            </div>

            <label style="display:block;margin-top:14px"><span>{{ __('business.create.short_intro') }}</span><input name="short_intro" value="{{ old('short_intro') }}" maxlength="300" placeholder="{{ __('business.create.intro_placeholder') }}"></label>
            <label style="display:block;margin-top:14px"><span>{{ __('business.create.description') }}</span><textarea name="description" rows="5" placeholder="{{ __('business.create.description_placeholder') }}">{{ old('description') }}</textarea></label>

            <label style="display:block;margin-top:14px"><span>{{ __('business.create.visibility') }}</span>
                <select name="visibility">
                    @foreach($visibilityLabels ?? AppSupportBusinessDirectory::visibilityLabels() as $value=>$label)
                        <option value="{{ $value }}" @selected(old('visibility', 'private') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <input type="hidden" name="status" value="active">

            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn">{{ __('business.create.submit') }} ←</button>
                <a href="{{ route('businesses.index') }}" style="display:inline-flex;align-items:center;text-decoration:none;border:1px solid #d0d5dd;border-radius:16px;padding:14px 20px;font-weight:900;color:#475467;background:#fff">{{ __('business.create.cancel') }}</a>
            </div>
        </section>
    </form>
</main>
@endsection
