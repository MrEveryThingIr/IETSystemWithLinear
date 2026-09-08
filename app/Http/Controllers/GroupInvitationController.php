<?php

namespace App\Http\Controllers;

use App\Actions\Groups\GroupRoleProvisioner;
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

        return view('invitations.show', compact('invitation'));
    }

    public function accept(Request $request, string $token, GroupRoleProvisioner $groupRoles): RedirectResponse
    {
        $invitation = $this->findUsableInvitation($token);
        $actor = $request->user()->actor;

        if ($invitation->email !== null && $invitation->email !== $request->user()->email) {
            return back()->with('error', 'This invitation is reserved for a different email address.');
        }

        DB::transaction(function () use ($invitation, $actor, $groupRoles): void {
            $invitation = GroupInvitation::query()->with('group')->lockForUpdate()->findOrFail($invitation->id);
            abort_unless($this->isUsable($invitation), 404);
            $acceptance = $invitation->acceptances()->firstOrCreate(['accepted_by_actor_id' => $actor->id], ['accepted_at' => now()]);
            if ($acceptance->wasRecentlyCreated) {
                $invitation->increment('uses_count');
            }
            $roles = $groupRoles->provision($invitation->group);
            $groupRoles->assign($actor, $invitation->group, $roles['member']);
            $invitation->group->memberships()->firstOrCreate(['actor_id' => $actor->id], ['status' => 'active']);
        });

        return to_route('groups.show', $invitation->group)->with('status', "You joined {$invitation->group->name} as a Member.");
    }

    private function findUsableInvitation(string $token): GroupInvitation
    {
        return GroupInvitation::query()->with('group')->where('token', $token)->whereNull('revoked_at')->where(function ($query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->where(function ($query): void {
            $query->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses');
        })->firstOrFail();
    }

    private function isUsable(GroupInvitation $invitation): bool
    {
        return $invitation->revoked_at === null && ($invitation->expires_at === null || $invitation->expires_at->isFuture()) && ($invitation->max_uses === null || $invitation->uses_count < $invitation->max_uses);
    }
}
