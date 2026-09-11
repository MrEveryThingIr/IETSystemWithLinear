<?php

namespace Database\Factories\Farsi;

use App\Models\User;
use Database\Factories\UserFactory as BaseUserFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends BaseUserFactory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake('fa_IR')->unique()->firstName().' '.fake('fa_IR')->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'locale' => 'fa',
        ];
    }
}
