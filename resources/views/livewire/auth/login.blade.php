<section class="space-y-6">
    <h1 class="text-2xl font-semibold">Log in</h1>
    @if (session('status')) <p role="status">{{ session('status') }}</p> @endif
    <form wire:submit="login" class="space-y-4">
    <div>
        <label for="email" class="block font-medium">Email</label>
        <input id="email" type="email" wire:model="email" autocomplete="email" required class="mt-1 w-full rounded border px-3 py-2">
        @error('email') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="block font-medium">Password</label>
        <input id="password" type="password" wire:model="password" autocomplete="current-password" required class="mt-1 w-full rounded border px-3 py-2">
        @error('password') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
    </div>
<label class="flex gap-2"><input type="checkbox" wire:model="remember"> Remember me</label>
        <button type="submit" wire:loading.attr="disabled" class="rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50">Log in</button>
    </form>
    <nav class="flex gap-4"><a href="{{ route('password.request') }}" class="underline">Forgot password?</a><a href="{{ route('register') }}" class="underline">Create account</a></nav>
</section>
