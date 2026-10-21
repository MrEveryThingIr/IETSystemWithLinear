<?php

namespace App\Livewire\Business;

use App\Actions\Capabilities\CopyLegacyProfessions;
use App\Actions\Capabilities\SaveCapability;
use App\Models\BusinessMembership;
use App\Models\Capability;
use App\Models\User;
use App\Support\BusinessAccess;
use App\Support\Categories\CategoryCatalog;
use App\Support\Parties\Party;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A team member's skills in this business (capabilities "practised as a
 * member"). The member and the business's managers may maintain them.
 */
class MemberCapabilities extends Component
{
    #[Locked]
    public int $membershipId;

    public bool $adding = false;

    public ?int $conceptId = null;

    public function mount(BusinessMembership $membership, CopyLegacyProfessions $legacy): void
    {
        abort_unless(BusinessAccess::canOperate($this->user(), $membership->business()->firstOrFail()), 403);
        $legacy->ensureCopied();
        $this->membershipId = (int) $membership->id;
    }

    public function updatedConceptId(SaveCapability $save): void
    {
        if ($this->conceptId === null) {
            return;
        }
        $membership = $this->membership();
        $save->create($this->user(), Party::make(Party::ACTOR, (int) $membership->actor_id), [
            'concept_id' => $this->conceptId,
            'visibility' => 'members',
            'business_membership_id' => $membership->id,
        ]);
        $this->reset('conceptId', 'adding');
    }

    public function remove(string $uuid, SaveCapability $save): void
    {
        $capability = Capability::query()->where('business_membership_id', $this->membershipId)->where('uuid', $uuid)->firstOrFail();
        $save->archive($this->user(), $capability);
    }

    public function render(CategoryCatalog $catalog, SaveCapability $save): View
    {
        $membership = $this->membership();
        $capabilities = Capability::query()->where('business_membership_id', $membership->id)->active()->orderBy('sort_order')->get();

        return view('livewire.business.member-capabilities', [
            'capabilities' => $capabilities,
            'labels' => $catalog->labels($capabilities->pluck('concept_id')->map(fn ($id): int => (int) $id)->all()),
            'canEdit' => $save->mayMaintain($this->user(), Party::make(Party::ACTOR, (int) $membership->actor_id), (int) $membership->id),
            'businessOwner' => 'business:'.$membership->business_id,
        ]);
    }

    private function membership(): BusinessMembership
    {
        return BusinessMembership::query()->findOrFail($this->membershipId);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
