<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\GroupInvitation;
use App\Models\GroupMembership;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemGroupInvitation
{
    public function preview(string $token): GroupInvitation
    {
        return GroupInvitation::query()
            ->with(['group', 'inviter.user'])
            ->where('token', GroupInvitation::hashToken($token))
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($query): void {
                $query->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses');
            })
            ->firstOrFail();
    }

    public function execute(string $token, Actor $actor, string $email): Admission
    {
        return DB::transaction(function () use ($token, $actor, $email): Admission {
            /** @var GroupInvitation $invitation */
            $invitation = GroupInvitation::query()->where('token', GroupInvitation::hashToken($token))->lockForUpdate()->firstOrFail();
            $expiresAt = $invitation->expires_at;
            $existingAcceptance = $invitation->acceptances()->where('accepted_by_actor_id', $actor->id)->first();
            abort_if(
                $invitation->revoked_at !== null
                || ($expiresAt instanceof CarbonInterface && $expiresAt->isPast())
                || ($existingAcceptance === null && $invitation->max_uses !== null && $invitation->uses_count >= $invitation->max_uses),
                404,
            );
            if ($invitation->email !== null && strcasecmp($invitation->email, $email) !== 0) {
                throw ValidationException::withMessages(['invitation' => __('ui.messages.invitation_reserved')]);
            }
            $hasCurrentMembership = GroupMembership::query()
                ->where('group_id', $invitation->group_id)
                ->where('actor_id', $actor->id)
                ->whereIn('status', ['active', 'suspended'])
                ->exists();
            abort_if($hasCurrentMembership, 422, 'Current Group members cannot create another admission.');

            $openKey = Admission::openKey($invitation->group_id, $actor->id);
            /** @var Admission|null $admission */
            $admission = Admission::query()->where('open_key', $openKey)->lockForUpdate()->first();
            abort_if($existingAcceptance !== null && $admission === null, 422, 'A new admission attempt requires a new invitation.');

            if ($existingAcceptance === null) {
                $invitation->acceptances()->create(['accepted_by_actor_id' => $actor->id, 'accepted_at' => now()]);
                $invitation->increment('uses_count');
            }
            if ($admission === null) {
                $admission = Admission::create(['group_id' => $invitation->group_id, 'candidate_actor_id' => $actor->id, 'source_invitation_id' => $invitation->id, 'status' => 'draft']);
                $admission->events()->create(['actor_id' => $actor->id, 'event' => 'admission.created_from_invitation', 'metadata' => ['invitation_id' => $invitation->id]]);
            }

            return $admission;
        });
    }
}
