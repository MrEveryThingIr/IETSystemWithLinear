<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', function (User $user, int $userId): bool {
    $current = User::query()->find($user->id);

    return $current instanceof User
        && (int) $current->id === $userId
        && $current->status === 'active'
        && $current->email_verified_at !== null;
});
