<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\ManageGroupAgreement;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AcceptAgreements extends Component
{
    public Group $group;

    public function mount(Group $group): void
    {
        abort_unless($this->membership($group) !== null, 403);
        $this->group = $group;
    }

    public function accept(int $versionId, ManageGroupAgreement $manager): void
    {
        $membership = $this->membership($this->group);
        abort_unless($membership !== null, 403);
        /** @var GroupAgreementVersion $version */
        $version = GroupAgreementVersion::query()->whereHas('agreement', fn ($query) => $query->where('group_id', $this->group->id))->findOrFail($versionId);
        $manager->acceptMembership($membership, $version, $this->actor());
    }

    public function render(): View
    {
        $membership = $this->membership($this->group);
        $acceptedIds = $membership?->agreementAcceptances()->pluck('group_agreement_version_id') ?? collect();
        $versions = GroupAgreementVersion::query()->with('agreement')->whereHas('agreement', fn ($query) => $query->where('group_id', $this->group->id))->where('status', 'active')->where('reacceptance_required', true)->where('effective_from', '<=', now())->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', now()))->whereNotIn('id', $acceptedIds)->get();

        return view('livewire.groups.accept-agreements', ['versions' => $versions]);
    }

    private function membership(Group $group): ?GroupMembership
    {
        return GroupMembership::query()->where('group_id', $group->id)->where('actor_id', auth()->user()?->actor?->id)->where('status', 'active')->first();
    }

    private function actor(): Actor
    {
        return Actor::query()->where('user_id', auth()->id())->firstOrFail();
    }
}
