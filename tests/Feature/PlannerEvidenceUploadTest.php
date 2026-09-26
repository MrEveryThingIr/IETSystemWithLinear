<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Livewire\Planner\Show as PlannerShow;
use App\Models\Actor;
use App\Models\Asset;
use App\PlanScheduleFrequency;
use Carbon\CarbonImmutable;
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
                ->assertSee(__('planner.plan.upload_evidence'))
                ->call('chooseEvidenceOccurrence', $occurrence->id)
                ->call('attachEvidence')
                ->assertHasErrors(['evidence'])
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
}
