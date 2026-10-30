<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Content\SetContentPublicAccess;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ManagePlanContent;
use App\Livewire\Content\CreateContent;
use App\Livewire\Planner\RelatedContent;
use App\Models\Actor;
use App\Models\ActorContact;
use App\Models\ContentBlueprint;
use App\Models\DirectContext;
use App\Models\Plan;
use App\Models\PlanContentLink;
use App\Models\PlanOccurrence;
use App\Models\SpaceContent;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** Content linked to a Plan (and a day of it) is only a pointer: Plan and Content visibility stay independent. */
class PlanContentLinksTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-11-01 08:00:00');
    }

    private function person(): Actor
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['content', 'planner', 'messages']);

        return $actor;
    }

    private function readingPlan(Actor $a): Plan
    {
        $context = app(EnsurePersonalContext::class)->execute($a->user);
        $plan = app(CreatePlan::class)->execute($context, $a->user, 'Read the book in 60 days', timezone: 'Asia/Tehran');
        app(CreatePlanScheduleRule::class)->execute($plan, $a->user, PlanScheduleFrequency::Daily, '2026-11-01', '21:00', 30, occurrenceLimit: 60);

        return $plan;
    }

    private function entry(Actor $a, string $title): SpaceContent
    {
        $context = app(EnsurePersonalContext::class)->execute($a->user);
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'note-diary')->firstOrFail()->activeVersionRecord();

        return app(CreateContentFromBlueprint::class)->execute($context, $version, $a->user, $title, ['body' => 'Today I read five pages.']);
    }

    public function test_a_recurring_plan_collects_one_entry_per_day_without_duplicating_the_plan_or_completing_anything(): void
    {
        $a = $this->person();
        $plan = $this->readingPlan($a);
        $days = $plan->occurrences()->orderBy('scheduled_start_at')->get();
        $this->assertGreaterThanOrEqual(2, $days->count());
        $links = app(ManagePlanContent::class);

        $day1 = $this->entry($a, 'Day 1 diary');
        $links->link($a->user, $plan, $day1, $days[0]);
        $day2 = $this->entry($a, 'Day 2 diary');
        $links->link($a->user, $plan, $day2, $days[1]);
        $links->link($a->user, $plan, $day2, $days[1]); // linking twice is one link

        $this->assertSame(1, Plan::query()->count());
        $this->assertSame(2, PlanContentLink::query()->count());
        $this->assertSame(PlanOccurrenceStatus::Scheduled, $days[0]->fresh()->status, 'writing never marks the day done');
        $this->assertSame('draft', $day1->fresh()->status);

        Livewire::actingAs($a->user)->test(RelatedContent::class, ['plan' => $plan])->assertSee('Day 1 diary')->assertSee('Day 2 diary')->assertSeeHtml('#occurrence-'.$days[0]->uuid);
        $this->actingAs($a->user)->get(route('contexts.contents.studio', [$day1->context, $day1]))->assertOk();
    }

    public function test_content_can_be_created_from_the_plan_and_is_linked_to_the_chosen_day(): void
    {
        $a = $this->person();
        $plan = $this->readingPlan($a);
        $day = $plan->occurrences()->orderBy('scheduled_start_at')->firstOrFail();
        app(EnsureSystemContentBlueprints::class)->execute();

        Livewire::actingAs($a->user)->test(CreateContent::class)
            ->call('openDialog', 'note', $plan->uuid, $day->uuid)->set('title', 'Day 1 diary')->set('payload.body', 'Five pages.')->call('create');

        $link = PlanContentLink::query()->sole();
        $this->assertSame($day->id, (int) $link->plan_occurrence_id);
        $this->assertSame('Day 1 diary', $link->content->draftRevision->title);
    }

    public function test_plan_and_content_visibility_stay_independent(): void
    {
        $a = $this->person();
        $b = $this->person();
        $c = $this->person();
        [$one, $two] = DirectContext::pair($a->id, $b->id);
        ActorContact::query()->create(['actor_one_id' => $one, 'actor_two_id' => $two, 'requested_by_actor_id' => $a->id, 'status' => ActorContact::ACCEPTED]);
        $plan = $this->readingPlan($a);
        $day = $plan->occurrences()->orderBy('scheduled_start_at')->firstOrFail();
        $draft = $this->entry($a, 'Private day 1');
        app(ManagePlanContent::class)->link($a->user, $plan, $draft, $day);
        $shared = app(PublishSpaceContent::class)->execute($this->entry($a, 'Shared day 2'), $a->user);
        app(SetContentPublicAccess::class)->execute($shared, $a->user, SpaceContent::ACCESS_CONTACTS);
        app(ManagePlanContent::class)->link($a->user, $plan, $shared, $day);

        // The contact reads the shared entry but cannot open the plan, learns nothing of it, and never sees the draft.
        $this->actingAs($b->user)->get(route('contexts.contents.show', [$shared->context, $shared]))->assertOk()->assertSee('Shared day 2')->assertDontSee('data-content-plan-links', false)->assertDontSee('Read the book in 60 days');
        $this->actingAs($b->user)->get(route('planner.show', $plan))->assertForbidden();
        $this->actingAs($b->user)->get(route('contexts.contents.show', [$draft->context, $draft]))->assertForbidden();

        // The author sees the way back from the content to the plan and its day.
        $this->actingAs($a->user)->get(route('contexts.contents.show', [$shared->context, $shared]))->assertSeeHtml('data-content-plan-links')->assertSee('Read the book in 60 days');
        Livewire::actingAs($a->user)->test(RelatedContent::class, ['plan' => $plan])->assertSee('Private day 1')->assertSee('Shared day 2');

        // Strangers cannot link to the plan, nor link content they cannot edit.
        $this->expectException(AuthorizationException::class);
        app(ManagePlanContent::class)->link($c->user, $plan, $this->entry($c, 'Intruder'), $day);
    }

    public function test_a_day_belongs_to_its_own_plan_only(): void
    {
        $a = $this->person();
        $plan = $this->readingPlan($a);
        $other = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($a->user), $a->user, 'Other plan', timezone: 'Asia/Tehran');
        $foreignDay = PlanOccurrence::query()->where('plan_id', $plan->id)->firstOrFail();

        $this->expectException(NotFoundHttpException::class);
        app(ManagePlanContent::class)->link($a->user, $other, $this->entry($a, 'Mixed'), $foreignDay);
    }
}
