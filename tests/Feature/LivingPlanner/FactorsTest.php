<?php

namespace Tests\Feature\LivingPlanner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\ComposePlan;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ExecuteLivingOccurrence;
use App\Actions\Planner\ShareCommitmentOutcomes;
use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\Models\Actor;
use App\Models\Concept;
use App\PlanScheduleFrequency;
use App\Support\Planner\CommitmentFactors;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FactorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_factors_count_each_occurrence_once_and_keep_unknowns_out_of_reported_denominator(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:00', 'UTC'));
        $user = Actor::factory()->create()->user;
        $context = app(EnsurePersonalContext::class)->execute($user);
        $root = app(CreatePlan::class)->execute($context, $user, 'Routine', timezone: 'UTC');
        $child = app(CreatePlan::class)->execute($context, $user, 'Study', timezone: 'UTC');
        app(ComposePlan::class)->execute($user, $child, $root);
        $rule = app(CreatePlanScheduleRule::class)->execute($child, $user, PlanScheduleFrequency::Daily, '2026-11-05', '09:00', 60, occurrenceLimit: 2);
        $this->travel(3)->days();
        app(ExecuteLivingOccurrence::class)->execute($user, $rule->occurrences()->oldest('id')->first(), 'report', 'measure-report-1', [
            'outcome' => 'completed', 'actual_start_at' => '2026-11-05T09:10:00Z', 'actual_end_at' => '2026-11-05T10:10:00Z',
        ]);
        $factors = app(CommitmentFactors::class);
        $ids = $factors->hierarchy($user, $root);
        $result = $factors->measure($user, [...$ids, $child->id], CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'));
        $this->assertSame(2, $result['expected']);
        $this->assertSame(1, $result['reported']);
        $this->assertSame(1, $result['counts']['unconfirmed']);
        $this->assertSame(100.0, $result['completed_of_reported_percent']);
        $this->assertSame(600, $result['rows'][0]['start_deviation_seconds']);
        $this->assertFalse($result['rows'][0]['start_within_window']);
        $this->assertSame('self', $result['rows'][0]['commitment_to']);
        $this->assertSame('self_reported', $result['rows'][0]['confidence']);
    }

    public function test_sharing_is_explicit_recipient_and_purpose_scoped_and_revocable(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $relationship = app(CreateRelationship::class)->execute($alice->user, Concept::factory()->create(), 'participant', [['actor' => $bob, 'role' => 'participant']]);
        $relationship = app(RespondToRelationship::class)->execute($relationship, $bob->user, true);
        $plan = app(CreatePlan::class)->execute($relationship->contextBinding->context, $alice->user, 'Shared commitment', participants: [['actor' => $bob]]);
        $service = app(ShareCommitmentOutcomes::class);
        $share = $service->grant($alice->user, $bob, 'Review this engagement', [$plan->id], CarbonImmutable::now()->subMonth(), CarbonImmutable::now(), CarbonImmutable::now()->addDay());
        $result = $service->read($bob->user, $share, 'Review this engagement');
        $this->assertSame($alice->id, $result['subject_actor_id']);
        $this->assertArrayNotHasKey('reflections', $result);
        try {
            $service->read($bob->user, $share, 'Recruiting');
            $this->fail('Consent purpose was ignored.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $service->revoke($alice->user, $share);
        $this->expectException(HttpException::class);
        $service->read($bob->user, $share, 'Review this engagement');
    }
}
