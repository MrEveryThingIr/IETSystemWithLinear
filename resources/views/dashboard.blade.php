@extends('layouts.app')

@section('title', __('ui.dashboard.title'))

@section('content')
    @php
        $dashboardUser = auth()->user();
        $dashboardName = $dashboardUser->actor?->profile?->display_name ?: $dashboardUser->username;
    @endphp
    <x-app.page-header :title="__('ui.dashboard.title')" :description="__('ui.dashboard.welcome', ['username' => $dashboardName])" />
    <div class="space-y-4">
        <x-app.empty-state :title="__('ui.dashboard.ready')" :description="__('ui.dashboard.verified')" icon="check-circle" />
        <div class="flex justify-center">
            <flux:button :href="route('contexts.personal')" variant="primary" icon="document-text">
                {{ __('ui.context_content.my_content') }}
            </flux:button>
        </div>
    </div>
@endsection
