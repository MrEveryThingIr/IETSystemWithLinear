@extends('layouts.app')
@section('title', __('business.create.title'))

@section('content')
<div class="mx-auto max-w-3xl space-y-6" data-business-create>
    <x-app.page-header :title="__('business.create.heading')" :description="__('business.create.intro')" />

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle">
            <ul class="list-disc ps-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </flux:callout>
    @endif

    <form method="POST" action="{{ route('businesses.store') }}">
        @csrf
        <x-app.panel :title="__('business.create.section')" :help="__('business.create.section_help')" icon="building-storefront">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input remember name="name" :label="__('business.create.name').' *'" required :placeholder="__('business.create.name_placeholder')" />
                <x-form.select remember name="kind" :label="__('business.create.type').' *'" :options="$kindLabels" required />
                <x-form.input remember name="legal_name" :label="__('business.create.legal_name')" :placeholder="__('business.create.legal_placeholder')" />
                <x-form.input remember type="number" name="founded_year" :label="__('business.create.founded_year')" :placeholder="__('business.create.year_placeholder')" />
            </div>
            <x-form.input remember name="short_intro" maxlength="300" :label="__('business.create.short_intro')" :placeholder="__('business.create.intro_placeholder')" />
            <x-form.textarea remember name="description" rows="5" :label="__('business.create.description')" :placeholder="__('business.create.description_placeholder')" />
            <x-form.select remember name="visibility" :label="__('business.create.visibility')" :options="$visibilityLabels ?? \App\Support\BusinessDirectory::visibilityLabels()" selected="private" />
            <input type="hidden" name="status" value="active">
            <div class="flex flex-wrap gap-2 pt-2">
                <flux:button type="submit" variant="primary">{{ __('business.create.submit') }}</flux:button>
                <flux:button :href="route('businesses.index')" variant="ghost">{{ __('business.create.cancel') }}</flux:button>
            </div>
        </x-app.panel>
    </form>
</div>
@endsection
