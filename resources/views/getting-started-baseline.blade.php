@extends('layouts.app')

@section('title', __('planning_baseline.onboarding.title'))

@section('content')
<section class="mx-auto max-w-3xl space-y-6">
    <x-app.page-header :title="__('planning_baseline.onboarding.title')" :description="__('planning_baseline.onboarding.help')" />

    <div class="grid gap-4 md:grid-cols-2">
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('planning_baseline.onboarding.workspace_title') }}</flux:heading>
            <flux:text>{{ __('planning_baseline.onboarding.workspace_help') }}</flux:text>
            <flux:button :href="route('dashboard')" variant="primary">
                {{ __('planning_baseline.onboarding.workspace_button') }}
            </flux:button>
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('planning_baseline.onboarding.identity_title') }}</flux:heading>
            <flux:text>{{ __('planning_baseline.onboarding.identity_help') }}</flux:text>
            <flux:button :href="route('profile.edit')" variant="ghost">
                {{ __('planning_baseline.onboarding.identity_button') }}
            </flux:button>
        </flux:card>
    </div>
</section>
@endsection
