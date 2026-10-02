@extends('layouts.app')
@section('title', __('business_listing.editor_title').' — '.$business->name)

@section('content')
@php
    $p = $version?->propertyDetails;
    $isProperty = $listing->listing_type === 'property';
    $bool = fn ($value) => $value === null ? '' : ($value ? '1' : '0');
    $currentPrices = $listing->prices
        ->where('business_listing_version_id', $version?->id)
        ->values();
@endphp

<style>
.bl{max-width:1220px;margin:auto;padding:24px 16px 72px}.bl-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap}.bl-head h1{font-size:30px;margin:6px 0}.muted{color:#64748b;line-height:1.75}.panel{background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:20px;margin-top:16px}.steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:18px}.step{padding:10px;border:1px solid #cbd5e1;border-radius:14px;text-align:center;font-size:13px;font-weight:800;background:#f8fafc}.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.field span{display:block;font-size:13px;font-weight:800;margin-bottom:6px}.field input,.field select,.field textarea{width:100%;border:1.5px solid #aab4c4;border-radius:12px;padding:10px 12px;background:#fff}.privacy{background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:12px;color:#9a3412}.actions{display:flex;gap:8px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:0;border-radius:13px;padding:10px 14px;font-weight:850;cursor:pointer}.primary{background:#4f46e5;color:#fff}.green{background:#047857;color:#fff}.light{background:#fff;color:#334155;border:1px solid #cbd5e1}.danger{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}.badge{display:inline-flex;padding:5px 9px;border-radius:999px;background:#f1f5f9;color:#475569;font-size:12px;font-weight:800}.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}.media-card{border:1px solid #e2e8f0;border-radius:18px;overflow:hidden}.media-preview{height:170px;background:#0f172a;display:flex;align-items:center;justify-content:center}.media-preview img,.media-preview video{width:100%;height:100%;object-fit:cover}.media-body{padding:12px}.status{padding:12px 14px;background:#ecfdf5;color:#065f46;border-radius:14px;font-weight:800}.errors{padding:12px 14px;background:#fff1f2;color:#9f1239;border-radius:14px}.advanced summary{cursor:pointer;font-weight:900}.price-row{border-top:1px solid #e2e8f0;padding:9px 0}.footer-stick{position:sticky;bottom:10px;z-index:10;background:rgba(255,255,255,.96);border:1px solid #e2e8f0;border-radius:18px;padding:10px;box-shadow:0 10px 35px rgba(15,23,42,.12)}
@media(max-width:850px){.steps{grid-template-columns:repeat(2,1fr)}.grid2,.grid3{grid-template-columns:1fr}}
</style>

<main class="bl">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <header class="bl-head">
        <div>
            <a href="{{ route('businesses.catalog.index',$business) }}" style="text-decoration:none;color:#4f46e5;font-weight:850">← {{ __('business_listing.back_catalog') }}</a>
            <h1>{{ $version?->title ?? __('business_listing.editor_title') }}</h1>
            <div class="actions">
                <span class="badge">{{ __('business_listing.version',['version'=>$version?->version_number]) }}</span>
                <span class="badge">{{ $listing->listing_type }}</span>
                <span class="badge">{{ __('business_listing.availability.'.$listing->availability_status) }}</span>
                @if($version?->published_at)<span class="badge">✓ {{ __('business_listing.published') }}</span>@endif
            </div>
            <p class="muted">{{ __('business_listing.editor_help') }}</p>
        </div>
        <div class="actions">
            <a class="btn light" href="{{ route('businesses.catalog.listings.preview',[$business,$listing]) }}">{{ __('business_listing.preview') }}</a>
            @if($version?->presentationContent)
                <a class="btn light" href="{{ route('contexts.contents.studio',[$business->contextBinding->context,$version->presentationContent]) }}">{{ __('business_listing.advanced_content') }}</a>
            @endif
        </div>
    </header>

    <div class="steps">
        @foreach(__('business_listing.steps') as $label)<div class="step">{{ $label }}</div>@endforeach
    </div>

    <div class="panel muted">{{ __('business_listing.history_notice') }}</div>

    <form method="POST" action="{{ route('businesses.catalog.listings.update',[$business,$listing]) }}">
        @csrf
        @method('PUT')

        <section class="panel" id="client">
            <h2>{{ __('business_listing.client.title') }}</h2>
            <p class="muted">{{ __('business_listing.client.help') }}</p>
            <label class="field"><span>{{ __('business_listing.client.title') }}</span>
                <select name="business_contact_id">
                    <option value="">{{ __('business_listing.client.none') }}</option>
                    @foreach($business->businessContacts as $contact)
                        <option value="{{ $contact->id }}" @selected((string)old('business_contact_id',$listing->business_contact_id)===(string)$contact->id)>
                            {{ $contact->display_name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </section>

        <section class="panel" id="purpose">
            <h2>{{ __('business_listing.basics.title') }}</h2>
            <div class="grid2">
                <label class="field"><span>{{ __('business_listing.basics.listing_title') }}</span><input name="title" required maxlength="220" value="{{ old('title',$version?->title) }}"></label>
                <label class="field"><span>{{ __('business_listing.basics.category') }}</span>
                    <select name="business_category_id">
                        <option value="">—</option>
                        @foreach($business->categories as $category)
                            <option value="{{ $category->id }}" @selected((string)old('business_category_id',$listing->business_category_id)===(string)$category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field"><span>{{ __('business_listing.basics.visibility') }}</span>
                    <select name="visibility">
                        @foreach(['private','members','public'] as $visibility)<option value="{{ $visibility }}" @selected(old('visibility',$listing->visibility)===$visibility)>{{ $visibility }}</option>@endforeach
                    </select>
                </label>
                <label class="field"><span>{{ __('business_listing.basics.availability') }}</span>
                    <select name="availability_status">
                        @foreach($availabilityStatuses as $key=>$label)<option value="{{ $key }}" @selected(old('availability_status',$listing->availability_status)===$key)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="field"><span>{{ __('business_listing.basics.available_from') }}</span><input type="datetime-local" name="available_from" value="{{ old('available_from',$listing->available_from?->format('Y-m-d\TH:i')) }}"></label>
                <label class="field"><span>{{ __('business_listing.basics.available_until') }}</span><input type="datetime-local" name="available_until" value="{{ old('available_until',$listing->available_until?->format('Y-m-d\TH:i')) }}"></label>
            </div>
            <label class="field" style="display:block;margin-top:12px"><span>{{ __('business_listing.basics.short_description') }}</span><input name="short_description" maxlength="500" value="{{ old('short_description',$version?->short_description) }}"></label>
            <label class="field" style="display:block;margin-top:12px"><span>{{ __('business_listing.basics.description') }}</span><textarea name="description" rows="5">{{ old('description',$version?->description) }}</textarea></label>
            @if($isProperty)
                <label style="display:flex;gap:8px;align-items:center;margin-top:14px;font-weight:800">
                    <input type="hidden" name="simple_office_mode" value="0">
                    <input type="checkbox" name="simple_office_mode" value="1" @checked(old('simple_office_mode',$listing->simple_office_mode))>
                    {{ __('business_listing.basics.simple_mode') }}
                </label>
                <div class="muted">{{ __('business_listing.basics.simple_mode_help') }}</div>
            @endif
        </section>

        @if($isProperty)
            <section class="panel" id="property">
                <h2>{{ __('business_listing.property.title') }}</h2>
                <div class="privacy">{{ __('business_listing.property.privacy_help') }}</div>
                <div class="grid3" style="margin-top:14px">
                    <label class="field"><span>{{ __('business_listing.property.transaction_mode') }}</span>
                        <select name="transaction_mode">
                            <option value="">—</option>
                            @foreach(['sale','rent','sale_or_rent'] as $mode)<option value="{{ $mode }}" @selected(old('transaction_mode',$p?->transaction_mode)===$mode)>{{ __('business_listing.property.'.$mode) }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field"><span>{{ __('business_listing.property.property_class') }}</span>
                        <select name="property_class">
                            <option value="">—</option>
                            @foreach(['residential','commercial','office','land','industrial','agricultural','mixed','other'] as $class)<option value="{{ $class }}" @selected(old('property_class',$p?->property_class)===$class)>{{ $class }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field"><span>{{ __('business_listing.property.property_subtype') }}</span><input name="property_subtype" value="{{ old('property_subtype',$p?->property_subtype) }}"></label>
                    <label class="field" style="grid-column:span 2"><span>{{ __('business_listing.property.public_area') }}</span><input name="public_area" value="{{ old('public_area',$p?->public_area) }}"></label>
                    <label class="field" style="grid-column:1/-1"><span>{{ __('business_listing.property.exact_address') }}</span><textarea name="exact_address" rows="2">{{ old('exact_address',$p?->exact_address) }}</textarea></label>
                    @foreach([
                        'land_area'=>'land_area','construction_area'=>'construction_area','width'=>'width','length'=>'length',
                        'frontage_count'=>'frontage_count','built_year'=>'built_year','building_age_years'=>'building_age_years',
                        'bedrooms'=>'bedrooms','floor_number'=>'floor_number','total_floors'=>'total_floors',
                        'unit_number'=>'unit_number','units_per_floor'=>'units_per_floor',
                    ] as $name=>$key)
                        <label class="field"><span>{{ __('business_listing.property.'.$key) }}</span><input name="{{ $name }}" inputmode="decimal" value="{{ old($name,$p?->{$name}) }}"></label>
                    @endforeach
                    <label class="field"><span>{{ __('business_listing.property.built_year_calendar') }}</span>
                        <select name="built_year_calendar"><option value="">—</option><option value="jalali" @selected(old('built_year_calendar',$p?->built_year_calendar)==='jalali')>Jalali</option><option value="gregorian" @selected(old('built_year_calendar',$p?->built_year_calendar)==='gregorian')>Gregorian</option></select>
                    </label>
                    <label class="field"><span>{{ __('business_listing.property.building_condition') }}</span>
                        <select name="building_condition"><option value="">—</option>@foreach(['new','excellent','good','renovated','needs_renovation','old','teardown'] as $v)<option value="{{ $v }}" @selected(old('building_condition',$p?->building_condition)===$v)>{{ $v }}</option>@endforeach</select>
                    </label>
                    @foreach(['orientation','deed_type','usage_type','occupancy_status'] as $name)
                        <label class="field"><span>{{ __('business_listing.property.'.$name) }}</span><input name="{{ $name }}" value="{{ old($name,$p?->{$name}) }}"></label>
                    @endforeach
                    <label class="field"><span>{{ __('business_listing.property.latitude') }}</span><input name="latitude" inputmode="decimal" value="{{ old('latitude',$p?->latitude) }}"></label>
                    <label class="field"><span>{{ __('business_listing.property.longitude') }}</span><input name="longitude" inputmode="decimal" value="{{ old('longitude',$p?->longitude) }}"></label>
                    <label class="field" style="grid-column:1/-1"><span>{{ __('business_listing.property.public_notes') }}</span><textarea name="public_notes" rows="3">{{ old('public_notes',$p?->public_notes) }}</textarea></label>
                    <label class="field" style="grid-column:1/-1"><span>{{ __('business_listing.property.private_notes') }}</span><textarea name="private_notes" rows="3">{{ old('private_notes',$p?->private_notes) }}</textarea></label>
                </div>
            </section>

            <section class="panel" id="facilities">
                <h2>{{ __('business_listing.facilities.title') }}</h2>
                <details class="advanced" @unless($listing->simple_office_mode) open @endunless>
                    <summary>{{ __('business_listing.facilities.advanced') }}</summary>
                    <div class="grid3" style="margin-top:14px">
                        @foreach([
                            'cabinet_type'=>'cabinet_type','heating_system'=>'heating','cooling_system'=>'cooling',
                            'yard_finish'=>'yard','flooring_type'=>'flooring','parking_type'=>'parking_type',
                            'parking_spaces'=>'parking_spaces','car_capacity'=>'car_capacity','motorbike_capacity'=>'motorbike_capacity',
                            'roof_finish'=>'roof_finish','storage_area'=>'storage_area','balcony_area'=>'balcony_area',
                        ] as $name=>$label)
                            <label class="field"><span>{{ __('business_listing.facilities.'.$label) }}</span><input name="{{ $name }}" value="{{ old($name,$p?->{$name}) }}"></label>
                        @endforeach
                        @foreach([
                            'has_false_ceiling'=>'false_ceiling','has_parking'=>'parking','roof_has_parapet'=>'roof_parapet',
                            'has_western_toilet'=>'western_toilet','has_iranian_toilet'=>'iranian_toilet',
                            'has_elevator'=>'elevator','has_storage'=>'storage','has_balcony'=>'balcony',
                        ] as $name=>$label)
                            <label class="field"><span>{{ __('business_listing.facilities.'.$label) }}</span>
                                <select name="{{ $name }}">
                                    <option value="">{{ __('business_listing.facilities.unknown') }}</option>
                                    <option value="1" @selected((string)old($name,$bool($p?->{$name}))==='1')>{{ __('business_listing.facilities.yes') }}</option>
                                    <option value="0" @selected((string)old($name,$bool($p?->{$name}))==='0')>{{ __('business_listing.facilities.no') }}</option>
                                </select>
                            </label>
                        @endforeach
                        <label class="field" style="grid-column:1/-1"><span>{{ __('business_listing.facilities.utilities') }}</span><input name="utilities_text" value="{{ old('utilities_text',implode(', ', $p?->utilities ?? [])) }}"></label>
                        <label class="field" style="grid-column:1/-1"><span>{{ __('business_listing.facilities.facilities') }}</span><input name="facilities_text" value="{{ old('facilities_text',implode(', ', $p?->facilities ?? [])) }}"></label>
                    </div>
                </details>
            </section>
        @endif

        <div class="footer-stick actions">
            <button class="btn primary" type="submit">{{ __('business_listing.save') }}</button>
            <a class="btn light" href="{{ route('businesses.catalog.listings.preview',[$business,$listing]) }}">{{ __('business_listing.preview') }}</a>
        </div>
    </form>

    <section class="panel" id="price">
        <h2>{{ __('business_listing.price.title') }}</h2>
        <p class="muted">{{ __('business_listing.price.help') }}</p>
        @foreach($currentPrices as $price)
            <div class="price-row">
                <strong>{{ $price->price_type }}</strong> ·
                {{ AppSupportMoneyAmount::format((int)$price->amount_minor,(int)$price->monetaryUnit->exponent) }} {{ $price->monetaryUnit->code }}
                @if($price->basis) / {{ $price->basis }} @endif
                <span class="badge">{{ $price->visibility }}</span>
            </div>
        @endforeach
        @if(!$version?->published_at)
            <form method="POST" action="{{ route('businesses.catalog.listings.prices.store',[$business,$listing]) }}" class="grid3" style="margin-top:12px">
                @csrf
                <label class="field"><span>{{ __('business_listing.price.type') }}</span>
                    <select name="price_type">
                        @foreach(['asking_sale','deposit','monthly_rent','retail','service','hourly','daily','monthly','wholesale','cost'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
                    </select>
                </label>
                <label class="field"><span>{{ __('business_listing.price.unit') }}</span>
                    <select name="unit_code">@foreach($unitCatalog as $code=>$meta)<option value="{{ $code }}" @selected($code===($business->defaultMonetaryUnit?->code ?? 'IET'))>{{ $code }} — {{ $meta['name'] }}</option>@endforeach</select>
                </label>
                <label class="field"><span>{{ __('business_listing.price.amount') }}</span><input name="amount" inputmode="decimal" required></label>
                <label class="field"><span>{{ __('business_listing.price.basis') }}</span><input name="basis"></label>
                <label class="field"><span>{{ __('business_listing.price.visibility') }}</span><select name="visibility"><option value="public">public</option><option value="members">members</option><option value="private">private</option></select></label>
                <label class="field"><span>{{ __('business_listing.price.reason') }}</span><input name="reason"></label>
                <div><button class="btn light">{{ __('business_listing.price.add') }}</button></div>
            </form>
        @endif
    </section>

    <section class="panel" id="media">
        <h2>{{ __('business_listing.media.title') }}</h2>
        <p class="muted">{{ __('business_listing.media.help') }}</p>

        @if(!$version?->published_at)
            <form method="POST" enctype="multipart/form-data" action="{{ route('businesses.catalog.listings.media.store',[$business,$listing]) }}" class="grid3">
                @csrf
                <label class="field"><span>{{ __('business_listing.media.file') }}</span><input type="file" name="media" accept="image/*,video/*,audio/*" required></label>
                <label class="field"><span>{{ __('business_listing.media.rights') }}</span><select name="rights_status">@foreach(AppModelsAsset::RIGHTS_STATUSES as $status)<option value="{{ $status }}" @selected($status==='owned')>{{ $status }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('business_listing.media.visibility') }}</span><select name="visibility"><option value="public">public</option><option value="members">members</option><option value="private">private</option></select></label>
                <label class="field" style="grid-column:span 2"><span>{{ __('business_listing.media.caption') }}</span><input name="caption"></label>
                <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="cover" value="1"> {{ __('business_listing.media.cover') }}</label>
                <div><button class="btn light">{{ __('business_listing.media.upload') }}</button></div>
            </form>
        @endif

        <div class="media-grid" style="margin-top:14px">
            @forelse($version?->media ?? [] as $media)
                <article class="media-card">
                    <div class="media-preview">
                        @if(str_starts_with($media->asset->mime_type,'image/'))
                            <img src="{{ route('businesses.catalog.listings.media.show',[$business,$listing,$media]) }}" alt="{{ $media->caption ?: $media->asset->alt_text }}">
                        @elseif(str_starts_with($media->asset->mime_type,'video/'))
                            <video controls preload="metadata" src="{{ route('businesses.catalog.listings.media.show',[$business,$listing,$media]) }}"></video>
                        @elseif(str_starts_with($media->asset->mime_type,'audio/'))
                            <audio controls src="{{ route('businesses.catalog.listings.media.show',[$business,$listing,$media]) }}"></audio>
                        @else
                            <span style="color:white">{{ $media->asset->original_filename }}</span>
                        @endif
                    </div>
                    <div class="media-body">
                        <div class="actions"><span class="badge">{{ $media->role }}</span><span class="badge">{{ $media->visibility }}</span><span class="badge">#{{ $media->position }}</span></div>
                        @if(!$version?->published_at)
                            <form method="POST" action="{{ route('businesses.catalog.listings.media.update',[$business,$listing,$media]) }}" style="margin-top:10px">
                                @csrf @method('PUT')
                                <div class="grid2">
                                    <label class="field"><span>{{ __('business_listing.media.position') }}</span><input name="position" type="number" min="0" value="{{ $media->position }}"></label>
                                    <label class="field"><span>{{ __('business_listing.media.visibility') }}</span><select name="visibility">@foreach(['public','members','private'] as $v)<option value="{{ $v }}" @selected($media->visibility===$v)>{{ $v }}</option>@endforeach</select></label>
                                </div>
                                <label class="field" style="display:block;margin-top:8px"><span>{{ __('business_listing.media.caption') }}</span><input name="caption" value="{{ $media->caption }}"></label>
                                <label style="display:flex;gap:8px;margin-top:8px"><input type="checkbox" name="cover" value="1" @checked($media->role==='cover')> {{ __('business_listing.media.cover') }}</label>
                                <button class="btn light" style="margin-top:8px">{{ __('business_listing.media.update') }}</button>
                            </form>
                            <form method="POST" action="{{ route('businesses.catalog.listings.media.destroy',[$business,$listing,$media]) }}" style="margin-top:8px">@csrf @method('DELETE')<button class="btn danger">{{ __('business_listing.media.remove') }}</button></form>
                        @else
                            @if($media->caption)<p class="muted">{{ $media->caption }}</p>@endif
                        @endif
                    </div>
                </article>
            @empty
                <p class="muted">{{ __('business_listing.media.empty') }}</p>
            @endforelse
        </div>
    </section>

    <section class="panel" id="preview">
        <h2>{{ __('business_listing.steps.preview') }} / {{ __('business_listing.steps.publish') }}</h2>
        <div class="actions">
            <a class="btn light" href="{{ route('businesses.catalog.listings.preview',[$business,$listing]) }}">{{ __('business_listing.preview') }}</a>
            @if(!$version?->published_at)
                <form method="POST" action="{{ route('businesses.catalog.listings.presentation.sync',[$business,$listing]) }}">@csrf<button class="btn light">{{ __('business_listing.sync_content') }}</button></form>
                <form method="POST" action="{{ route('businesses.catalog.listings.publish',[$business,$listing]) }}">@csrf<button class="btn green">{{ __('business_listing.publish') }}</button></form>
            @endif
            @if($version?->presentationContent)
                <a class="btn light" href="{{ route('contexts.contents.studio',[$business->contextBinding->context,$version->presentationContent]) }}">{{ __('business_listing.advanced_content') }}</a>
            @endif
        </div>
    </section>
</main>
@endsection
