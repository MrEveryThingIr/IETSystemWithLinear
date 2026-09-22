<?php

namespace App\Http\Controllers;

use App\Support\Localization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(Localization::codes())],
            'return_to' => ['nullable', 'string', 'max:4096'],
        ]);
        $locale = $data['locale'];

        $request->session()->put((string) config('localization.session_key', 'locale'), $locale);

        if ($request->user() !== null) {
            $request->user()->locale = $locale;
            $request->user()->save();
        }

        $returnTo = $this->safeReturnPath($data['return_to'] ?? null);

        return $returnTo !== null ? redirect($returnTo) : back();
    }

    private function safeReturnPath(?string $returnTo): ?string
    {
        if ($returnTo === null
            || ! str_starts_with($returnTo, '/')
            || str_starts_with($returnTo, '//')
            || str_contains($returnTo, '\\')
            || str_contains($returnTo, "\r")
            || str_contains($returnTo, "\n")) {
            return null;
        }

        return $returnTo;
    }
}
