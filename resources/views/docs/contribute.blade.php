@php
    $user = auth()->user();
    $selectedField = old('field', $field);
@endphp

<x-docs.layout :title="__('docs.contribute.title')" :description="__('docs.contribute.intro')" :canonical="route('docs.contribute')" :noindex="$inviteOnly">
    <main class="mx-auto w-full max-w-3xl space-y-8 px-4 py-8 sm:px-6 sm:py-12" data-docs-contribute>
        <div class="space-y-3">
            <h1 class="text-3xl font-semibold leading-tight tracking-tight">{{ __('docs.contribute.title') }}</h1>
            <p class="text-lg leading-8 text-zinc-600 dark:text-zinc-300">{{ __('docs.contribute.intro') }}</p>
        </div>

        @if (session('docs.thanks'))
            <div class="rounded-2xl border border-green-300 bg-green-50 p-5 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100" role="status" data-docs-thanks>
                <p class="font-semibold">{{ __('docs.contribute.thanks_title') }}</p>
                <p class="mt-1 leading-7">{{ __('docs.contribute.thanks_body') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('docs.contribute.submit') }}" class="space-y-8" data-docs-form>
            @csrf
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <fieldset class="space-y-4">
                <legend class="text-lg font-semibold">{{ __('docs.contribute.about_you') }}</legend>
                @if ($user)
                    <p class="text-sm text-zinc-500">{{ __('docs.contribute.member_note') }}</p>
                @else
                    <p class="text-sm text-zinc-500">{{ __('docs.contribute.guest_note') }}</p>
                @endif
                <flux:input name="name" :label="__('docs.contribute.name')" :value="old('name', '')" maxlength="120" required />
                <flux:input name="email" type="email" :label="__('docs.contribute.email')" :value="old('email', $user?->email ?? '')" maxlength="190" required />
                <flux:select name="field" :label="__('docs.contribute.field')" required>
                    <option value="">—</option>
                    @foreach (config('docs.fields') as $option)
                        <option value="{{ $option }}" @selected($selectedField === $option)>{{ __('docs.fields.'.$option) }}</option>
                    @endforeach
                </flux:select>
                <flux:input name="field_other" :label="__('docs.contribute.field_other')" :value="old('field_other')" maxlength="80" />
                <flux:textarea name="expertise" rows="3" :label="__('docs.contribute.expertise')" :placeholder="__('docs.contribute.expertise_hint')" required>{{ old('expertise') }}</flux:textarea>
                <flux:textarea name="education" rows="3" :label="__('docs.contribute.education')" :placeholder="__('docs.contribute.education_hint')" required>{{ old('education') }}</flux:textarea>
                <flux:textarea name="experience" rows="3" :label="__('docs.contribute.experience')" :placeholder="__('docs.contribute.experience_hint')">{{ old('experience') }}</flux:textarea>
                <flux:textarea name="links" rows="2" :label="__('docs.contribute.links')" :placeholder="__('docs.contribute.links_hint')">{{ old('links') }}</flux:textarea>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-lg font-semibold">{{ __('docs.contribute.your_proposal') }}</legend>
                <flux:select name="topic" :label="__('docs.contribute.topic')" required>
                    <option value="general" @selected(old('topic', $topic) === 'general')>{{ __('docs.contribute.topic_general') }}</option>
                    @foreach ($pages as $page)
                        <option value="{{ $page['slug'] }}" @selected(old('topic', $topic) === $page['slug'])>{{ $page['title'] }}</option>
                    @endforeach
                </flux:select>
                <flux:textarea name="proposal" rows="9" :label="__('docs.contribute.proposal')" :placeholder="__('docs.contribute.proposal_hint')" required>{{ old('proposal') }}</flux:textarea>
            </fieldset>

            <fieldset class="space-y-3">
                <flux:checkbox name="may_credit" value="1" :label="__('docs.contribute.may_credit')" :checked="old('may_credit')" />
                <flux:checkbox name="agree" value="1" :label="__('docs.contribute.agree')" :checked="old('agree')" required />
                <p class="text-sm leading-6 text-zinc-500">{{ __('docs.contribute.privacy') }}</p>
            </fieldset>

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 p-4 text-sm text-red-900 dark:bg-red-950 dark:text-red-100" role="alert" data-docs-errors>
                    <ul class="list-disc ps-5">
                        @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <flux:button type="submit" variant="primary">{{ __('docs.contribute.submit') }}</flux:button>
        </form>
    </main>
</x-docs.layout>
