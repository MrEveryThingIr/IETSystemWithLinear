<?php

namespace App\Livewire\Planner\Meetings;

use App\Actions\Planner\Meetings\MeetingScheduler;
use App\Models\Planner\MeetingInvitee;
use App\Models\Planner\MeetingRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The private link a guest receives. Signed-in people are taken to the
 * meeting in their planner (and the invitation becomes theirs).
 */
#[Layout('layouts.public')]
class GuestRespond extends Component
{
    use RespondsToMeeting;

    #[Locked]
    public string $token = '';

    public function mount(string $token, MeetingScheduler $meetings): mixed
    {
        $invitee = MeetingInvitee::findByToken($token);
        abort_unless($invitee instanceof MeetingInvitee, 404);
        $this->token = $token;

        $user = auth()->user();
        if ($user instanceof User && $user->actor !== null && $user->email_verified_at !== null) {
            $meeting = $invitee->meeting;
            if ((int) $meeting->organizer_actor_id !== (int) $user->actor->id) {
                $meetings->claim($invitee, $user);
            }

            return $this->redirectRoute('planner.meetings.show', $meeting);
        }

        $invitee->forceFill(['last_viewed_at' => now()])->save();
        $this->viewerTimezone = $invitee->meeting->timezone;
        $this->loadVotes($invitee);

        return null;
    }

    protected function respondingInvitee(): MeetingInvitee
    {
        $invitee = MeetingInvitee::findByToken($this->token);
        abort_unless($invitee instanceof MeetingInvitee, 404);

        return $invitee;
    }

    public function render(): View
    {
        $invitee = $this->respondingInvitee();
        $meeting = MeetingRequest::query()
            ->with(['organizer.user', 'organizer.profile', 'slots.votes', 'invitees', 'finalSlot'])
            ->findOrFail($invitee->meeting_request_id);

        return view('livewire.planner.meetings.guest', [
            'meeting' => $meeting,
            'invitee' => $invitee,
        ])->title($meeting->title);
    }
}
