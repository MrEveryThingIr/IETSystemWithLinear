<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RegisterUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Create your account')]
class Register extends Component
{
    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(RegisterUser $register): void
    {
        $key = 'register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again in a minute.']);
        }
        RateLimiter::hit($key, 60);

        $user = $register->handle($this->only(['username', 'email', 'password', 'password_confirmation']));
        Auth::login($user);
        session()->regenerate();
        $this->reset('password', 'password_confirmation');
        $this->redirectRoute('verification.notice');
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
