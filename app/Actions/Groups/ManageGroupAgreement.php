<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use Illuminate\Support\Facades\DB;

class ManageGroupAgreement
{
    public function create(Group $group, Actor $actor, string $name, bool $required, string $content): GroupAgreement
    {
        return DB::transaction(function () use ($group, $actor, $name, $required, $content): GroupAgreement {
            $agreement = GroupAgreement::create(['group_id' => $group->id, 'name' => $name, 'required_for_admission' => $required]);
            $agreement->versions()->create(['version' => 1, 'content' => $content, 'status' => 'draft', 'created_by_actor_id' => $actor->id]);
            return $agreement;
        });
    }
    public function revise(GroupAgreement $agreement, Actor $actor, string $content): GroupAgreementVersion
    {
        return DB::transaction(function () use ($agreement, $actor, $content): GroupAgreementVersion {
            $next = ((int) $agreement->versions()->lockForUpdate()->max('version')) + 1;
            return $agreement->versions()->create(['version' => $next, 'content' => $content, 'status' => 'draft', 'created_by_actor_id' => $actor->id]);
        });
    }
    public function schedule(GroupAgreementVersion $version, ?\DateTimeInterface $from, ?\DateTimeInterface $until): void
    {
        abort_if(in_array($version->status, ['active', 'superseded'], true), 422, 'Published versions cannot be edited.');
        $version->update(['status' => $from !== null && $from->isFuture() ? 'scheduled' : 'active', 'effective_from' => $from, 'effective_until' => $until]);
        if ($version->status === 'active') $version->agreement->versions()->whereKeyNot($version->id)->where('status', 'active')->update(['status' => 'superseded', 'effective_until' => now()]);
    }
}
