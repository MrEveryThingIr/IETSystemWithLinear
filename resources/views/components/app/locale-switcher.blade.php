@php($locales = \App\Support\Localization::supported())

<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" size="sm" icon="language" icon:trailing="chevron-down" :aria-label="__('ui.language')">
        <span>{{ $locales[app()->getLocale()]['native_name'] ?? strtoupper(app()->getLocale()) }}</span>
    </flux:button>
    <flux:menu class="min-w-44">
        @foreach ($locales as $code => $locale)
            <form method="POST" action="{{ route('locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $code }}">
                <flux:menu.item type="submit" :icon="$code === app()->getLocale() ? 'check' : null">
                    <span lang="{{ str_replace('_', '-', $code) }}" dir="{{ $locale['direction'] }}">{{ $locale['native_name'] }}</span>
                </flux:menu.item>
            </form>
        @endforeach
    </flux:menu>
</flux:dropdown>
