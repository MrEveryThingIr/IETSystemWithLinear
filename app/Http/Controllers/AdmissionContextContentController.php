<?php

namespace App\Http\Controllers;

use App\Actions\Contexts\EnsureAdmissionContext;
use App\Models\Admission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdmissionContextContentController extends Controller
{
    public function __invoke(
        Request $request,
        Admission $admission,
        EnsureAdmissionContext $contexts,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = $contexts->execute($admission, $user);

        return redirect()->route('contexts.contents.index', $context);
    }
}
