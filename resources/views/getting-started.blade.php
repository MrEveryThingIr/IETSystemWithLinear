@extends('layouts.app')

@section('title', __('access.getting_started.title'))

@section('content')
<section class="mx-auto max-w-4xl space-y-6">
    <x-app.page-header :title="__('access.getting_started.title')" :description="__('access.getting_started.help')" />
    <div class="grid gap-4 md:grid-cols-2">
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('access.getting_started.intent_title') }}</flux:heading>
            <flux:text>{{ __('access.getting_started.intent_help') }}</flux:text>
            <flux:button :href="route('profile.edit')" variant="primary">{{ __('access.getting_started.intent_button') }}</flux:button>
        </flux:card>
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('access.getting_started.profile_title') }}</flux:heading>
            <flux:text>{{ __('access.getting_started.profile_help') }}</flux:text>
            <flux:button :href="route('profile.edit')" variant="ghost">{{ __('access.getting_started.profile_button') }}</flux:button>
        </flux:card>
    </div>
</section>
@endsection
