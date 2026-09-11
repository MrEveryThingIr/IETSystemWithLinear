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
        ]);
        $locale = $data['locale'];

        $request->session()->put((string) config('localization.session_key', 'locale'), $locale);

        if ($request->user() !== null) {
            $request->user()->locale = $locale;
            $request->user()->save();
        }

        return back();
    }
}
