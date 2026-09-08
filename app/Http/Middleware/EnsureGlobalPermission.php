<?php

namespace App\Http\Middleware;

use App\Actions\Administration\GlobalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGlobalPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user !== null && app(GlobalAccess::class)->can($user, $permission), 403);

        return $next($request);
    }
}
