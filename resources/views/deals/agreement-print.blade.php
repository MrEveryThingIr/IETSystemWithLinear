<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa', 'ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $terms['title'] ?? __('deals.agreement') }} — {{ __('deals.print.title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background: #fff; color: #111; }
        .doc { max-width: 46rem; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        .doc h1 { font-size: 1.5rem; font-weight: 700; }
        .doc table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .doc th, .doc td { text-align: start; padding: .35rem .5rem; border-bottom: 1px solid #ddd; vertical-align: top; }
        .doc .muted { color: #555; font-size: .8rem; }
        @media print { .no-print { display: none !important; } .doc { padding: 0; } a { color: inherit; text-decoration: none; } }
    </style>
</head>
<body>
<main class="doc space-y-6" data-agreement-print>
    <div class="no-print flex flex-wrap items-center justify-between gap-2">
        <a href="{{ \App\Support\Deals\DealLinks::workspace($relationship, 'agreement') }}" class="text-sm underline">{{ __('deals.print.back') }}</a>
        <button type="button" onclick="window.print()" class="rounded border px-3 py-1.5 text-sm">{{ __('deals.print.print') }}</button>
    </div>

    <header class="space-y-1">
        <div class="muted">{{ __('deals.print.executed') }}</div>
        <h1 dir="auto">{{ $terms['title'] ?? $relationship->title ?? __('deals.agreement') }}</h1>
        @if (! empty($terms['summary']))<p dir="auto">{{ $terms['summary'] }}</p>@endif
        <p class="muted">
            {{ __('deals.print.reference', ['ref' => strtoupper(substr($contract->uuid, 0, 8))]) }} ·
            {{ __('deals.agreement_version', ['version' => $version->version]) }}
            @php($hash = $version->terms_hash ?? $source->terms_hash)
            @if ($hash) · {{ __('deals.print.fingerprint', ['hash' => substr($hash, 0, 16)]) }}@endif
            @if ($version->effective_from) · {{ __('deals.print.effective') }} <x-app.local-datetime :value="$version->effective_from" />@endif
        </p>
        @if ($originTitle)<p class="muted" dir="auto">{{ __('deals.print.origin', ['title' => $originTitle, 'version' => $originVersion]) }}</p>@endif
    </header>

    <section>
        <h2 class="font-semibold">{{ __('deals.print.parties') }}</h2>
        <table>
            <thead><tr><th>{{ __('deals.print.party') }}</th><th>{{ __('deals.print.signed_by') }}</th><th>{{ __('deals.print.when') }}</th></tr></thead>
            <tbody>
            @foreach ($parties as $party)
                <tr data-print-party="{{ $party->principal_key }}">
                    <td dir="auto">{{ $party->business?->name ?? $party->group?->name ?? $party->actor?->user?->username }}<div class="muted">{{ $party->role }}</div></td>
                    <td dir="auto">{{ $party->acceptance?->acceptedByUser?->username ?? '—' }}</td>
                    <td>@if ($party->acceptance)<x-app.local-datetime :value="$party->acceptance->accepted_at" />@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section>
        <h2 class="font-semibold">{{ __('deals.print.terms') }}</h2>
        @if ($legacyText !== null)
            <div class="whitespace-pre-wrap" dir="auto">{{ $legacyText }}</div>
        @else
            <x-deals.document :sections="$sections" />
        @endif
    </section>

    @if ($agreementVersions->count() > 1)
        <section data-print-agreement-versions>
            <h2 class="font-semibold">{{ __('deals.agreement_versions') }}</h2>
            <table><tbody>
                @foreach ($agreementVersions as $cv)
                    <tr><td>{{ __('deals.agreement_version', ['version' => $cv->version]) }} @if ($cv->id === $version->id)<strong>· {{ __('deals.print.in_force') }}</strong>@endif</td><td>{{ __('contracts.version_status.'.$cv->status->value) }}</td><td><x-app.local-datetime :value="$cv->effective_from" /></td></tr>
                @endforeach
            </tbody></table>
        </section>
    @endif

    <section>
        <h2 class="font-semibold">{{ __('deals.print.versions') }}</h2>
        <table>
            <tbody>
            @foreach ($versions as $v)
                <tr><td>{{ __('deals.terms_card', ['version' => $v->version]) }} @if ($v->id === $source->id)<strong>· {{ __('deals.print.accepted_version') }}</strong>@endif</td>
                    <td dir="auto">{{ $v->proposedBy?->user?->username }}</td><td><x-app.local-datetime :value="$v->proposed_at" /></td></tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <footer class="muted">{{ __('deals.print.footer', ['date' => now()->toDateString()]) }}</footer>
</main>
</body>
</html>
