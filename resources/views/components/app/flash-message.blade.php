@foreach (['status' => 'success', 'success' => 'success', 'error' => 'danger'] as $key => $variant)
    @if (session()->has($key))
        <flux:callout :variant="$variant" :role="$variant === 'danger' ? 'alert' : 'status'">
            <flux:callout.text>{{ session($key) }}</flux:callout.text>
        </flux:callout>
    @endif
@endforeach
