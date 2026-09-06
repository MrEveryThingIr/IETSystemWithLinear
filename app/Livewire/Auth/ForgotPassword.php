<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Forgot password')]
class ForgotPassword extends Component
{
    public string $email = '';

    public function sendResetLink(): void
    {
        $data = $this->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $key = 'password-email:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again in a minute.']);
        }
        RateLimiter::hit($key, 60);
        Password::sendResetLink($data);
        session()->flash('status', 'If an account matches that email, a password reset link will be sent.');
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
