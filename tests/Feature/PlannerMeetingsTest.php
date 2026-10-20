<?php

namespace Tests\Feature;

use App\Actions\Auth\ProvisionVerifiedUserDefaults;
use App\Actions\Planner\Meetings\MeetingScheduler;
use App\Livewire\Home\Today;
use App\Livewire\Planner\Create as PlannerCreate;
use App\Livewire\Planner\Meetings\GuestRespond;
use App\Livewire\Planner\Meetings\Show as MeetingShow;
use App\Models\Actor;
use App\Models\NotificationOutbox;
use App\Models\Plan;
use App\Models\Planner\MeetingInvitee;
use App\Models\Planner\MeetingRequest;
use App\Models\Planner\MeetingSlot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PlannerMeetingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-21 06:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_organizer_offers_times_and_a_single_invitee_agrees_by_choosing_one(): void
    {
        $organizer = $this->person('safdar', 'Asia/Tehran');
        $sara = $this->person('sara', 'Europe/Berlin');

        $wizard = Livewire::actingAs($organizer->user)
            ->test(PlannerCreate::class)
            ->call('chooseKind', 'meeting')
            ->set('title', 'Coffee')
            ->call('next')
            ->call('addSlotsFromPicker', ['2026-10-23', '2026-10-24'], '17:00', 60)
            ->assertSet('meetingSlots', ['2026-10-23 17:00', '2026-10-24 17:00'])
            ->call('next')
            ->assertSet('step', 'who')
            ->set('inviteUsername', '@sara')
            ->call('addUserInvitee')
            ->set('location', 'Café Naderi')
            ->call('next')
            ->call('next')
            ->assertSet('step', 'review')
            ->call('save')
            ->assertHasNoErrors();

        $meeting = MeetingRequest::query()->with('slots')->sole();
        $wizard->assertRedirect(route('planner.meetings.show', $meeting));
        $this->assertSame(0, Plan::query()->count());
        $this->assertTrue(NotificationOutbox::query()->where('recipient_user_id', $sara->user->id)->where('kind', 'planner.meeting')->exists());

        // Sara sees it on Today, answers yes to the second option → agreed.
        Livewire::actingAs($sara->user)->test(Today::class)->assertSee(__('planner.meetings.today_waiting', ['organizer' => 'safdar', 'title' => 'Coffee']));
        $second = $meeting->slots[1];
        Livewire::actingAs($sara->user)
            ->test(MeetingShow::class, ['meeting' => $meeting])
            ->assertSeeHtml('data-meeting-respond')
            ->call('vote', $meeting->slots[0]->uuid, 'no')
            ->call('vote', $second->uuid, 'yes')
            ->call('sendResponse')
            ->assertSeeHtml('data-meeting-agreed');

        $meeting->refresh();
        $this->assertSame(MeetingRequest::STATUS_AGREED, $meeting->status);
        $this->assertSame($second->id, $meeting->final_slot_id);

        // Both planners have it, each in their own time.
        $mine = Plan::query()->whereKey($meeting->organizer_plan_id)->with('occurrences')->sole();
        $hers = Plan::query()->whereKey(MeetingInvitee::query()->sole()->plan_id)->with('occurrences')->sole();
        $this->assertSame('meeting', $mine->metadata['kind']);
        $this->assertSame($meeting->uuid, $hers->metadata['meeting_uuid']);
        $this->assertSame('2026-10-24 17:00', $mine->occurrences->sole()->scheduled_start_at->setTimezone('Asia/Tehran')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-24 15:30', $hers->occurrences->sole()->scheduled_start_at->setTimezone('Europe/Berlin')->format('Y-m-d H:i'));
        $this->assertStringContainsString('Café Naderi', (string) $hers->description);

        $this->actingAs($sara->user)->get(route('planner.meetings.ics', $meeting))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('DTSTART:20261024T133000Z', false);
        $stranger = $this->person('stranger');
        $this->actingAs($stranger->user)->get(route('planner.meetings.ics', $meeting))->assertForbidden();
    }

    public function test_guest_answers_by_link_suggests_a_time_and_the_organizer_chooses_it(): void
    {
        $organizer = $this->person('safdar', 'Asia/Tehran');
        $ali = $this->person('ali');
        $result = app(MeetingScheduler::class)->create($organizer->user, [
            'title' => 'Project kickoff',
            'duration_minutes' => 45,
            'timezone' => 'Asia/Tehran',
        ], [CarbonImmutable::parse('2026-10-25 10:00', 'Asia/Tehran')], [
            ['username' => 'ali'],
            ['name' => 'Reza', 'email' => 'reza@example.com'],
        ]);
        $meeting = $result['meeting'];
        $guestLink = array_values($result['links'])[0];
        $token = basename($guestLink);

        $this->get($guestLink)->assertOk()->assertSee('Project kickoff')->assertSee('data-meeting-respond', false);

        Livewire::test(GuestRespond::class, ['token' => $token])
            ->call('setViewerTimezone', 'Asia/Tehran')
            ->call('vote', $meeting->slots[0]->uuid, 'maybe')
            ->set('suggesting', true)
            ->set('suggestDate', '2026-10-26')
            ->set('suggestTime', '11:00')
            ->set('note', 'Monday works better')
            ->call('sendResponse')
            ->assertHasNoErrors();

        $suggested = MeetingSlot::query()->whereNotNull('suggested_by_invitee_id')->sole();
        $this->assertSame('2026-10-26 07:30', $suggested->starts_at->utc()->format('Y-m-d H:i'));
        $this->assertSame(MeetingRequest::STATUS_OPEN, $meeting->fresh()->status);

        Livewire::actingAs($organizer->user)
            ->test(MeetingShow::class, ['meeting' => $meeting])
            ->assertSeeHtml('data-meeting-grid')
            ->assertSee('Monday works better')
            ->assertSeeHtml('data-guest-link')
            ->call('choose', $suggested->uuid)
            ->assertSeeHtml('data-meeting-agreed');

        // Ali (an account) gets it in his planner; the guest gets a calendar file.
        $this->assertNotNull(MeetingInvitee::query()->where('actor_id', $ali->id)->value('plan_id'));
        $this->get(route('meetings.guest.ics', $token))->assertOk()->assertSee('SUMMARY:Project kickoff', false);

        // Later Reza registers with that email: the meeting is added to his planner.
        $reza = $this->person('reza', 'Asia/Tehran', 'reza@example.com');
        app(ProvisionVerifiedUserDefaults::class)->execute($reza->user);
        $invitee = MeetingInvitee::query()->where('email', 'reza@example.com')->sole();
        $this->assertSame($reza->id, $invitee->actor_id);
        $this->assertSame('2026-10-26 11:00', Plan::query()->findOrFail($invitee->plan_id)->occurrences()->sole()->scheduled_start_at->setTimezone('Asia/Tehran')->format('Y-m-d H:i'));
    }

    public function test_signed_in_person_opening_a_guest_link_takes_over_the_invitation(): void
    {
        $organizer = $this->person('safdar');
        $result = app(MeetingScheduler::class)->create($organizer->user, ['title' => 'Lunch', 'duration_minutes' => 60, 'timezone' => 'UTC'],
            [CarbonImmutable::parse('2026-10-25 12:00', 'UTC')], [['name' => 'Mina']]);
        $token = basename(array_values($result['links'])[0]);
        $mina = $this->person('mina');

        Livewire::actingAs($mina->user)->test(GuestRespond::class, ['token' => $token])
            ->assertRedirect(route('planner.meetings.show', $result['meeting']));
        $this->assertSame($mina->id, MeetingInvitee::query()->sole()->actor_id);

        // The link cannot be taken over twice.
        $other = $this->person('other');
        $this->expectException(ValidationException::class);
        app(MeetingScheduler::class)->claim(MeetingInvitee::query()->sole(), $other->user);
    }

    public function test_declining_cancelling_and_access(): void
    {
        $organizer = $this->person('safdar');
        $sara = $this->person('sara');
        $meeting = app(MeetingScheduler::class)->create($organizer->user, ['title' => 'Call', 'duration_minutes' => 30, 'timezone' => 'UTC'],
            [CarbonImmutable::parse('2026-10-25 12:00', 'UTC')], [['username' => 'sara']])['meeting'];

        $stranger = $this->person('stranger');
        $this->actingAs($stranger->user)->get(route('planner.meetings.show', $meeting))->assertForbidden();

        Livewire::actingAs($sara->user)->test(MeetingShow::class, ['meeting' => $meeting])->call('decline');
        $this->assertSame(MeetingInvitee::STATUS_DECLINED, MeetingInvitee::query()->sole()->status);

        Livewire::actingAs($organizer->user)->test(MeetingShow::class, ['meeting' => $meeting])
            ->assertSee(__('planner.meetings.declined_short'))
            ->call('cancelMeeting');
        $this->assertSame(MeetingRequest::STATUS_CANCELLED, $meeting->fresh()->status);

        $this->expectException(ValidationException::class);
        app(MeetingScheduler::class)->create($organizer->user, ['title' => 'Past', 'duration_minutes' => 30, 'timezone' => 'UTC'],
            [CarbonImmutable::parse('2026-10-20 12:00', 'UTC')], [['username' => 'sara']]);
    }

    private function person(string $username, string $timezone = 'UTC', ?string $email = null): Actor
    {
        $actor = Actor::factory()->create();
        $actor->user->forceFill(['username' => $username, 'timezone' => $timezone, 'email' => $email ?? $username.'@test.local'])->save();

        return $actor->fresh('user');
    }
}
