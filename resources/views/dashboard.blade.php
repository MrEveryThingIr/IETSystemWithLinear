@extends('layouts.app')

@section('title', __('ui.dashboard.title'))

@section('content')
    <x-app.page-header :title="__('ui.dashboard.title')" :description="__('ui.dashboard.welcome', ['username' => auth()->user()->username])" />
    <x-app.empty-state :title="__('ui.dashboard.ready')" :description="__('ui.dashboard.verified')" icon="check-circle" />
@endsection
