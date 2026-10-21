<?php

namespace App\Livewire\Planner\Meetings;

use App\Actions\Planner\Meetings\MeetingScheduler;
use App\Models\Actor;
use App\Models\Planner\MeetingInvitee;
use App\Models\Planner\MeetingRequest;
use App\Models\User;
use App\Support\TemporalPreferences;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** A meeting request for its organizer (pick the time) or a user invitee (answer). */
#[Layout('layouts.app')]
class Show extends Component
{
    use RespondsToMeeting;

    public MeetingRequest $meeting;

    public function mount(MeetingRequest $meeting): void
    {
        $this->meeting = $meeting;
        abort_unless($this->isOrganizer() || $this->invitee() instanceof MeetingInvitee, 403);
        $this->viewerTimezone = TemporalPreferences::timezoneFor($this->user());
        if ($this->invitee() instanceof MeetingInvitee) {
            $this->loadVotes($this->invitee());
        }
    }

    public function choose(string $slotUuid, MeetingScheduler $meetings): void
    {
        $this->meeting = $meetings->finalize($this->meeting, $this->user(), $slotUuid);
        session()->flash('status', __('planner.meetings.agreed_flash'));
    }

    public function cancelMeeting(MeetingScheduler $meetings): void
    {
        $this->meeting = $meetings->cancel($this->meeting, $this->user());
        session()->flash('status', __('planner.meetings.cancelled_flash'));
    }

    protected function respondingInvitee(): MeetingInvitee
    {
        $invitee = $this->invitee();
        abort_unless($invitee instanceof MeetingInvitee, 403);

        return $invitee;
    }

    public function render(): View
    {
        $meeting = $this->meeting->fresh(['organizer.user', 'organizer.profile', 'slots.votes', 'slots.suggestedBy', 'invitees.actor.user', 'invitees.votes', 'finalSlot', 'organizerPlan']);
        abort_unless($meeting instanceof MeetingRequest, 404);
        $this->meeting = $meeting;
        $invitee = $this->invitee();

        return view('livewire.planner.meetings.show', [
            'isOrganizer' => $this->isOrganizer(),
            'invitee' => $invitee,
            'myPlan' => $this->isOrganizer() ? $meeting->organizerPlan : $invitee?->plan,
            'links' => $this->isOrganizer()
                ? $meeting->invitees->filter->isGuest()->mapWithKeys(fn (MeetingInvitee $guest): array => [$guest->uuid => ($token = $guest->token()) ? route('meetings.guest', $token) : null])->filter()
                : collect(),
        ])->title($meeting->title);
    }

    private function isOrganizer(): bool
    {
        return (int) $this->meeting->organizer_actor_id === (int) $this->user()->actor?->id;
    }

    private function invitee(): ?MeetingInvitee
    {
        $actorId = $this->user()->actor?->id;

        return $actorId === null ? null : $this->meeting->invitees()->where('actor_id', $actorId)->first();
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
