<?php

namespace App\Http\Middleware;

use App\Services\Surfaces\FeatureSurfaceAccess;
use App\Services\Surfaces\FeatureSurfaceRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMappedFeatureSurface
{
    public function __construct(
        private readonly FeatureSurfaceRegistry $registry,
        private readonly FeatureSurfaceAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Surface revelation is not authentication/account verification.
        //
        // Let the route's own auth / account.active / verified middleware
        // produce the canonical redirect or logout response. This also keeps
        // intentionally-public mapped endpoints reachable by guests.
        if (
            $user === null
            || $user->status !== 'active'
            || $user->email_verified_at === null
        ) {
            return $next($request);
        }

        $surfaceKey = $this->registry->routeSurface(
            $request->route()?->getName()
        );

        if ($surfaceKey !== null) {
            abort_unless(
                $this->access->allows($user, $surfaceKey),
                403
            );
        }

        return $next($request);
    }
}
