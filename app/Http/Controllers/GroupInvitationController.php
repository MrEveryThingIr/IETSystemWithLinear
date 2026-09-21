<?php

namespace App\Http\Controllers;

use App\Actions\Groups\RedeemGroupInvitation;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GroupInvitationController
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->findInvitation($token);
        $user = $request->user();
        $actor = $user instanceof User ? $user->actor : null;
        $membership = $actor instanceof Actor
            ? GroupMembership::query()->where('group_id', $invitation->group_id)->where('actor_id', $actor->id)->whereIn('status', ['active', 'suspended'])->first()
            : null;
        $admission = $actor instanceof Actor
            ? Admission::query()->where('open_key', Admission::openKey($invitation->group_id, $actor->id))->first()
            : null;
        $targetMismatch = $user instanceof User && $invitation->email !== null
            && strcasecmp($invitation->email, $user->email) !== 0;
        $state = $this->state($invitation);

        if ($user instanceof User && ! $user->hasVerifiedEmail() && $state === 'available') {
            $request->session()->put('url.intended', route('invitations.show', ['token' => $token]));
        }

        return response()->view('invitations.show', compact('invitation', 'token', 'state', 'membership', 'admission', 'targetMismatch'))
            ->withHeaders(['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function accept(Request $request, string $token, RedeemGroupInvitation $redemption): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        if (! $user->actor instanceof Actor) {
            return to_route('invitations.show', ['token' => $token])->with('error', __('ui.invitation.identity_recovery'));
        }

        $invitation = $this->findInvitation($token);
        if ($this->state($invitation) !== 'available') {
            return to_route('invitations.show', ['token' => $token]);
        }

        $admission = $redemption->execute($token, $user->actor, $user->email);

        return to_route('admissions.show', $admission)->with('status', __('ui.messages.admission_created'));
    }

    private function findInvitation(string $token): GroupInvitation
    {
        return GroupInvitation::query()
            ->with(['group', 'inviter.user'])
            ->where('token', GroupInvitation::hashToken($token))
            ->firstOrFail();
    }

    private function state(GroupInvitation $invitation): string
    {
        return match (true) {
            $invitation->revoked_at !== null => 'revoked',
            $invitation->expires_at?->isPast() === true => 'expired',
            $invitation->max_uses !== null && $invitation->uses_count >= $invitation->max_uses => 'exhausted',
            default => 'available',
        };
    }
}
