<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class RegisterUser
{
    /** @param array<string, mixed> $input */
    public function handle(array $input): User
    {
        $data = Validator::make($input, [
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ])->validate();

        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->actor()->create([]);

            DB::afterCommit(fn () => event(new Registered($user)));

            return $user;
        });
    }
}
