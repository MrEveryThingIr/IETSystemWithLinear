<?php

namespace App\Livewire\Platform;

use App\Models\Actor;
use App\Models\MessageReport;
use App\Models\User;
use App\PlatformCapability;
use App\Support\RequestMemo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Platform administrators review reported people and messages. */
#[Layout('layouts.app')]
class MessageReports extends Component
{
    use WithPagination;

    public string $status = 'open';

    public function mount(): void
    {
        $this->authorizeReview();
    }

    public function markReviewed(string $uuid): void
    {
        $this->authorizeReview();
        $actor = RequestMemo::user($this->user())?->actor;
        abort_unless($actor instanceof Actor, 403);
        MessageReport::query()->where('uuid', $uuid)->where('status', 'open')
            ->update(['status' => 'reviewed', 'reviewed_by_actor_id' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now()]);
    }

    public function render(): View
    {
        $this->authorizeReview();

        return view('livewire.platform.message-reports', [
            'reports' => MessageReport::query()
                ->where('status', $this->status === 'reviewed' ? 'reviewed' : 'open')
                ->with(['reporter.user', 'reported.user', 'message.latestRevision'])
                ->latest('id')
                ->paginate(30),
        ])->title(__('messages.reports_admin.title'));
    }

    private function authorizeReview(): void
    {
        abort_unless($this->user()->hasPlatformCapability(PlatformCapability::ManageUsers), 403);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
