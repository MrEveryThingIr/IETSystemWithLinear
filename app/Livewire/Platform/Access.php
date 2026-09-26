<?php

namespace App\Livewire\Platform;

use App\Actions\Platform\RequestPlatformAccess;
use App\Actions\Platform\ReviewPlatformAccessRequest;
use App\Models\PlatformAccessRequest;
use App\Models\User;
use App\PlatformCapability;
use App\PlatformRole;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Access extends Component
{
    public string $requestReason = '';

    /** @var array<int, string> */
    public array $reviewNotes = [];

    public function requestGroupCreation(RequestPlatformAccess $requestAccess): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->validate([
            'requestReason' => ['nullable', 'string', 'max:2000'],
        ]);

        $requestAccess->execute(
            $user,
            PlatformRole::GroupCreator,
            filled($data['requestReason']) ? $data['requestReason'] : null,
        );

        $this->reset('requestReason');
        session()->flash('status', __('ui.platform_access.messages.submitted'));
    }

    public function review(int $requestId, bool $approved, ReviewPlatformAccessRequest $reviewAccess): void
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->hasPlatformCapability(PlatformCapability::ManagePlatformAccess), 403);

        $data = $this->validate([
            "reviewNotes.{$requestId}" => ['nullable', 'string', 'max:2000'],
        ]);

        $accessRequest = PlatformAccessRequest::query()->where('status', 'pending')->findOrFail($requestId);
        $reviewAccess->execute($accessRequest, $user, $approved, $data['reviewNotes'][$requestId] ?? null);

        unset($this->reviewNotes[$requestId]);
        session()->flash('status', $approved
            ? __('ui.platform_access.messages.approved')
            : __('ui.platform_access.messages.rejected'));
    }

    public function render(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $canCreateGroups = $user->hasPlatformCapability(PlatformCapability::CreateGroups);
        $canManagePlatformAccess = $user->hasPlatformCapability(PlatformCapability::ManagePlatformAccess);

        $myRequests = PlatformAccessRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $pendingRequests = $canManagePlatformAccess
            ? PlatformAccessRequest::query()
                ->where('status', 'pending')
                ->where('user_id', '!=', $user->id)
                ->with('user')
                ->oldest()
                ->get()
            : collect();

        return view('livewire.platform.access', compact(
            'canCreateGroups',
            'canManagePlatformAccess',
            'myRequests',
            'pendingRequests',
        ))->title(__('ui.platform_access.title'));
    }
}
