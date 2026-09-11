<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RegisterInvitedUser;
use App\Actions\Groups\RedeemGroupInvitation;
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
    public string $invitationToken = '';

    public string $groupName = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token, RedeemGroupInvitation $redemption): void
    {
        $invitation = $redemption->preview($token);
        $this->invitationToken = $token;
        $this->groupName = $invitation->group->name;
        $this->email = $invitation->email ?? '';
    }

    public function register(RegisterInvitedUser $register): void
    {
        $key = 'register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('ui.messages.too_many_attempts')]);
        }
        RateLimiter::hit($key, 60);

        [$user, $admission] = $register->handle(
            $this->only(['username', 'email', 'password', 'password_confirmation']),
            $this->invitationToken,
        );
        Auth::login($user);
        session()->regenerate();
        session()->put('url.intended', route('admissions.show', $admission));
        $this->reset('password', 'password_confirmation');
        $this->redirectRoute('verification.notice');
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
