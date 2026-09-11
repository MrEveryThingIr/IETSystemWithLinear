<?php

namespace App\Http\Middleware;

use App\Support\Localization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (! Localization::supports($locale)) {
            $sessionLocale = $request->session()->get((string) config('localization.session_key', 'locale'));
            $locale = is_string($sessionLocale) && Localization::supports($sessionLocale) ? $sessionLocale : null;
        }

        if ($locale === null) {
            $locale = $request->getPreferredLanguage(Localization::codes()) ?? (string) config('app.locale', 'en');
        }

        if (! Localization::supports($locale)) {
            $locale = (string) config('app.fallback_locale', 'en');
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', str_replace('_', '-', $locale));

        return $response;
    }
}
