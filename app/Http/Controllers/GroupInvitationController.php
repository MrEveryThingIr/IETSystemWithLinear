<?php

namespace App\Http\Controllers;

use App\Actions\Groups\RedeemGroupInvitation;
use App\Models\GroupInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupInvitationController
{
    public function show(Request $request, string $token): View
    {
        $invitation = $this->findUsableInvitation($token);
        $request->session()->put('url.intended', route('invitations.show', ['token' => $invitation->token]));

        return view('invitations.show', compact('invitation'));
    }

    public function accept(Request $request, string $token, RedeemGroupInvitation $redemption): RedirectResponse
    {
        $admission = $redemption->execute($token, $request->user()->actor, $request->user()->email);

        return to_route('admissions.show', $admission)->with('status', 'Your admission application has been created.');
    }

    private function findUsableInvitation(string $token): GroupInvitation
    {
        return GroupInvitation::query()->with('group')->where('token', $token)->whereNull('revoked_at')->where(function ($query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->where(function ($query): void {
            $query->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses');
        })->firstOrFail();
    }
}
