<?php

namespace Database\Factories;

use App\ContextKind;
use App\Models\Context;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Context> */
class ContextFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'kind' => ContextKind::Personal,
        ];
    }
}
