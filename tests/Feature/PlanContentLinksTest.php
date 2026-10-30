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
use App\CalendarSystem;
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
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** @return array<string, array{string, CalendarSystem, string, string, string, string, string}> */
    public static function temporalProfiles(): array
    {
        return [
            'Gregorian numeric' => ['en', CalendarSystem::Gregorian, 'UTC', 'numeric', 'hidden', '2026/11/01', '2026-11-01'],
            'Persian Jalali' => ['fa', CalendarSystem::Persian, 'Asia/Tehran', 'numeric', 'hidden', '۱۴۰۵/۰۸/۱۰', '2026-11-01'],
            'English Jalali' => ['en', CalendarSystem::Persian, 'UTC', 'numeric', 'hidden', '1405/08/10', '2026-11-01'],
            'Persian Gregorian' => ['fa', CalendarSystem::Gregorian, 'UTC', 'numeric', 'hidden', '۲۰۲۶/۱۱/۰۱', '2026-11-01'],
            'Timezone crosses midnight' => ['en', CalendarSystem::Gregorian, 'Pacific/Kiritimati', 'medium', 'hidden', '2 Nov 2026', '2026-11-02'],
            'Long format' => ['en', CalendarSystem::Gregorian, 'Asia/Tehran', 'long', 'hidden', '1 November 2026', '2026-11-01'],
            'Inline equivalent' => ['fa', CalendarSystem::Persian, 'Pacific/Kiritimati', 'numeric', 'inline', '۱۴۰۵/۰۸/۱۱', '2026-11-02'],
            'Hover equivalent' => ['fa', CalendarSystem::Persian, 'Asia/Tehran', 'numeric', 'hover', '۱۴۰۵/۰۸/۱۰', '2026-11-01'],
            'System timezone fallback' => ['en', CalendarSystem::Gregorian, '', 'numeric', 'hidden', '2026/11/02', '2026-11-02'],
        ];
    }

    #[DataProvider('temporalProfiles')]
    public function test_content_occurrence_labels_follow_temporal_preferences_without_changing_identity_or_schedule(
        string $locale, CalendarSystem $calendar, string $timezone, string $format, string $equivalent,
        string $primary, string $displayDate,
    ): void {
        config(['app.timezone' => 'Pacific/Kiritimati']);
        $actor = $this->person();
        $user = $actor->user;
        $user->forceFill(['locale' => $locale, 'calendar' => $calendar, 'timezone' => $timezone,
            'date_display_format' => $format, 'gregorian_equivalent_mode' => $equivalent,
            'show_gregorian_equivalent' => $equivalent !== 'hidden'])->save();
        app()->setLocale($locale);
        $plan = $this->readingPlan($actor);
        $day = $plan->occurrences()->orderBy('scheduled_start_at')->firstOrFail();
        $this->assertSame('2026-11-01', $day->local_date->toDateString());
        $this->assertSame('2026-11-01 17:30:00', $day->scheduled_start_at->format('Y-m-d H:i:s'));
        $snapshot = fn (): array => [
            DB::table('plan_occurrences')->where('plan_id', $plan->id)->orderBy('id')->get()->toJson(),
            DB::table('plan_schedule_rules')->where('plan_id', $plan->id)->orderBy('id')->get()->toJson(),
        ];
        $before = $snapshot();
        $content = $this->entry($actor, 'Temporal content');
        $panel = Livewire::actingAs($user)->test(RelatedContent::class, ['plan' => $plan]);
        $html = $panel->html();
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $options = (new \DOMXPath($document))->query('//*[@data-plan-content-day]//option[@value="'.$day->uuid.'"]');
        $this->assertSame(1, $options->length);
        $expected = $equivalent === 'inline'
            ? $primary.' · '.__('ui.profile.temporal.gregorian_equivalent').' · ۲۰۲۶/۱۱/۰۲'
            : $primary;
        $this->assertSame($expected, trim($options->item(0)->textContent));
        $panel->set('occurrence', $day->uuid)->set('attach', $content->uuid)->call('attachExisting')->assertHasNoErrors();
        $this->assertSame($day->uuid, $panel->get('occurrence'));
        $link = PlanContentLink::query()->sole();
        $this->assertSame($day->id, $link->plan_occurrence_id);
        $this->assertSame($content->id, $link->space_content_id);
        $panel->assertSeeHtml('data-profile-date="'.$displayDate.'"')->assertSee($primary);
        $backlink = Blade::render('<x-content.plan-links :content="$content" />', ['content' => $content]);
        $this->assertStringContainsString('data-profile-date="'.$displayDate.'"', $backlink);
        $this->assertStringContainsString($primary, $backlink);
        $this->assertStringContainsString('#occurrence-'.$day->uuid, $backlink);
        foreach ([$panel->html(), $backlink] as $rendered) {
            if ($equivalent === 'hidden') {
                $this->assertStringNotContainsString('data-temporal-equivalent', $rendered);
            } else {
                $this->assertStringContainsString('data-equivalent-mode="'.$equivalent.'"', $rendered);
                $this->assertStringContainsString('data-temporal-equivalent', $rendered);
            }
        }
        $this->assertSame($before, $snapshot(), 'Presentation and linking must not mutate or regenerate occurrences or rules.');
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
