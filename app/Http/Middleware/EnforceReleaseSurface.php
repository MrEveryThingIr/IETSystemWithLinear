<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceReleaseSurface
{
    /**
     * The baseline is intentionally smaller than the source tree. Code for
     * later capabilities may remain available for selective re-admission, but
     * it is not a reachable product surface in this profile.
     *
     * @var list<string>
     */
    private const BASELINE_ROUTES = [
        'access-invitations.show',
        'access-invitations.register',
        'locale.update',
        'login',
        'password.request',
        'password.reset',
        'logout',
        'verification.notice',
        'verification.verify',
        'dashboard',
        'getting-started',
        'profile.edit',
        'profiles.images.show',
        'actors.avatar',
        'planner.*',
        'finance.*',
        'financial-obligations.*',
        'accounting.*',
        'vault.*',
        'platform.access',
        'platform.access-invitations',
        'actors.*',
        'livewire.*',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((string) config('release.profile') !== 'planning_baseline') {
            return $next($request);
        }

        // Livewire transports component actions/assets through framework
        // endpoints that may use a version/hash-prefixed path such as
        // "livewire-<hash>/update". These are infrastructure routes, not
        // product surfaces, and must remain reachable after the page route
        // itself has passed this release boundary.
        $name = $request->route()?->getName();

        if (
            $request->is('livewire/*')
            || $request->is('livewire-*/*')
            || (is_string($name) && Str::contains($name, 'livewire'))
        ) {
            return $next($request);
        }

        // The root landing page intentionally has no dependency on another
        // capability and remains the invitation-only entry point.
        if ($name === null && $request->is('/')) {
            return $next($request);
        }

        if (is_string($name) && Str::is(self::BASELINE_ROUTES, $name)) {
            return $next($request);
        }

        abort(404);
    }
}
