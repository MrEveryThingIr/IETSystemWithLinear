@props(['position' => null])
@if ($position && $position['status'] === 'confirmed')
    <details class="text-xs text-zinc-500" data-credit-history>
        <summary>{{ __('credit.history') }}</summary>
        <div>{{ __('credit.applied') }}: {{ \App\Support\Bookkeeping\Toman::format($position['applied']) }}</div>
        <div>{{ __('credit.available') }}: {{ \App\Support\Bookkeeping\Toman::format($position['available']) }}</div>
        <div>{{ __('credit.reserved') }}: {{ \App\Support\Bookkeeping\Toman::format($position['reserved']) }}</div>
        <div>{{ __('credit.returned') }}: {{ \App\Support\Bookkeeping\Toman::format($position['returned']) }}</div>
        @foreach ($position['application_sources'] as $application)
            <div>{{ __('credit.obligation') }}: {{ $application['obligation'] }} · {{ \App\Support\Bookkeeping\Toman::format($application['amount']) }}</div>
        @endforeach
        <div>{{ __('credit.not_debt') }}</div>
    </details>
@endif
