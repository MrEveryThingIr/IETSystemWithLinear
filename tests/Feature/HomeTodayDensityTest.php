<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Livewire\Home\Today;
use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\PlanScheduleRule;
use App\Support\HomeTodayProjection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeTodayDensityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('release.profile', 'full');
        CarbonImmutable::setTestNow('2026-10-17 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, array{int}> */
    public static function volumes(): array
    {
        return ['one' => [1], 'ten' => [10], 'hundred' => [100], 'max scan' => [250]];
    }

    #[DataProvider('volumes')]
    public function test_today_renders_a_bounded_schedule_regardless_of_volume(int $count): void
    {
        $actor = $this->actor();
        $this->seedOccurrences($actor, $count, spacingMinutes: 3);

        $html = Livewire::actingAs($actor->user)->test(Today::class)->html();

        $rows = substr_count($html, 'data-today-row');
        $this->assertLessThanOrEqual(HomeTodayProjection::SCHEDULE_PREVIEW_LIMIT, $rows);
        $this->assertSame(min($count, HomeTodayProjection::SCHEDULE_PREVIEW_LIMIT), $rows);
        $this->assertStringContainsString('data-today-count', $html);

        if ($count > HomeTodayProjection::SCHEDULE_PREVIEW_LIMIT) {
            $this->assertStringContainsString(
                e(__('home.schedule.more_today', ['count' => $count - HomeTodayProjection::SCHEDULE_PREVIEW_LIMIT])),
                $html,
            );
        } else {
            $this->assertStringNotContainsString('data-today-more', $html);
        }
    }

    public function test_preview_prefers_running_and_upcoming_items_and_names_the_next_one(): void
    {
        $actor = $this->actor();
        $plan = $this->plan($actor, 'Morning');
        $this->occurrence($plan, '2026-10-17 07:00', 30);
        $this->occurrence($plan, '2026-10-17 08:00', 30);
        $running = $this->occurrence($this->plan($actor, 'Running review'), '2026-10-17 11:30', 60);
        $next = $this->occurrence($this->plan($actor, 'English lesson'), '2026-10-17 13:00', 60);

        $projection = app(HomeTodayProjection::class)->build($actor->user);

        $this->assertSame(4, $projection['todayOccurrenceCount']);
        $this->assertTrue($projection['inProgressOccurrences']->contains($running));
        $this->assertTrue($projection['nextOccurrence']->is($next));

        Livewire::actingAs($actor->user)
            ->test(Today::class)
            ->assertSeeHtml('data-today-focus')
            ->assertSee(__('home.schedule.now'))
            ->assertSee('Running review');
    }

    public function test_overlapping_activities_collapse_into_one_slot(): void
    {
        $actor = $this->actor();
        foreach (['Call A', 'Call B', 'Call C', 'Call D'] as $title) {
            $this->occurrence($this->plan($actor, $title), '2026-10-17 14:00', 30);
        }

        Livewire::actingAs($actor->user)
            ->test(Today::class)
            ->assertSee(__('home.schedule.activities', ['count' => 4]));
    }

    public function test_full_day_is_rendered_only_on_request(): void
    {
        $actor = $this->actor();
        $this->seedOccurrences($actor, 40, spacingMinutes: 5);

        $component = Livewire::actingAs($actor->user)->test(Today::class);
        $this->assertSame(HomeTodayProjection::SCHEDULE_PREVIEW_LIMIT, substr_count($component->html(), 'data-today-row'));

        $component->call('toggleFullSchedule');
        $this->assertSame(40, substr_count($component->html(), 'data-today-row'));
        $component->assertSee(__('home.schedule.show_less'));
    }

    public function test_greeting_no_longer_advertises_the_timezone(): void
    {
        $actor = $this->actor();
        $actor->user->forceFill(['timezone' => 'Asia/Tehran', 'timezone_mode' => 'fixed'])->save();

        Livewire::actingAs($actor->user->fresh())
            ->test(Today::class)
            ->assertDontSee('Asia/Tehran ·', false)
            ->assertDontSee('· Asia/Tehran', false);
    }

    private function actor(): Actor
    {
        $actor = Actor::factory()->create();
        $actor->user->forceFill(['timezone' => 'UTC', 'timezone_mode' => 'fixed'])->save();

        return $actor;
    }

    private function seedOccurrences(Actor $actor, int $count, int $spacingMinutes): void
    {
        $plan = $this->plan($actor, 'Repeated block');
        $start = CarbonImmutable::parse('2026-10-17 00:00:00 UTC');

        for ($i = 0; $i < $count; $i++) {
            $at = $start->addMinutes($i * $spacingMinutes);
            $this->occurrence($plan, $at->format('Y-m-d H:i'), 2);
        }
    }

    private function plan(Actor $actor, string $title): Plan
    {
        $context = app(EnsurePersonalContext::class)->execute($actor->user);

        return Plan::factory()->create([
            'context_id' => $context->id,
            'created_by_actor_id' => $actor->id,
            'title' => $title,
            'timezone' => 'UTC',
        ]);
    }

    private function occurrence(Plan $plan, string $start, int $minutes): PlanOccurrence
    {
        $rule = PlanScheduleRule::query()->where('plan_id', $plan->id)->first()
            ?? PlanScheduleRule::factory()->create(['plan_id' => $plan->id]);
        $startAt = CarbonImmutable::parse($start, 'UTC');

        return PlanOccurrence::factory()->create([
            'plan_id' => $plan->id,
            'schedule_rule_id' => $rule->id,
            'local_date' => $startAt->toDateString(),
            'scheduled_start_at' => $startAt,
            'scheduled_end_at' => $startAt->addMinutes($minutes),
            'timezone' => 'UTC',
        ]);
    }
}
