<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\GroupMembership;

class RemoveGroupMember
{
    public function __construct(private TransitionGroupMembership $memberships) {}

    public function handle(GroupMembership $membership, ?Actor $actingActor = null, string $reason = 'Removed by an authorized Group manager.'): void
    {
        $this->memberships->remove($membership, $actingActor, $reason);
    }
}
