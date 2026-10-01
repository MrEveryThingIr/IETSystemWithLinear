<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated
 *
 * Historical release profiles used this middleware as a hard-coded route
 * whitelist. That conflicts with IET's per-user publication graph.
 *
 * It intentionally remains as a no-op compatibility class so older references
 * do not crash while the repository is consolidated, but it is no longer an
 * access-control authority.
 *
 * Canonical revelation is handled by:
 * - FeatureSurfaceRegistry
 * - FeatureSurfaceGrantService
 * - EnforceMappedFeatureSurface
 * - RequireFeatureSurface
 */
class EnforceReleaseSurface
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
