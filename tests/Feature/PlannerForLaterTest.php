<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ManagePlanContent;
use App\Actions\Planner\MaterializePlannerHorizon;
use App\CalendarSystem;
use App\Livewire\Home\Today;
use App\Livewire\Planner\Create;
use App\Livewire\Planner\Index;
use App\Livewire\Planner\Show;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\Plan;
use App\Models\PlanContentLink;
use App\Models\PlanOccurrence;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanStatus;
use App\Support\DuePlanReminderEmitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** "For later": a Plan of kind `later` with no schedule rule. It stays genuinely unscheduled until the person schedules it. */
class PlannerForLaterTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 09:00:00');
    }

    private function person(): Actor
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['content', 'planner', 'messages']);

        return $actor;
    }

    private function later(Actor $a, string $title = 'Improve Content media handling', string $notes = ''): Plan
    {
        Livewire::actingAs($a->user)->test(Create::class)->call('chooseKind', 'later')->set('title', $title)->set('description', $notes)->call('save')->assertHasNoErrors();

        return Plan::query()->latest('id')->firstOrFail();
    }

    public function test_a_title_alone_is_enough_and_nothing_about_time_is_invented(): void
    {
        $a = $this->person();
        $plan = $this->later($a);

        $this->assertSame('Improve Content media handling', $plan->title);
        $this->assertNull($plan->description);
        $this->assertSame('later', $plan->metadata['kind']);
        $this->assertSame(PlanStatus::Active, $plan->status);
        $this->assertTrue($plan->isForLater());
        $this->assertSame(0, $plan->scheduleRules()->count(), 'no schedule rule');
        $this->assertSame(0, $plan->occurrences()->count(), 'no fake occurrence');
        $this->assertSame(0, DB::table('plan_reminders')->count(), 'no reminder rows');
        $this->assertSame(0, PlanOccurrence::query()->count());
    }

    public function test_notes_are_kept_and_the_item_can_be_edited_later(): void
    {
        $a = $this->person();
        $plan = $this->later($a, 'Try the new editor', "Compare with the old one.\nAsk Sara.");
        $this->assertSame("Compare with the old one.\nAsk Sara.", $plan->description);

        Livewire::actingAs($a->user)->test(Show::class, ['plan' => $plan])->assertSeeHtml('data-for-later')->assertSeeHtml('data-for-later-schedule')
            ->call('editLater')->set('laterTitle', 'Try the new Content editor')->set('laterNotes', 'Only the media part.')->call('saveLater')->assertHasNoErrors();

        $plan->refresh();
        $this->assertSame('Try the new Content editor', $plan->title);
        $this->assertSame('Only the media part.', $plan->description);
        $this->assertTrue($plan->isForLater());
    }

    public function test_it_is_listed_and_searchable_under_for_later_but_not_among_scheduled_plans_today_or_the_calendar(): void
    {
        $a = $this->person();
        $later = $this->later($a);
        $scheduled = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($a->user), $a->user, 'Dentist visit', timezone: 'Asia/Tehran');
        app(CreatePlanScheduleRule::class)->execute($scheduled, $a->user, PlanScheduleFrequency::Once, '2026-11-02', '10:00', 30);

        $index = Livewire::actingAs($a->user)->test(Index::class);
        $index->assertSeeHtml('data-for-later-list')->assertSee('Improve Content media handling');
        $this->assertCount(1, $index->viewData('forLater'));
        $this->assertSame([$scheduled->id], $index->viewData('plans')->pluck('id')->all(), 'scheduled plans keep their own list');

        $index->set('search', 'media')->assertSeeHtml('data-for-later-item');
        $index->set('search', 'zzz')->assertDontSeeHtml('data-for-later-item');
        $index->set('search', '');

        $index->set('view', 'calendar');
        $this->assertSame([$scheduled->id], $index->viewData('occurrences')->pluck('plan_id')->all(), 'only the scheduled plan has calendar time');

        Livewire::actingAs($a->user)->test(Today::class)->assertDontSee('Improve Content media handling');
        $this->assertTrue($later->fresh()->isForLater());
    }

    public function test_the_timing_engine_never_materializes_starts_expires_or_reminds_an_unscheduled_item(): void
    {
        $a = $this->person();
        $plan = $this->later($a);

        foreach (['2026-11-03 09:00:00', '2027-02-01 09:00:00', '2030-01-01 00:00:00'] as $when) {
            $this->travelTo($when);
            $this->assertSame(0, app(MaterializePlannerHorizon::class)->execute(400), 'nothing to materialize');
            $this->assertSame(0, app(DuePlanReminderEmitter::class)->emit(), 'no reminder is due');
        }

        $plan->refresh();
        $this->assertTrue($plan->isForLater(), 'never expired, failed, started or completed');
        $this->assertSame(PlanStatus::Active, $plan->status);
        $this->assertSame(0, $plan->occurrences()->count());
        $this->assertSame(0, DB::table('notification_outbox')->count());
    }

    public function test_content_links_can_be_attached_to_an_unscheduled_item(): void
    {
        $a = $this->person();
        $plan = $this->later($a);
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'note-diary')->firstOrFail()->activeVersionRecord();
        $content = app(CreateContentFromBlueprint::class)->execute(app(EnsurePersonalContext::class)->execute($a->user), $version, $a->user, 'Media ideas', ['body' => 'Notes']);

        app(ManagePlanContent::class)->link($a->user, $plan, $content);

        $this->assertSame(1, PlanContentLink::query()->where('plan_id', $plan->id)->count());
        Livewire::actingAs($a->user)->test(Show::class, ['plan' => $plan])->assertSee('Media ideas');
    }

    public function test_scheduling_later_keeps_the_same_plan_its_metadata_and_content_links_and_the_normal_engine_takes_over(): void
    {
        $a = $this->person();
        $plan = $this->later($a, 'Improve Content media handling', 'Crop and camera follow-ups.');
        $plan->forceFill(['metadata' => [...$plan->metadata, 'color' => '#ef4444', 'important' => true]])->save();
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'note-diary')->firstOrFail()->activeVersionRecord();
        $content = app(CreateContentFromBlueprint::class)->execute(app(EnsurePersonalContext::class)->execute($a->user), $version, $a->user, 'Media ideas', ['body' => 'Notes']);
        app(ManagePlanContent::class)->link($a->user, $plan, $content);

        Livewire::actingAs($a->user)->withQueryParams(['schedule' => $plan->uuid])->test(Create::class)
            ->assertSet('step', 'when')->assertSet('title', 'Improve Content media handling')
            ->set('startsOn', '2026-11-05')->set('startTime', '10:00')->set('durationMinutes', 45)->set('frequency', 'once')
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1, Plan::query()->count(), 'the same logical plan, not a copy');
        $scheduled = $plan->fresh();
        $this->assertSame($plan->id, $scheduled->id);
        $this->assertFalse($scheduled->isForLater());
        $this->assertSame('Improve Content media handling', $scheduled->title);
        $this->assertSame('Crop and camera follow-ups.', $scheduled->description);
        $this->assertSame('task', $scheduled->metadata['kind']);
        $this->assertSame('#ef4444', $scheduled->metadata['color']);
        $this->assertTrue($scheduled->metadata['important']);
        $this->assertSame(1, PlanContentLink::query()->where('plan_id', $plan->id)->count(), 'Content links survive');
        $this->assertSame(1, $scheduled->scheduleRules()->count());
        $occurrence = $scheduled->occurrences()->sole();
        $this->assertSame('2026-11-05', $occurrence->local_date->toDateString());
        $this->assertSame(PlanOccurrenceStatus::Scheduled, $occurrence->status);

        // From now on it is an ordinary scheduled plan: out of For later, in the scheduled list.
        $index = Livewire::actingAs($a->user)->test(Index::class);
        $this->assertCount(0, $index->viewData('forLater'));
        $this->assertSame([$plan->id], $index->viewData('plans')->pluck('id')->all());
        // Scheduling twice is refused: it is no longer "for later".
        $this->actingAs($a->user)->get(route('planner.create', ['schedule' => $plan->uuid]))->assertNotFound();
    }

    public function test_after_scheduling_dates_follow_the_users_calendar_in_persian_and_english(): void
    {
        $a = $this->person();
        $a->user->forceFill(['calendar' => CalendarSystem::Persian, 'locale' => 'fa'])->save();
        $plan = $this->later($a, 'بهبود رسانه');
        app()->setLocale('fa');

        Livewire::actingAs($a->user->fresh())->withQueryParams(['schedule' => $plan->uuid])->test(Create::class)
            ->set('startsOn', '2026-11-05')->set('startTime', '10:00')->call('save')->assertHasNoErrors();

        // 14 Aban 1405 is 5 Nov 2026: the shared temporal system formats it, not this feature.
        Livewire::actingAs($a->user->fresh())->test(Show::class, ['plan' => $plan->fresh()])->assertSee('بهبود رسانه')->assertDontSeeHtml('data-for-later')->assertSee('۱۴');
        $a->user->forceFill(['calendar' => CalendarSystem::Gregorian, 'locale' => 'en'])->save();
        app()->setLocale('en');
        Livewire::actingAs($a->user->fresh())->test(Show::class, ['plan' => $plan->fresh()])->assertSee('Nov');
    }

    public function test_the_wizard_offers_for_later_without_a_when_step_in_both_languages(): void
    {
        $a = $this->person();

        foreach (['en' => ['For later', 'Improve Content media handling'], 'fa' => ['برای بعد', 'بهبود مدیریت رسانه در محتوا']] as $locale => [$name, $example]) {
            app()->setLocale($locale);
            $wizard = Livewire::actingAs($a->user)->test(Create::class)->call('chooseKind', 'later');
            $wizard->assertSee($name)->assertSeeHtml('data-plan-kind="later"')->assertSee($example)->assertDontSeeHtml('data-wizard-step="when"');
            $this->assertSame(['what', 'details', 'review'], $wizard->instance()->steps());
        }
    }

    public function test_existing_scheduled_plans_and_other_kinds_are_unchanged(): void
    {
        $a = $this->person();
        $task = Livewire::actingAs($a->user)->test(Create::class)->call('chooseKind', 'task')->set('title', 'Send invoice')
            ->set('startsOn', '2026-11-03')->set('startTime', '09:30')->call('save')->assertHasNoErrors();

        $plan = Plan::query()->sole();
        $this->assertSame('task', $plan->metadata['kind']);
        $this->assertFalse($plan->isForLater());
        $this->assertSame(1, $plan->scheduleRules()->count());
        $this->assertSame(1, $plan->occurrences()->count());
        $this->assertNotNull($task);
    }

    public function test_only_people_who_may_manage_a_plan_can_schedule_it(): void
    {
        $a = $this->person();
        $stranger = $this->person();
        $plan = $this->later($a);

        $this->actingAs($stranger->user)->get(route('planner.create', ['schedule' => $plan->uuid]))->assertForbidden();
        $this->assertTrue($plan->fresh()->isForLater());
    }
}
