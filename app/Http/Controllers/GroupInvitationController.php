<?php

namespace App\Http\Controllers;

use App\Models\GroupInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GroupInvitationController
{
    public function show(Request $request, string $token): View
    {
        $invitation = $this->findUsableInvitation($token);
        $request->session()->put('url.intended', route('invitations.show', ['token' => $invitation->token]));

        return view('invitations.show', ['invitation' => $invitation]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findUsableInvitation($token);
        $actor = $request->user()->actor;

        if ($invitation->email !== null && $invitation->email !== $request->user()->email) {
            return back()->with('error', 'This invitation is reserved for a different email address.');
        }

        DB::transaction(function () use ($invitation, $actor): void {
            $invitation->group->memberships()->firstOrCreate(
                ['actor_id' => $actor->id],
                ['role' => 'member', 'status' => 'active'],
            );

            $invitation->update([
                'accepted_at' => now(),
                'accepted_by_actor_id' => $actor->id,
            ]);
        });

        return to_route('groups.index')->with('status', "You joined {$invitation->group->name}.");
    }

    private function findUsableInvitation(string $token): GroupInvitation
    {
        return GroupInvitation::query()
            ->with('group')
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();
    }
}
