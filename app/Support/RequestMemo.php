<?php

namespace App\Support;

use App\Models\Actor;
use App\Models\FeatureSurfaceGrant;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use Closure;

/**
 * Remembers read-only lookups that one request repeats many times (the
 * current user with their actor, superadmin and facility checks), so a page
 * listing fifty items does not run the same queries fifty times.
 *
 * Emptied at the start of every request and whenever a user, actor or
 * access grant is written, so authorization never works on stale data.
 */
final class RequestMemo
{
    /** @var array<string, mixed> */
    private static array $values = [];

    public static function remember(string $key, Closure $callback): mixed
    {
        if (! array_key_exists($key, self::$values)) {
            self::$values[$key] = $callback();
        }

        return self::$values[$key];
    }

    public static function flush(): void
    {
        self::$values = [];
    }

    /** The user as stored now, with their actor (one query per request). */
    public static function user(User|int $user): ?User
    {
        $id = $user instanceof User ? (int) $user->getKey() : $user;

        return self::remember('user:'.$id, fn (): ?User => User::query()->with('actor')->find($id));
    }

    /** @return list<class-string> */
    public static function invalidatingModels(): array
    {
        return [User::class, Actor::class, FeatureSurfaceGrant::class, PlatformAccessGrant::class];
    }
}
