<?php

namespace App\Http\Controllers;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MyContextContentController extends Controller
{
    public function __invoke(Request $request, EnsurePersonalContext $contexts): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = $contexts->execute($user);

        return redirect()->route('contexts.contents.index', $context);
    }
}
