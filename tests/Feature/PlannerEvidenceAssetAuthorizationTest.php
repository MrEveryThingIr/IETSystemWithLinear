<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Context;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\PlanScheduleFrequency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlannerEvidenceAssetAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTES = [
        'planner.occurrences.assets.show',
        'planner.occurrences.assets.download',
        'planner.occurrences.evidence.show',
    ];

    public function test_guest_is_redirected_and_stranger_is_forbidden(): void
    {
        Storage::fake('local');
        [$owner, $plan, $occurrence] = $this->plan();
        $asset = $this->attached($owner, $plan, $occurrence);
        $stranger = Actor::factory()->create();

        foreach (self::ROUTES as $route) {
            $url = route($route, [$plan, $occurrence, $asset]);
            $this->get($url)->assertRedirect();
            $this->actingAs($stranger->user)->get($url)->assertForbidden();
            auth()->logout();
        }
    }

    public function test_context_asset_not_linked_to_the_occurrence_is_not_served(): void
    {
        Storage::fake('local');
        [$owner, $plan, $occurrence] = $this->plan();
        $this->attached($owner, $plan, $occurrence);
        $unlinked = $this->asset($plan->context, $owner, 'unlinked.png');

        foreach (self::ROUTES as $route) {
            $this->actingAs($owner->user)->get(route($route, [$plan, $occurrence, $unlinked]))->assertNotFound();
        }
    }

    public function test_occurrence_from_another_plan_is_not_accepted(): void
    {
        Storage::fake('local');
        [$owner, $plan, $occurrence] = $this->plan();
        $asset = $this->attached($owner, $plan, $occurrence);
        $otherPlan = app(CreatePlan::class)->execute($plan->context, $owner->user, 'Other plan', timezone: 'UTC');

        foreach (self::ROUTES as $route) {
            $this->actingAs($owner->user)->get(route($route, [$otherPlan, $occurrence, $asset]))->assertNotFound();
        }
    }

    public function test_missing_stored_file_is_not_found(): void
    {
        Storage::fake('local');
        [$owner, $plan, $occurrence] = $this->plan();
        $asset = $this->attached($owner, $plan, $occurrence);
        Storage::disk('local')->delete($asset->storage_key);

        $this->actingAs($owner->user)
            ->get(route('planner.occurrences.assets.show', [$plan, $occurrence, $asset]))
            ->assertNotFound();
    }

    /** @return array{Actor, Plan, PlanOccurrence} */
    private function plan(): array
    {
        $owner = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($owner->user);
        $plan = app(CreatePlan::class)->execute($context, $owner->user, 'Private plan', timezone: 'UTC');
        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan, $owner->user, PlanScheduleFrequency::Once, now('UTC')->addDay()->toDateString(), '09:00', 30,
        );

        return [$owner, $plan->fresh('context'), $rule->occurrences()->sole()];
    }

    private function attached(Actor $owner, Plan $plan, PlanOccurrence $occurrence): Asset
    {
        $asset = $this->asset($plan->context, $owner, 'private-proof.png');
        app(AttachPlanOccurrenceEvidence::class)->execute($occurrence, $owner->user, assetIds: [$asset->id]);

        return $asset;
    }

    private function asset(Context $context, Actor $actor, string $filename): Asset
    {
        $key = 'assets/'.Str::uuid().'/'.$filename;
        Storage::disk('local')->put($key, 'bytes');

        return Asset::query()->create([
            'context_id' => $context->id,
            'original_filename' => $filename,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'byte_size' => 5,
            'disk' => 'local',
            'storage_key' => $key,
            'sha256' => hash('sha256', 'bytes'),
            'uploaded_by_actor_id' => $actor->id,
            'scan_status' => 'unavailable',
            'processing_status' => 'ready',
            'rights_status' => 'owned',
        ]);
    }
}
