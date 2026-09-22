@extends('layouts.app')

@section('title', __('ui.dashboard.title'))

@section('content')
    @php
        $dashboardUser = auth()->user();
        $dashboardName = $dashboardUser->actor?->profile?->display_name ?: $dashboardUser->username;
    @endphp
    <x-app.page-header :title="__('ui.dashboard.title')" :description="__('ui.dashboard.welcome', ['username' => $dashboardName])" />
    <x-app.empty-state :title="__('ui.dashboard.ready')" :description="__('ui.dashboard.verified')" icon="check-circle" />
@endsection
