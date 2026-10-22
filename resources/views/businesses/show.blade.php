@extends('layouts.app')
@section('title', $business->name)

@section('content')
@php
    $card = 'min-w-0 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500';
    $item = 'min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50';
    $subform = 'space-y-3 rounded-lg border border-dashed border-zinc-300 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/40';
    $actorName = fn ($actor) => $actor->user?->username ?? $actor->user?->email ?? ('Actor #'.$actor->getKey());
@endphp
<div class="mx-auto max-w-6xl space-y-6" data-business-show>
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">{{ session('status') }}</flux:callout>
    @endif
    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle">
            <ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </flux:callout>
    @endif

    <x-app.page-header :title="$business->name" :description="$business->short_intro ?: __('business.show.missing_intro')">
        <x-slot:actions>
            <flux:badge>{{ $kindLabels[$business->kind] ?? $business->kind }}</flux:badge>
            <flux:badge color="zinc">{{ __('business.show.code') }} {{ $business->code }}</flux:badge>
            @if ($publicSiteUrl)
                <flux:button :href="$publicSiteUrl" target="_blank" rel="noopener" size="sm" icon="globe-alt">{{ __('business.public_site.open') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-app.page-header>
    @if ($canOperate)
        <flux:text class="-mt-3 text-sm">{{ __('business.show.owner') }}: {{ $actorName($business->owner) }}</flux:text>
    @endif

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
                    <flux:button :href="$businessNextHref" variant="primary">{{ $businessNextLabel }}</flux:button>
                @endif
                <flux:button :href="route('businesses.clients.index', $business)" variant="ghost">{{ __('workflow.business.sections.clients') }}</flux:button>
                <flux:button :href="route('businesses.catalog.index', $business)" variant="ghost">{{ __('workflow.business.sections.catalog') }}</flux:button>
            </x-slot:actions>

            <x-slot:advanced>
                <div class="flex flex-wrap gap-2">
                    @if ($canUsePlanner && $businessContext)
                        <flux:button :href="route('planner.index', ['context' => $businessContext->uuid])" size="sm" variant="ghost">{{ __('workflow.business.sections.work') }}</flux:button>
                    @endif
                    @if ($canUseDeals)
                        <flux:button :href="route('deals.index')" size="sm" variant="ghost">{{ __('workflow.business.sections.deals') }}</flux:button>
                    @endif
                    @if ($canUseMoney)
                        <flux:button :href="route('bookkeeping.index')" size="sm" variant="ghost">{{ __('workflow.business.sections.money') }}</flux:button>
                    @endif
                    <flux:button href="#team-settings" size="sm" variant="ghost">{{ __('workflow.business.sections.manage') }}</flux:button>
                </div>
            </x-slot:advanced>
        @endif

        <x-slot:help>{{ __('workflow.business.help') }}</x-slot:help>
    </x-app.workflow-shell>

    @if ($canOperate)
        <section class="grid gap-3 [grid-template-columns:repeat(auto-fill,minmax(min(100%,10rem),1fr))]" data-business-stats>
            <a class="{{ $card }}" href="#team-settings"><span class="text-sm text-zinc-500">{{ __('business.show.stats.members') }}</span><strong class="mt-1 block text-2xl">{{ $business->memberships->count() }}</strong></a>
            <a class="{{ $card }}" href="{{ route('businesses.clients.index', $business) }}"><span class="text-sm text-zinc-500">{{ __('business.show.stats.clients') }}</span><strong class="mt-1 block text-2xl">{{ $business->businessContacts->count() }}</strong></a>
            <a class="{{ $card }}" href="{{ route('businesses.catalog.index', $business) }}"><span class="text-sm text-zinc-500">{{ __('business.show.stats.listings') }}</span><strong class="mt-1 block text-2xl">{{ $business->listings->count() }}</strong></a>
            @if ($canUsePlanner)
                <a class="{{ $card }}" href="{{ route('planner.index', ['context' => $businessContext->uuid]) }}"><span class="text-sm text-zinc-500">{{ __('business.show.stats.routines') }}</span><strong class="mt-1 block text-2xl">{{ $routineCount }}</strong></a>
            @endif
        </section>

        <section class="grid gap-3 [grid-template-columns:repeat(auto-fill,minmax(min(100%,14rem),1fr))]" data-business-quick>
            <a class="{{ $card }}" href="{{ route('businesses.clients.index', $business) }}">
                <span class="flex items-center gap-2 font-semibold"><flux:icon.users class="size-5 text-zinc-500" />{{ __('business.show.quick.clients') }}</span>
                <span class="mt-1 block text-sm text-zinc-500">{{ __('business.show.quick.clients_help') }}</span>
            </a>
            <a class="{{ $card }}" href="{{ route('businesses.catalog.index', $business) }}">
                <span class="flex items-center gap-2 font-semibold"><flux:icon.rectangle-stack class="size-5 text-zinc-500" />{{ __('business.show.quick.catalog') }}</span>
                <span class="mt-1 block text-sm text-zinc-500">{{ __('business.show.quick.catalog_help') }}</span>
            </a>
            @if ($canUsePlanner)
                <a class="{{ $card }}" href="{{ route('planner.index', ['context' => $businessContext->uuid]) }}">
                    <span class="flex items-center gap-2 font-semibold"><flux:icon.calendar-days class="size-5 text-zinc-500" />{{ __('business.show.quick.planner') }}</span>
                    <span class="mt-1 block text-sm text-zinc-500">{{ __('business.show.quick.planner_help') }}</span>
                </a>
                <a class="{{ $card }}" href="{{ route('planner.create', ['context' => $businessContext->uuid]) }}">
                    <span class="flex items-center gap-2 font-semibold"><flux:icon.plus class="size-5 text-zinc-500" />{{ __('business.show.quick.new_plan') }}</span>
                    <span class="mt-1 block text-sm text-zinc-500">{{ __('business.show.quick.new_plan_help') }}</span>
                </a>
            @endif
        </section>
    @endif

    @if ($canOperate && $canUseMarket)
        <x-app.panel :title="__('business.show.market.title')" :help="__('business.show.market.help')" icon="megaphone" data-business-market>
            <x-slot:actions>
                @if ($canManage)
                    <flux:button :href="route('intents.create', ['as' => 'business:'.$business->id])" size="sm" variant="primary" icon="plus">{{ __('business.show.market.add') }}</flux:button>
                @endif
                <flux:button :href="route('intents.index', ['scope' => 'mine'])" size="sm" variant="ghost">{{ __('business.show.market.all') }}</flux:button>
            </x-slot:actions>
            @forelse ($marketItems as $intent)
                <a href="{{ route('intents.index', ['highlight' => $intent->uuid, 'scope' => 'mine']) }}" class="flex min-w-0 flex-wrap items-center justify-between gap-2 {{ $item }} hover:border-zinc-400">
                    <span class="min-w-0 font-medium [overflow-wrap:anywhere]" dir="auto">{{ $intent->title ?: $intent->concept?->displayLabel() }}</span>
                    <span class="flex flex-wrap gap-1">
                        <flux:badge size="sm" :color="$intent->kind->value === 'need' ? 'amber' : 'green'">{{ __('intents.kinds.'.$intent->kind->value) }}</flux:badge>
                        <flux:badge size="sm" color="zinc">{{ __('business.show.market.status.'.$intent->status->value) }}</flux:badge>
                    </span>
                </a>
            @empty
                <flux:text>{{ __('business.show.market.empty') }}</flux:text>
            @endforelse
        </x-app.panel>
    @endif

    @if ($canOperate)
        <x-app.panel id="public-site" :title="__('business.public_site.title')" :help="__('business.public_site.help')" icon="globe-alt">
            <x-slot:actions>
                @if ($publicSiteUrl)
                    <flux:button :href="$publicSiteUrl" target="_blank" rel="noopener" size="sm" variant="primary">{{ __('business.public_site.open') }}</flux:button>
                @else
                    <flux:badge color="zinc">{{ __('business.public_site.not_live') }}</flux:badge>
                @endif
            </x-slot:actions>

            @if ($publicSiteUrl)
                <div class="{{ $item }}">
                    <strong>{{ __('business.public_site.live') }}</strong>
                    <div class="mt-1 text-sm text-zinc-500 [overflow-wrap:anywhere]" dir="ltr">{{ $publicSiteUrl }}</div>
                </div>
            @elseif ($canManage)
                <div class="{{ $item }} text-sm">{{ __('business.public_site.publish_help') }}</div>
            @endif

            @if ($canManage)
                <form class="{{ $subform }}" method="POST" enctype="multipart/form-data" action="{{ route('businesses.public-site.update', $business) }}">
                    @csrf @method('PUT')
                    <flux:heading size="sm" level="3">{{ __('business.public_site.intro_video_title') }}</flux:heading>
                    <flux:text class="text-sm">{{ __('business.public_site.intro_video_help') }}</flux:text>
                    @if ($introVideo)
                        <div class="{{ $item }} space-y-2">
                            <strong>{{ __('business.public_site.intro_video_current') }}</strong>
                            <div class="text-sm text-zinc-500 [overflow-wrap:anywhere]">{{ $introVideo['original_name'] ?? __('business.public_site.intro_video_file') }}</div>
                            @if ($publicSiteUrl)
                                <flux:button size="sm" variant="ghost" target="_blank" rel="noopener" :href="route('public.businesses.intro-video', ['business' => $business->slug])">{{ __('business.public_site.intro_video_preview') }}</flux:button>
                            @endif
                            <x-form.checkbox name="remove_intro_video" :label="__('business.public_site.intro_video_remove')" />
                        </div>
                    @endif
                    <x-form.input type="file" name="intro_video" :label="$introVideo ? __('business.public_site.intro_video_replace') : __('business.public_site.intro_video_upload')" :hint="__('business.public_site.intro_video_limit')" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" />

                    <flux:heading size="sm" level="3" class="pt-2">{{ __('business.public_site.featured_title') }}</flux:heading>
                    <flux:text class="text-sm">{{ __('business.public_site.featured_help') }}</flux:text>
                    @if ($publicSiteCandidates->isNotEmpty())
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($publicSiteCandidates as $candidate)
                                <div class="{{ $item }}">
                                    <x-form.checkbox name="featured_business_ids[]" :value="$candidate->id" :checked="$featuredBusinessIds->contains((int) $candidate->id)" :label="$candidate->name">
                                        @if ($candidate->short_intro)<small class="mt-1 block text-zinc-500">{{ $candidate->short_intro }}</small>@endif
                                    </x-form.checkbox>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="{{ $item }} text-sm">{{ __('business.public_site.no_candidates') }}</div>
                    @endif
                    <flux:button type="submit" variant="primary">{{ __('business.public_site.save') }}</flux:button>
                </form>
            @endif
        </x-app.panel>
    @endif

    @if ($canOperate && ($canUseMarket || $canUseDeals))
        <x-app.panel :title="__('business.show.money.activity_title')" :help="__('business.show.money.activity_help')" icon="chart-bar" data-business-activity>
            <div class="flex flex-wrap gap-2">
                <flux:badge>{{ $economyProjection['market_intent_count'] ?? 0 }} {{ __('business.show.money.market') }}</flux:badge>
                <flux:badge>{{ $economyProjection['deal_count'] ?? 0 }} {{ __('business.show.money.deals') }}</flux:badge>
                <flux:badge>{{ $economyProjection['contract_count'] ?? 0 }} {{ __('business.show.money.contracts') }}</flux:badge>
            </div>
        </x-app.panel>
    @endif

    @if ($canOperate && $business->kind === 'real_estate' && $business->publicIntakePortals->isNotEmpty())
        <x-app.panel :title="__('business.show.real_estate.title')" :help="__('business.show.real_estate.help')" icon="home-modern">
            @foreach ($business->publicIntakePortals as $portal)
                <div class="flex flex-wrap items-center justify-between gap-3 {{ $item }}">
                    <div class="min-w-0">
                        <strong class="[overflow-wrap:anywhere]">{{ $portal->title }}</strong>
                        <div class="mt-1 text-sm text-zinc-500">{{ __('business.show.real_estate.preserved') }}</div>
                    </div>
                    <flux:button size="sm" variant="ghost" :href="route('office.real-estate.index', ['portal' => $portal->uuid])">{{ __('business.show.real_estate.cases') }}</flux:button>
                </div>
            @endforeach
        </x-app.panel>
    @endif

    <x-app.panel id="business-profile" :title="__('business.show.profile.title')" :help="__('business.show.profile.help')" icon="building-storefront">
        @if ($canManage)
            <form method="POST" action="{{ route('businesses.update', $business) }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-form.input name="name" :value="$business->name" :label="__('business.show.profile.name')" required />
                    <x-form.select name="kind" :options="$kindLabels" :selected="$business->kind" :label="__('business.show.profile.type')" />
                    <x-form.select name="visibility" :options="$visibilityLabels" :selected="$business->visibility" :label="__('business.show.profile.visibility')" />
                    <x-form.input name="legal_name" :value="$business->legal_name" :label="__('business.show.profile.legal_name')" />
                    <x-form.input type="number" name="founded_year" :value="$business->founded_year" :label="__('business.show.profile.founded_year')" />
                    <x-form.select name="status" :options="['active' => __('business.show.profile.active'), 'paused' => __('business.show.profile.paused')]" :selected="$business->status" :label="__('business.show.profile.status')" />
                </div>
                <x-form.input name="short_intro" :value="$business->short_intro" :label="__('business.show.profile.short_intro')" />
                <x-form.textarea name="description" :value="$business->description" :label="__('business.show.profile.description')" />
                <flux:button type="submit" variant="primary">{{ __('business.show.profile.save') }}</flux:button>
            </form>
        @else
            <p class="[overflow-wrap:anywhere]" dir="auto">{{ $business->description ?: __('business.show.profile.empty_description') }}</p>
        @endif
    </x-app.panel>

    <x-app.panel :title="__('business.show.contacts.title')" :help="__('business.show.contacts.help')" icon="phone">
        <div class="space-y-2">
            @forelse ($business->contactPoints as $point)
                <div class="flex flex-wrap items-center justify-between gap-3 {{ $item }}">
                    <div class="min-w-0">
                        <strong>{{ $point->label ?: $point->kind }}</strong>
                        <div dir="ltr" class="mt-1 font-semibold [overflow-wrap:anywhere]">{{ $point->value }}</div>
                        <flux:badge size="sm" color="zinc" class="mt-1">{{ $point->visibility }}</flux:badge>
                    </div>
                    @if ($canManage)
                        <form method="POST" action="{{ route('businesses.contacts.destroy', [$business, $point]) }}">@csrf @method('DELETE')<flux:button type="submit" size="sm" variant="danger">{{ __('business.show.contacts.delete') }}</flux:button></form>
                    @endif
                </div>
            @empty
                <flux:text>{{ __('business.show.contacts.empty') }}</flux:text>
            @endforelse
        </div>

        @if ($canManage)
            <form class="{{ $subform }}" method="POST" action="{{ route('businesses.contacts.store', $business) }}">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-form.select name="kind" :label="__('business.show.contacts.type')" :options="collect(['mobile', 'phone', 'email', 'website', 'whatsapp'])->mapWithKeys(fn ($kind) => [$kind => __('business.show.contacts.kinds.'.$kind)])->all()" />
                    <x-form.input name="label" :label="__('business.show.contacts.label')" :placeholder="__('business.show.contacts.label_placeholder')" />
                    <x-form.select name="visibility" :options="$visibilityLabels" :label="__('business.show.contacts.visibility')" />
                </div>
                <x-form.input name="value" :label="__('business.show.contacts.value')" required />
                <x-form.checkbox name="is_primary" :label="__('business.show.contacts.primary')" />
                <flux:button type="submit" variant="primary" icon="plus">{{ __('business.show.contacts.add') }}</flux:button>
            </form>
        @endif
    </x-app.panel>

    <x-app.panel :title="__('business.show.addresses.title')" :help="__('business.show.addresses.help')" icon="map-pin">
        <div class="space-y-2">
            @forelse ($business->addresses as $address)
                <div class="flex flex-wrap items-center justify-between gap-3 {{ $item }}">
                    <div class="min-w-0">
                        <strong>{{ $address->label ?: $address->type }}</strong>
                        <div class="mt-1 [overflow-wrap:anywhere]" dir="auto">{{ collect([$address->province, $address->city, $address->district, $address->street])->filter()->join(' · ') }}</div>
                    </div>
                    @if ($canManage)
                        <form method="POST" action="{{ route('businesses.addresses.destroy', [$business, $address]) }}">@csrf @method('DELETE')<flux:button type="submit" size="sm" variant="danger">{{ __('business.show.addresses.delete') }}</flux:button></form>
                    @endif
                </div>
            @empty
                <flux:text>{{ __('business.show.addresses.empty') }}</flux:text>
            @endforelse
        </div>

        @if ($canManage)
            <form class="{{ $subform }}" method="POST" action="{{ route('businesses.addresses.store', $business) }}">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-form.select name="type" :label="__('business.show.addresses.type')" :options="collect(['work', 'branch', 'billing', 'shipping', 'project_site', 'other'])->mapWithKeys(fn ($type) => [$type => __('business.show.addresses.types.'.$type)])->all()" />
                    <x-form.input name="label" :label="__('business.show.addresses.label')" :placeholder="__('business.show.addresses.label_placeholder')" />
                    <x-form.select name="visibility" :options="$visibilityLabels" :label="__('business.show.addresses.visibility')" />
                    <x-form.input name="province" :label="__('business.show.addresses.province')" />
                    <x-form.input name="city" :label="__('business.show.addresses.city').' *'" required />
                    <x-form.input name="district" :label="__('business.show.addresses.district')" />
                    <x-form.input name="street" :label="__('business.show.addresses.street')" />
                    <x-form.input name="alley" :label="__('business.show.addresses.alley')" />
                    <x-form.input name="building_no" :label="__('business.show.addresses.building_no')" />
                </div>
                <input type="hidden" name="country_code" value="IR">
                <flux:button type="submit" variant="primary" icon="plus">{{ __('business.show.addresses.add') }}</flux:button>
            </form>
        @endif
    </x-app.panel>

    @if ($canOperate)
        <x-app.panel id="team-settings" :title="__('business.show.team.title')" :help="__('business.show.team.help')" icon="user-group">
            <x-slot:actions>
                <flux:button size="sm" variant="ghost" :href="route('profile.professions.index')">{{ __('capabilities.title') }}</flux:button>
            </x-slot:actions>

            @if ($capabilitySummary->isNotEmpty())
                <div data-business-capabilities>
                    <div class="mb-2 text-sm font-medium">{{ __('business.show.team.capabilities') }}</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($capabilitySummary as $skill)
                            <flux:badge size="sm" color="indigo" class="max-w-full">
                                <span class="min-w-0 [overflow-wrap:anywhere]">{{ $skill['label'] }}</span>@if ($skill['count'] > 1)<span class="ms-1 opacity-75">×{{ $skill['count'] }}</span>@endif
                            </flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="space-y-3">
                @foreach ($business->memberships as $membership)
                    <article class="space-y-3 {{ $item }}" wire:key="membership-{{ $membership->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <strong class="text-base [overflow-wrap:anywhere]">{{ $actorName($membership->actor) }}</strong>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <flux:badge size="sm" :color="$membership->role === 'owner' ? 'amber' : ($membership->role === 'manager' ? 'blue' : 'zinc')">{{ $roleLabels[$membership->role] ?? $membership->role }}</flux:badge>
                                    @if ($membership->job_title)<flux:badge size="sm" color="zinc">{{ $membership->job_title }}</flux:badge>@endif
                                </div>
                            </div>
                            @if ($canManage && $membership->role !== 'owner')
                                <form method="POST" action="{{ route('businesses.members.destroy', [$business, $membership]) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('business.show.team.remove_confirm')) }})">@csrf @method('DELETE')<flux:button type="submit" size="sm" variant="danger">{{ __('business.show.team.remove') }}</flux:button></form>
                            @endif
                        </div>

                        @if ($canManage && $membership->role !== 'owner')
                            <form method="POST" action="{{ route('businesses.members.update', [$business, $membership]) }}" class="{{ $subform }}">
                                @csrf @method('PUT')
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-form.select name="role" :options="['member' => __('business.show.team.member'), 'manager' => __('business.show.team.manager')]" :selected="$membership->role" :label="__('business.show.team.management_role')" />
                                    <x-form.input name="job_title" :value="$membership->job_title" :label="__('business.show.team.job_title')" :placeholder="__('business.show.team.job_title_placeholder')" />
                                </div>
                                <flux:button type="submit" size="sm">{{ __('business.show.team.save_role') }}</flux:button>
                            </form>
                        @endif

                        <livewire:business.member-capabilities :membership="$membership" :key="'member-capabilities-'.$membership->id" />
                    </article>
                @endforeach
            </div>

            @if ($canManage)
                <form class="{{ $subform }}" method="POST" action="{{ route('businesses.members.store', $business) }}">
                    @csrf
                    <flux:heading size="sm" level="3">{{ __('business.show.team.add_user') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-form.input name="user" :label="__('business.show.team.user')" required />
                        <x-form.select name="role" :options="['member' => __('business.show.team.member'), 'manager' => __('business.show.team.manager')]" :label="__('business.show.team.role')" />
                        <x-form.input name="job_title" :label="__('business.show.team.job_title')" :placeholder="__('business.show.team.job_placeholder')" />
                    </div>
                    <flux:button type="submit" variant="primary">{{ __('business.show.team.add_member') }}</flux:button>
                </form>
            @endif

            @if ($canManageOwnership)
                <form class="{{ $subform }}" method="POST" action="{{ route('businesses.transfer-ownership', $business) }}">
                    @csrf
                    <flux:heading size="sm" level="3" class="text-amber-800 dark:text-amber-300">{{ __('business.show.team.transfer_title') }}</flux:heading>
                    <flux:text class="text-sm">{{ __('business.show.team.transfer_help') }}</flux:text>
                    <x-form.select name="actor_id" required :placeholder="__('business.show.team.select_owner')"
                        :options="$business->memberships->where('role', '!=', 'owner')->mapWithKeys(fn ($candidate) => [$candidate->actor_id => $actorName($candidate->actor)])->all()" />
                    <flux:button type="submit" variant="danger" onclick="return confirm({{ \Illuminate\Support\Js::from(__('business.show.team.transfer_confirm')) }})">{{ __('business.show.team.transfer') }}</flux:button>
                </form>
            @endif
        </x-app.panel>
    @endif
</div>
@endsection
