<?php

namespace App\Livewire\Planner\Meetings;

use App\Actions\Planner\Meetings\MeetingScheduler;
use App\Models\Planner\MeetingInvitee;
use App\Models\Planner\MeetingSlot;
use App\Models\Planner\MeetingVote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

/** The invitee's answer form, shared by the signed-in and the guest page. */
trait RespondsToMeeting
{
    /** @var array<string, string> slot uuid => yes|maybe|no */
    public array $votes = [];

    public string $suggestDate = '';

    public string $suggestTime = '';

    public string $note = '';

    public bool $suggesting = false;

    public string $viewerTimezone = 'UTC';

    abstract protected function respondingInvitee(): MeetingInvitee;

    protected function loadVotes(MeetingInvitee $invitee): void
    {
        $this->votes = MeetingVote::query()
            ->where('meeting_invitee_id', $invitee->id)
            ->with('slot')
            ->get()
            ->filter(fn (MeetingVote $vote): bool => $vote->slot instanceof MeetingSlot)
            ->mapWithKeys(fn (MeetingVote $vote): array => [$vote->slot->uuid => $vote->answer])
            ->all();
        $this->note = (string) $invitee->note;
    }

    public function vote(string $slotUuid, string $answer): void
    {
        abort_unless(in_array($answer, MeetingVote::ANSWERS, true), 422);
        $this->votes[$slotUuid] = $answer;
    }

    public function setViewerTimezone(string $timezone): void
    {
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            $this->viewerTimezone = $timezone;
        }
    }

    public function sendResponse(MeetingScheduler $meetings): void
    {
        $suggest = null;
        if ($this->suggesting && ($this->suggestDate !== '' || $this->suggestTime !== '')) {
            Validator::make(
                ['suggestDate' => $this->suggestDate, 'suggestTime' => $this->suggestTime],
                ['suggestDate' => ['required', 'date_format:Y-m-d'], 'suggestTime' => ['required', 'date_format:H:i']],
            )->validate();
            $suggest = CarbonImmutable::createFromFormat('Y-m-d H:i', $this->suggestDate.' '.$this->suggestTime, $this->viewerTimezone);
        }

        $meetings->respond($this->respondingInvitee(), $this->votes, $suggest instanceof CarbonImmutable ? $suggest : null, $this->note);
        $this->reset('suggestDate', 'suggestTime', 'suggesting');
        session()->flash('status', __('planner.meetings.response_sent'));
    }

    public function decline(MeetingScheduler $meetings): void
    {
        $meetings->respond($this->respondingInvitee(), [], null, $this->note, decline: true);
        session()->flash('status', __('planner.meetings.declined'));
    }
}
