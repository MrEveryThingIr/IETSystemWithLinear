@extends('layouts.app')
@section('title', __('contact_center.title'))

@section('content')
@php
    $kindLabels = collect(['email', 'mobile', 'phone', 'website', 'whatsapp', 'telegram', 'other'])->mapWithKeys(fn ($k) => [$k => __('contact_center.kinds.'.$k)])->all();
    $kindIcons = ['email' => 'envelope', 'mobile' => 'device-phone-mobile', 'phone' => 'phone', 'website' => 'globe-alt', 'whatsapp' => 'chat-bubble-left-right', 'telegram' => 'paper-airplane', 'other' => 'link'];
    $visibilityLabels = collect(['private', 'contacts', 'members', 'public'])->mapWithKeys(fn ($v) => [$v => __('contact_center.visibility.'.$v)])->all();
    $addressTypes = collect(['residence', 'work', 'branch', 'billing', 'shipping', 'project_site', 'other'])->mapWithKeys(fn ($t) => [$t => __('contact_center.types.'.$t)])->all();
    $item = 'min-w-0 space-y-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50';
    $subform = 'space-y-4 rounded-lg border border-dashed border-zinc-300 p-4 dark:border-zinc-700';
@endphp
<div class="mx-auto max-w-5xl space-y-6" data-contact-center>
    <x-app.page-header :title="__('contact_center.title')" :description="__('contact_center.intro')" />
    <div class="-mt-3 flex flex-wrap items-center gap-2 text-sm">
        <flux:icon.lock-closed class="size-4 text-zinc-500" />
        <span class="text-zinc-500">{{ __('contact_center.login_email') }}:</span>
        <strong dir="ltr" class="[overflow-wrap:anywhere]">{{ $user->email }}</strong>
        <flux:badge size="sm" :color="$user->email_verified_at ? 'green' : 'amber'">{{ $user->email_verified_at ? __('contact_center.verified') : __('contact_center.unverified') }}</flux:badge>
    </div>

    @if (session('status'))<flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>@endif
    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle">
            <strong>{{ __('contact_center.fix') }}</strong>
            <ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </flux:callout>
    @endif

    <section class="grid gap-3 [grid-template-columns:repeat(auto-fill,minmax(min(100%,10rem),1fr))]">
        @foreach ([['contacts', $contactPoints->count()], ['addresses', $addresses->count()], ['public', $contactPoints->where('visibility', 'public')->count() + $addresses->where('visibility', 'public')->count()]] as [$key, $count])
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"><span class="text-sm text-zinc-500">{{ __('contact_center.stats.'.$key) }}</span><strong class="mt-1 block text-2xl">{{ $count }}</strong></div>
        @endforeach
    </section>

    <x-app.panel :title="__('contact_center.contacts.title')" :help="__('contact_center.contacts.help')" icon="phone">
        <div class="space-y-3">
            @forelse ($contactPoints as $point)
                <article class="{{ $item }}" wire:key="point-{{ $point->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <flux:icon :name="$kindIcons[$point->kind] ?? 'link'" class="mt-1 size-5 shrink-0 text-zinc-500" />
                            <div class="min-w-0">
                                <div class="text-sm text-zinc-500">{{ $point->label ?: ($kindLabels[$point->kind] ?? $point->kind) }}</div>
                                <div class="font-semibold [overflow-wrap:anywhere]" dir="ltr">{{ $point->value }}</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if ($point->is_primary)<flux:badge size="sm" color="amber">★ {{ __('contact_center.primary_badge') }}</flux:badge>@endif
                                    <flux:badge size="sm" :color="$point->is_verified ? 'green' : 'zinc'">{{ $point->is_verified ? __('contact_center.verified') : __('contact_center.unverified') }}</flux:badge>
                                    <flux:badge size="sm" :color="$point->visibility === 'public' ? 'blue' : 'zinc'">{{ $visibilityLabels[$point->visibility] ?? $point->visibility }}</flux:badge>
                                </div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('profile.contacts.contact.destroy', $point) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('contact_center.contacts.delete_confirm')) }})">
                            @csrf @method('DELETE')
                            <flux:button type="submit" size="sm" variant="danger">{{ __('contact_center.contacts.delete') }}</flux:button>
                        </form>
                    </div>
                    <details>
                        <summary class="cursor-pointer py-1 text-sm font-medium">{{ __('contact_center.contacts.edit') }}</summary>
                        <form method="POST" action="{{ route('profile.contacts.contact.update', $point) }}" class="mt-2 {{ $subform }}">
                            @csrf @method('PUT')
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-form.select name="kind" :options="$kindLabels" :selected="$point->kind" :label="__('contact_center.contacts.kind')" />
                                <x-form.input name="label" :value="$point->label" :label="__('contact_center.contacts.label')" :placeholder="__('contact_center.contacts.label_placeholder')" />
                                <x-form.select name="visibility" :options="$visibilityLabels" :selected="$point->visibility" :label="__('contact_center.contacts.visibility')" />
                            </div>
                            <x-form.input name="value" :value="$point->value" required dir="ltr" :label="__('contact_center.contacts.value')" />
                            <x-form.checkbox name="is_primary" :checked="(bool) $point->is_primary" :label="__('contact_center.contacts.primary')" />
                            <flux:button type="submit" variant="primary" size="sm">{{ __('contact_center.contacts.save') }}</flux:button>
                        </form>
                    </details>
                </article>
            @empty
                <flux:text>{{ __('contact_center.contacts.empty') }}</flux:text>
            @endforelse
        </div>

        <form method="POST" action="{{ route('profile.contacts.contact.store') }}" class="{{ $subform }}">
            @csrf
            <flux:heading size="sm" level="3">{{ __('contact_center.contacts.add_title') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.select name="kind" required :options="$kindLabels" :label="__('contact_center.contacts.kind')" />
                <x-form.input name="label" :label="__('contact_center.addresses.label')" :placeholder="__('contact_center.contacts.add_label_placeholder')" />
                <x-form.select name="visibility" required :options="$visibilityLabels" selected="private" :label="__('contact_center.contacts.show_to')" />
            </div>
            <x-form.input name="value" required dir="ltr" :label="__('contact_center.contacts.add_value')" :placeholder="__('contact_center.contacts.add_value_placeholder')" />
            <x-form.checkbox name="is_primary" :label="__('contact_center.contacts.add_primary')" />
            <flux:button type="submit" variant="primary" icon="plus">{{ __('contact_center.contacts.add') }}</flux:button>
            <flux:text class="text-xs">{{ __('contact_center.contacts.note') }}</flux:text>
        </form>
    </x-app.panel>

    <x-app.panel :title="__('contact_center.addresses.title')" :help="__('contact_center.addresses.help')" icon="map-pin">
        <div class="space-y-3">
            @forelse ($addresses as $address)
                <article class="{{ $item }}" wire:key="address-{{ $address->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <flux:icon.home class="mt-1 size-5 shrink-0 text-zinc-500" />
                            <div class="min-w-0">
                                <div class="text-sm text-zinc-500">{{ $address->label ?: ($addressTypes[$address->type] ?? $address->type) }}</div>
                                <div class="[overflow-wrap:anywhere]" dir="auto">
                                    {{ collect([$address->province, $address->city, $address->district, $address->street, $address->alley])->filter()->join('، ') }}
                                    @if ($address->building_no) {{ __('contact_center.addresses.building_line', ['no' => $address->building_no]) }} @endif
                                    @if ($address->unit) {{ __('contact_center.addresses.unit_line', ['unit' => $address->unit]) }} @endif
                                </div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if ($address->is_primary)<flux:badge size="sm" color="amber">★ {{ __('contact_center.primary_badge') }}</flux:badge>@endif
                                    <flux:badge size="sm" :color="$address->visibility === 'public' ? 'blue' : 'zinc'">{{ $visibilityLabels[$address->visibility] ?? $address->visibility }}</flux:badge>
                                    @if ($address->postal_code)<flux:badge size="sm" color="zinc">{{ __('contact_center.addresses.postal_badge', ['code' => $address->postal_code]) }}</flux:badge>@endif
                                </div>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('profile.contacts.address.destroy', $address) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('contact_center.addresses.delete_confirm')) }})">
                            @csrf @method('DELETE')
                            <flux:button type="submit" size="sm" variant="danger">{{ __('contact_center.contacts.delete') }}</flux:button>
                        </form>
                    </div>
                    <details>
                        <summary class="cursor-pointer py-1 text-sm font-medium">{{ __('contact_center.addresses.edit') }}</summary>
                        <form method="POST" action="{{ route('profile.contacts.address.update', $address) }}" class="mt-2 {{ $subform }}">
                            @csrf @method('PUT')
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <x-form.select name="type" :options="$addressTypes" :selected="$address->type" :label="__('contact_center.addresses.type')" />
                                <x-form.input name="label" :value="$address->label" :label="__('contact_center.addresses.label')" />
                                <x-form.select name="visibility" :options="$visibilityLabels" :selected="$address->visibility" :label="__('contact_center.contacts.show_to')" />
                                <x-form.input name="country_code" :value="$address->country_code ?: 'IR'" maxlength="2" :label="__('contact_center.addresses.country')" />
                                @foreach (['province', 'district', 'street', 'alley', 'building_no', 'unit', 'postal_code'] as $field)
                                    @if ($field === 'district')
                                        <x-form.input name="city" :value="$address->city" required :label="__('contact_center.addresses.city').' *'" />
                                    @endif
                                    <x-form.input :name="$field" :value="$address->{$field}" :label="__('contact_center.addresses.'.$field)" />
                                @endforeach
                            </div>
                            <x-form.checkbox name="is_primary" :checked="(bool) $address->is_primary" :label="__('contact_center.addresses.primary')" />
                            <flux:button type="submit" variant="primary" size="sm">{{ __('contact_center.contacts.save') }}</flux:button>
                        </form>
                    </details>
                </article>
            @empty
                <flux:text>{{ __('contact_center.addresses.empty') }}</flux:text>
            @endforelse
        </div>

        <form method="POST" action="{{ route('profile.contacts.address.store') }}" class="{{ $subform }}">
            @csrf
            <flux:heading size="sm" level="3">{{ __('contact_center.addresses.add_title') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-form.select name="type" required :options="$addressTypes" :label="__('contact_center.addresses.add_type')" />
                <x-form.input name="label" :label="__('contact_center.contacts.label')" :placeholder="__('contact_center.addresses.label_placeholder')" />
                <x-form.select name="visibility" required :options="$visibilityLabels" selected="private" :label="__('contact_center.contacts.show_to')" />
                <x-form.input name="country_code" value="IR" maxlength="2" :label="__('contact_center.addresses.country')" />
                <x-form.input name="province" :label="__('contact_center.addresses.province')" />
                <x-form.input name="city" required :label="__('contact_center.addresses.city').' *'" />
                @foreach (['district', 'street', 'alley', 'building_no', 'unit', 'postal_code'] as $field)
                    <x-form.input :name="$field" :label="__('contact_center.addresses.'.$field)" />
                @endforeach
            </div>
            <x-form.checkbox name="is_primary" :label="__('contact_center.addresses.primary')" />
            <flux:button type="submit" variant="primary" icon="plus">{{ __('contact_center.addresses.add') }}</flux:button>
        </form>
    </x-app.panel>
</div>
@endsection
