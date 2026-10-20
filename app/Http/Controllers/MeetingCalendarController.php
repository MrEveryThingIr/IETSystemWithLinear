<?php

namespace App\Http\Controllers;

use App\Actions\Planner\Meetings\MeetingScheduler;
use App\Models\Planner\MeetingInvitee;
use App\Models\Planner\MeetingRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Calendar files for agreed meetings, and the guest's way to sign in. */
class MeetingCalendarController extends Controller
{
    public function member(Request $request, MeetingRequest $meeting, MeetingScheduler $meetings): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->actor !== null, 403);
        $actorId = (int) $user->actor->id;
        abort_unless(
            (int) $meeting->organizer_actor_id === $actorId || $meeting->invitees()->where('actor_id', $actorId)->exists(),
            403,
        );

        return $this->file($meeting, $meetings);
    }

    public function guest(string $token, MeetingScheduler $meetings): Response
    {
        $invitee = MeetingInvitee::findByToken($token);
        abort_unless($invitee instanceof MeetingInvitee, 404);

        return $this->file($invitee->meeting, $meetings);
    }

    /** After signing in, the link adds the meeting to the person's planner. */
    public function signIn(Request $request, string $token): RedirectResponse
    {
        abort_unless(MeetingInvitee::findByToken($token) instanceof MeetingInvitee, 404);
        $request->session()->put('url.intended', route('meetings.guest', $token));

        return redirect()->route('login');
    }

    private function file(MeetingRequest $meeting, MeetingScheduler $meetings): Response
    {
        abort_unless($meeting->status === MeetingRequest::STATUS_AGREED, 404);

        return response($meetings->ics($meeting->load('finalSlot')), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.(Str::slug($meeting->title) ?: 'meeting').'.ics"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
