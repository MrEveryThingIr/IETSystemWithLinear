<?php

namespace App\Http\Controllers;

use App\Actions\Contexts\EnsureAdmissionContext;
use App\Models\Admission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdmissionContextCollaborationController extends Controller
{
    public function conversation(
        Request $request,
        Admission $admission,
        EnsureAdmissionContext $contexts,
    ): RedirectResponse {
        return $this->redirect($request, $admission, $contexts, 'contexts.conversation');
    }

    public function timeline(
        Request $request,
        Admission $admission,
        EnsureAdmissionContext $contexts,
    ): RedirectResponse {
        return $this->redirect($request, $admission, $contexts, 'contexts.timeline');
    }

    private function redirect(
        Request $request,
        Admission $admission,
        EnsureAdmissionContext $contexts,
        string $route,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $context = $contexts->execute($admission, $user);

        return redirect()->route($route, $context);
    }
}
