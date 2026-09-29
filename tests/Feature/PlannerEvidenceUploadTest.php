<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Livewire\Planner\Show as PlannerShow;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\ContentEvidenceReference;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\PlanScheduleFrequency;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PlannerEvidenceUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_upload_new_context_evidence_on_the_occurrence_page(): void
    {
        Storage::fake('local');
        CarbonImmutable::setTestNow('2026-09-25 06:00:00 UTC');

        try {
            $actor = Actor::factory()->create();
            $context = app(EnsurePersonalContext::class)->execute($actor->user);
            $plan = app(CreatePlan::class)->execute($context, $actor->user, 'Evidence-ready plan', timezone: 'UTC');
            $rule = app(CreatePlanScheduleRule::class)->execute(
                $plan, $actor->user, PlanScheduleFrequency::Once, '2026-09-25', '08:00', 60,
            );
            $occurrence = $rule->occurrences()->sole();

            Livewire::actingAs($actor->user)
                ->test(PlannerShow::class, ['plan' => $plan])
                ->call('chooseEvidenceOccurrence', $occurrence->id)
                ->assertSee(__('planner.plan.upload_evidence'))
                ->set('evidenceUpload', UploadedFile::fake()->create('proof.txt', 1, 'text/plain'))
                ->set('evidenceUploadRightsStatus', 'owned')
                ->call('attachEvidence')
                ->assertHasNoErrors()
                ->assertSee('proof.txt');

            $asset = Asset::query()->where('context_id', $context->id)->where('original_filename', 'proof.txt')->sole();
            $this->assertDatabaseHas('plan_occurrence_assets', [
                'plan_occurrence_id' => $occurrence->id,
                'asset_id' => $asset->id,
                'added_by_actor_id' => $actor->id,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_participant_cannot_attach_content_evidence_they_cannot_view(): void
    {
        $owner = Actor::factory()->create();
        $participant = Actor::factory()->create();
        $group = Group::factory()->create(['created_by_actor_id' => $owner->id]);

        GroupMembership::factory()->create([
            'group_id' => $group->id,
            'actor_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        GroupMembership::factory()->create([
            'group_id' => $group->id,
            'actor_id' => $participant->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $space = GroupSpace::factory()->create([
            'group_id' => $group->id,
            'created_by_actor_id' => $owner->id,
            'access_mode' => 'group',
        ]);
        $context = app(EnsureGroupSpaceContext::class)->execute($space);

        $plan = app(CreatePlan::class)->execute(
            $context,
            $owner->user,
            'Shared plan',
            timezone: 'UTC',
            participants: [['actor' => $participant, 'role' => 'participant']],
        );
        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $owner->user,
            PlanScheduleFrequency::Once,
            now('UTC')->addDay()->toDateString(),
            '12:00',
            60,
        );
        $occurrence = $rule->occurrences()->sole();

        $content = SpaceContent::factory()->create([
            'group_space_id' => $space->id,
            'context_id' => $context->id,
            'author_actor_id' => $owner->id,
            'status' => 'draft',
        ]);
        $revision = SpaceContentRevision::factory()->create([
            'space_content_id' => $content->id,
            'created_by_actor_id' => $owner->id,
        ]);
        $reference = ContentEvidenceReference::factory()->create([
            'context_id' => $context->id,
            'space_content_id' => $content->id,
            'space_content_revision_id' => $revision->id,
            'created_by_actor_id' => $owner->id,
        ]);

        $this->expectException(AuthorizationException::class);

        app(AttachPlanOccurrenceEvidence::class)->execute(
            $occurrence,
            $participant->user,
            evidenceReferenceIds: [$reference->id],
        );
    }
}
