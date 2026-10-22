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
    $published = (bool) $version?->published_at;
    $visibilities = ['private' => 'private', 'members' => 'members', 'public' => 'public'];
    $item = 'min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50';
@endphp
<div class="mx-auto max-w-5xl space-y-6" data-listing-editor>
    @if (session('status'))<flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>@endif
    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle"><ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></flux:callout>
    @endif

    <div>
        <flux:button :href="route('businesses.catalog.index', $business)" variant="ghost" size="sm" icon="arrow-left" class="rtl:[&_svg]:rotate-180">{{ __('business_listing.back_catalog') }}</flux:button>
    </div>
    <x-app.page-header :title="$version?->title ?? __('business_listing.editor_title')" :description="__('business_listing.editor_help')">
        <x-slot:actions>
            <flux:button :href="route('businesses.catalog.listings.preview', [$business, $listing])" variant="ghost">{{ __('business_listing.preview') }}</flux:button>
            @if ($version?->presentationContent)
                <flux:button :href="route('contexts.contents.studio', [$business->contextBinding->context, $version->presentationContent])" variant="ghost">{{ __('business_listing.advanced_content') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-app.page-header>
    <div class="-mt-3 flex flex-wrap gap-1">
        <flux:badge size="sm">{{ __('business_listing.version', ['version' => $version?->version_number]) }}</flux:badge>
        <flux:badge size="sm" color="zinc">{{ $listing->listing_type }}</flux:badge>
        <flux:badge size="sm" color="zinc">{{ __('business_listing.availability.'.$listing->availability_status) }}</flux:badge>
        @if ($published)<flux:badge size="sm" color="green">✓ {{ __('business_listing.published') }}</flux:badge>@endif
    </div>

    <ol class="flex flex-wrap gap-2 text-sm" aria-label="{{ __('business_listing.editor_title') }}">
        @foreach (__('business_listing.steps') as $label)
            <li class="rounded-full border border-zinc-200 px-3 py-1 dark:border-zinc-700">{{ $label }}</li>
        @endforeach
    </ol>

    <flux:callout icon="information-circle">{{ __('business_listing.history_notice') }}</flux:callout>

    <form method="POST" action="{{ route('businesses.catalog.listings.update', [$business, $listing]) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-app.panel id="client" :title="__('business_listing.client.title')" :help="__('business_listing.client.help')" icon="user">
            <x-form.select remember name="business_contact_id" :label="__('business_listing.client.title')" :placeholder="__('business_listing.client.none')" :selected="$listing->business_contact_id"
                :options="$business->businessContacts->mapWithKeys(fn ($contact) => [$contact->id => $contact->display_name])->all()" />
        </x-app.panel>

        <x-app.panel id="purpose" :title="__('business_listing.basics.title')" icon="document-text">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input remember name="title" required maxlength="220" :value="$version?->title" :label="__('business_listing.basics.listing_title')" />
                <x-form.select remember name="business_category_id" placeholder="—" :selected="$listing->business_category_id" :label="__('business_listing.basics.category')"
                    :options="$business->categories->mapWithKeys(fn ($category) => [$category->id => $category->name])->all()" />
                <x-form.select remember name="visibility" :options="$visibilities" :selected="$listing->visibility" :label="__('business_listing.basics.visibility')" />
                <x-form.select remember name="availability_status" :options="$availabilityStatuses" :selected="$listing->availability_status" :label="__('business_listing.basics.availability')" />
            </div>
            <x-form.input remember name="short_description" maxlength="500" :value="$version?->short_description" :label="__('business_listing.basics.short_description')" />
            <x-form.textarea remember name="description" rows="5" :value="$version?->description" :label="__('business_listing.basics.description')" />
            @if ($isProperty)
                <input type="hidden" name="simple_office_mode" value="0">
                <x-form.checkbox name="simple_office_mode" :checked="(bool) old('simple_office_mode', $listing->simple_office_mode)" :label="__('business_listing.basics.simple_mode')" />
                <flux:text class="-mt-2 text-sm">{{ __('business_listing.basics.simple_mode_help') }}</flux:text>
            @endif
        </x-app.panel>

        @if ($isProperty)
            <x-app.panel id="property" :title="__('business_listing.property.title')" icon="home-modern">
                <flux:callout variant="warning" icon="lock-closed">{{ __('business_listing.property.privacy_help') }}</flux:callout>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-form.select remember name="transaction_mode" placeholder="—" :selected="$p?->transaction_mode" :label="__('business_listing.property.transaction_mode')"
                        :options="collect(['sale', 'rent', 'sale_or_rent'])->mapWithKeys(fn ($mode) => [$mode => __('business_listing.property.'.$mode)])->all()" />
                    <x-form.select remember name="property_class" placeholder="—" :selected="$p?->property_class" :label="__('business_listing.property.property_class')"
                        :options="collect(['residential', 'commercial', 'office', 'land', 'industrial', 'agricultural', 'mixed', 'other'])->mapWithKeys(fn ($class) => [$class => __('business_listing.property.classes.'.$class)])->all()" />
                    <x-form.input remember name="property_subtype" :value="$p?->property_subtype" :label="__('business_listing.property.property_subtype')" />
                    <div class="sm:col-span-2 lg:col-span-3"><x-form.input remember name="public_area" :value="$p?->public_area" :label="__('business_listing.property.public_area')" /></div>
                    <div class="sm:col-span-2 lg:col-span-3"><x-form.textarea remember name="exact_address" rows="2" :value="$p?->exact_address" :label="__('business_listing.property.exact_address')" /></div>
                    @foreach (['land_area', 'construction_area', 'width', 'length', 'frontage_count', 'built_year', 'building_age_years', 'bedrooms', 'floor_number', 'total_floors', 'unit_number', 'units_per_floor'] as $name)
                        <x-form.input remember :name="$name" inputmode="decimal" :value="$p?->{$name}" :label="__('business_listing.property.'.$name)" />
                    @endforeach
                    <x-form.select remember name="built_year_calendar" placeholder="—" :selected="$p?->built_year_calendar" :label="__('business_listing.property.built_year_calendar')"
                        :options="['jalali' => __('business_listing.property.calendars.jalali'), 'gregorian' => __('business_listing.property.calendars.gregorian')]" />
                    <x-form.select remember name="building_condition" placeholder="—" :selected="$p?->building_condition" :label="__('business_listing.property.building_condition')"
                        :options="collect(['new', 'excellent', 'good', 'renovated', 'needs_renovation', 'old', 'teardown'])->mapWithKeys(fn ($v) => [$v => __('business_listing.property.conditions.'.$v)])->all()" />
                    @foreach (['orientation', 'deed_type', 'usage_type', 'occupancy_status'] as $name)
                        <x-form.input remember :name="$name" :value="$p?->{$name}" :label="__('business_listing.property.'.$name)" />
                    @endforeach
                    <x-form.input remember name="latitude" inputmode="decimal" dir="ltr" :value="$p?->latitude" :label="__('business_listing.property.latitude')" />
                    <x-form.input remember name="longitude" inputmode="decimal" dir="ltr" :value="$p?->longitude" :label="__('business_listing.property.longitude')" />
                    <div class="sm:col-span-2 lg:col-span-3"><x-form.textarea remember name="public_notes" rows="3" :value="$p?->public_notes" :label="__('business_listing.property.public_notes')" /></div>
                    <div class="sm:col-span-2 lg:col-span-3"><x-form.textarea remember name="private_notes" rows="3" :value="$p?->private_notes" :label="__('business_listing.property.private_notes')" /></div>
                </div>
            </x-app.panel>

            <x-app.panel id="facilities" :title="__('business_listing.facilities.title')" icon="wrench-screwdriver">
                <details @unless ($listing->simple_office_mode) open @endunless>
                    <summary class="cursor-pointer py-1 font-medium">{{ __('business_listing.facilities.advanced') }}</summary>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach (['cabinet_type' => 'cabinet_type', 'heating_system' => 'heating', 'cooling_system' => 'cooling', 'yard_finish' => 'yard', 'flooring_type' => 'flooring', 'parking_type' => 'parking_type', 'parking_spaces' => 'parking_spaces', 'car_capacity' => 'car_capacity', 'motorbike_capacity' => 'motorbike_capacity', 'roof_finish' => 'roof_finish', 'storage_area' => 'storage_area', 'balcony_area' => 'balcony_area'] as $name => $label)
                            <x-form.input remember :name="$name" :value="$p?->{$name}" :label="__('business_listing.facilities.'.$label)" />
                        @endforeach
                        @foreach (['has_false_ceiling' => 'false_ceiling', 'has_parking' => 'parking', 'roof_has_parapet' => 'roof_parapet', 'has_western_toilet' => 'western_toilet', 'has_iranian_toilet' => 'iranian_toilet', 'has_elevator' => 'elevator', 'has_storage' => 'storage', 'has_balcony' => 'balcony'] as $name => $label)
                            <x-form.select remember :name="$name" :placeholder="__('business_listing.facilities.unknown')" :selected="$bool($p?->{$name})" :label="__('business_listing.facilities.'.$label)"
                                :options="['1' => __('business_listing.facilities.yes'), '0' => __('business_listing.facilities.no')]" />
                        @endforeach
                        <div class="sm:col-span-2 lg:col-span-3"><x-form.input remember name="utilities_text" :value="implode(', ', $p?->utilities ?? [])" :label="__('business_listing.facilities.utilities')" /></div>
                        <div class="sm:col-span-2 lg:col-span-3"><x-form.input remember name="facilities_text" :value="implode(', ', $p?->facilities ?? [])" :label="__('business_listing.facilities.facilities')" /></div>
                    </div>
                </details>
            </x-app.panel>
        @endif

        <div class="sticky bottom-3 z-10 flex flex-wrap gap-2 rounded-xl border border-zinc-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
            <flux:button type="submit" variant="primary">{{ __('business_listing.save') }}</flux:button>
            <flux:button :href="route('businesses.catalog.listings.preview', [$business, $listing])" variant="ghost">{{ __('business_listing.preview') }}</flux:button>
        </div>
    </form>

    <x-app.panel id="price" :title="__('business_listing.price.title')" :help="__('business_listing.price.help')" icon="banknotes">
        @foreach ($currentPrices as $price)
            <div class="flex flex-wrap items-center gap-2 {{ $item }}">
                <strong>{{ $price->price_type }}</strong> ·
                <span>{{ \App\Support\MoneyAmount::format((int) $price->amount_minor, (int) $price->monetaryUnit->exponent) }} {{ $price->monetaryUnit->code }}</span>
                @if ($price->basis)<span>/ {{ $price->basis }}</span>@endif
                <flux:badge size="sm" color="zinc">{{ $price->visibility }}</flux:badge>
            </div>
        @endforeach
        @unless ($published)
            <form method="POST" action="{{ route('businesses.catalog.listings.prices.store', [$business, $listing]) }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-form.select name="price_type" :label="__('business_listing.price.type')" :options="collect(['asking_sale', 'deposit', 'monthly_rent', 'retail', 'service', 'hourly', 'daily', 'monthly', 'wholesale', 'cost'])->mapWithKeys(fn ($type) => [$type => $type])->all()" />
                    <x-form.select name="unit_code" :label="__('business_listing.price.unit')" :selected="$business->defaultMonetaryUnit?->code ?? 'IET'"
                        :options="collect($unitCatalog)->mapWithKeys(fn ($meta, $code) => [$code => $code.' — '.$meta['name']])->all()" />
                    <x-form.input name="amount" inputmode="decimal" required :label="__('business_listing.price.amount')" />
                    <x-form.input name="basis" :label="__('business_listing.price.basis')" />
                    <x-form.select name="visibility" :options="['public' => 'public', 'members' => 'members', 'private' => 'private']" :label="__('business_listing.price.visibility')" />
                    <x-form.input name="reason" :label="__('business_listing.price.reason')" />
                </div>
                <flux:button type="submit" icon="plus">{{ __('business_listing.price.add') }}</flux:button>
            </form>
        @endunless
    </x-app.panel>

    <x-app.panel id="media" :title="__('business_listing.media.title')" :help="__('business_listing.media.help')" icon="photo">
        @unless ($published)
            <form method="POST" enctype="multipart/form-data" action="{{ route('businesses.catalog.listings.media.store', [$business, $listing]) }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-form.input type="file" name="media" accept="image/*,video/*,audio/*" required :label="__('business_listing.media.file')" />
                    <x-form.select name="rights_status" selected="owned" :options="array_combine($rightsStatuses, $rightsStatuses)" :label="__('business_listing.media.rights')" />
                    <x-form.select name="visibility" :options="['public' => 'public', 'members' => 'members', 'private' => 'private']" :label="__('business_listing.media.visibility')" />
                </div>
                <x-form.input name="caption" :label="__('business_listing.media.caption')" />
                <x-form.checkbox name="cover" :label="__('business_listing.media.cover')" />
                <flux:button type="submit" icon="arrow-up-tray">{{ __('business_listing.media.upload') }}</flux:button>
            </form>
        @endunless

        <div class="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(min(100%,16rem),1fr))]">
            @forelse ($version?->media ?? [] as $media)
                <article class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700" wire:key="media-{{ $media->id }}">
                    <div class="flex aspect-[4/3] items-center justify-center bg-zinc-900">
                        @php($src = route('businesses.catalog.listings.media.show', [$business, $listing, $media]))
                        @if (str_starts_with($media->asset->mime_type, 'image/'))
                            <img src="{{ $src }}" alt="{{ $media->caption ?: $media->asset->alt_text }}" loading="lazy" decoding="async" class="h-full w-full object-cover">
                        @elseif (str_starts_with($media->asset->mime_type, 'video/'))
                            <video controls preload="metadata" playsinline src="{{ $src }}" class="h-full w-full object-contain"></video>
                        @elseif (str_starts_with($media->asset->mime_type, 'audio/'))
                            <audio controls src="{{ $src }}" class="w-full px-2"></audio>
                        @else
                            <span class="px-3 text-white [overflow-wrap:anywhere]">{{ $media->asset->original_filename }}</span>
                        @endif
                    </div>
                    <div class="space-y-3 p-3">
                        <div class="flex flex-wrap gap-1"><flux:badge size="sm">{{ $media->role }}</flux:badge><flux:badge size="sm" color="zinc">{{ $media->visibility }}</flux:badge><flux:badge size="sm" color="zinc">#{{ $media->position }}</flux:badge></div>
                        @unless ($published)
                            <form method="POST" action="{{ route('businesses.catalog.listings.media.update', [$business, $listing, $media]) }}" class="space-y-3">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-2 gap-3">
                                    <x-form.input name="position" type="number" min="0" :value="$media->position" :label="__('business_listing.media.position')" />
                                    <x-form.select name="visibility" :options="['public' => 'public', 'members' => 'members', 'private' => 'private']" :selected="$media->visibility" :label="__('business_listing.media.visibility')" />
                                </div>
                                <x-form.input name="caption" :value="$media->caption" :label="__('business_listing.media.caption')" />
                                <x-form.checkbox name="cover" :checked="$media->role === 'cover'" :label="__('business_listing.media.cover')" />
                                <flux:button type="submit" size="sm">{{ __('business_listing.media.update') }}</flux:button>
                            </form>
                            <form method="POST" action="{{ route('businesses.catalog.listings.media.destroy', [$business, $listing, $media]) }}">@csrf @method('DELETE')<flux:button type="submit" size="sm" variant="danger">{{ __('business_listing.media.remove') }}</flux:button></form>
                        @else
                            @if ($media->caption)<flux:text class="text-sm">{{ $media->caption }}</flux:text>@endif
                        @endunless
                    </div>
                </article>
            @empty
                <flux:text>{{ __('business_listing.media.empty') }}</flux:text>
            @endforelse
        </div>
    </x-app.panel>

    <x-app.panel id="preview" :title="__('business_listing.steps.preview').' / '.__('business_listing.steps.publish')" icon="eye">
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('businesses.catalog.listings.preview', [$business, $listing])" variant="ghost">{{ __('business_listing.preview') }}</flux:button>
            @unless ($published)
                <form method="POST" action="{{ route('businesses.catalog.listings.presentation.sync', [$business, $listing]) }}">@csrf<flux:button type="submit">{{ __('business_listing.sync_content') }}</flux:button></form>
                <form method="POST" action="{{ route('businesses.catalog.listings.publish', [$business, $listing]) }}">@csrf<flux:button type="submit" variant="primary">{{ __('business_listing.publish') }}</flux:button></form>
            @endunless
            @if ($version?->presentationContent)
                <flux:button :href="route('contexts.contents.studio', [$business->contextBinding->context, $version->presentationContent])" variant="ghost">{{ __('business_listing.advanced_content') }}</flux:button>
            @endif
        </div>
    </x-app.panel>
</div>
@endsection
