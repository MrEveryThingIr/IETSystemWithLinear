<?php

namespace App\Http\Controllers;

use App\Actions\Groups\RedeemGroupInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupInvitationController
{
    public function show(Request $request, string $token, RedeemGroupInvitation $redemption): View
    {
        $invitation = $redemption->preview($token);

        if ($request->user() !== null && ! $request->user()->hasVerifiedEmail()) {
            $request->session()->put('url.intended', route('invitations.show', ['token' => $invitation->token]));
        }

        return view('invitations.show', compact('invitation'));
    }

    public function accept(Request $request, string $token, RedeemGroupInvitation $redemption): RedirectResponse
    {
        $admission = $redemption->execute($token, $request->user()->actor, $request->user()->email);

        return to_route('admissions.show', $admission)->with('status', __('ui.messages.admission_created'));
    }
}
