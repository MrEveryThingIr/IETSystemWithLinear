<?php

namespace App\Http\Middleware;

use App\Services\Surfaces\FeatureSurfaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFeatureSurface
{
    public function __construct(
        private readonly FeatureSurfaceAccess $access,
    ) {}

    public function handle(Request $request, Closure $next, string $surfaceKey): Response
    {
        abort_unless(
            $this->access->allows($request->user(), $surfaceKey),
            403
        );

        return $next($request);
    }
}
