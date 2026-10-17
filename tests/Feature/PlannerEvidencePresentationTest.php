<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Livewire\Planner\Show as PlannerShow;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Context;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\PlanScheduleFrequency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlannerEvidencePresentationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, string}> */
    public static function mediaKinds(): array
    {
        return [
            'image' => ['inspection.png', 'image/png', '<img'],
            'video' => ['work-result.mp4', 'video/mp4', '<video'],
            'audio' => ['note.mp3', 'audio/mpeg', '<audio'],
            'pdf' => ['report.pdf', 'application/pdf', 'data-asset-preview="pdf"'],
            'document' => ['budget.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'data-asset-preview="file"'],
        ];
    }

    #[DataProvider('mediaKinds')]
    public function test_planner_renders_evidence_as_a_media_card_not_a_filename(string $filename, string $mime, string $marker): void
    {
        Storage::fake('local');
        [$actor, $plan, $occurrence] = $this->planWithOccurrence();
        $asset = $this->storedAsset($plan->context, $actor, $filename, $mime);
        app(AttachPlanOccurrenceEvidence::class)->execute($occurrence, $actor->user, assetIds: [$asset->id]);

        Livewire::actingAs($actor->user)
            ->test(PlannerShow::class, ['plan' => $plan])
            ->assertSee($filename)
            ->assertSeeHtml($marker)
            ->assertSeeHtml(route('planner.occurrences.assets.show', [$plan, $occurrence, $asset]))
            ->assertSeeHtml(route('planner.occurrences.evidence.show', [$plan, $occurrence, $asset]))
            ->assertSee(__('media.evidence.open'));
    }

    public function test_evidence_page_shows_plan_occurrence_attacher_and_media(): void
    {
        Storage::fake('local');
        [$actor, $plan, $occurrence] = $this->planWithOccurrence();
        $asset = $this->storedAsset($plan->context, $actor, 'site-photo.jpg', 'image/jpeg');
        app(AttachPlanOccurrenceEvidence::class)->execute($occurrence, $actor->user, assetIds: [$asset->id]);

        $this->actingAs($actor->user)
            ->get(route('planner.occurrences.evidence.show', [$plan, $occurrence, $asset]))
            ->assertOk()
            ->assertSee('site-photo.jpg')
            ->assertSee($plan->title)
            ->assertSee($actor->user->username)
            ->assertSee(__('media.rights.owned'))
            ->assertSeeHtml('<img')
            ->assertSeeHtml(route('planner.occurrences.assets.download', [$plan, $occurrence, $asset]));
    }

    public function test_inline_and_download_stream_the_exact_stored_file(): void
    {
        Storage::fake('local');
        [$actor, $plan, $occurrence] = $this->planWithOccurrence();
        $asset = $this->storedAsset($plan->context, $actor, 'clip.mp4', 'video/mp4', 'video-bytes');
        app(AttachPlanOccurrenceEvidence::class)->execute($occurrence, $actor->user, assetIds: [$asset->id]);

        $inline = $this->actingAs($actor->user)
            ->get(route('planner.occurrences.assets.show', [$plan, $occurrence, $asset]));
        $inline->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Accept-Ranges', 'bytes');
        $this->assertStringContainsString('inline', (string) $inline->headers->get('Content-Disposition'));

        $download = $this->actingAs($actor->user)
            ->get(route('planner.occurrences.assets.download', [$plan, $occurrence, $asset]));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $download->baseResponse);
        $this->assertSame('video-bytes', $download->baseResponse->getFile()->getContent());
    }

    public function test_presentation_creates_no_duplicate_asset_or_content(): void
    {
        Storage::fake('local');
        [$actor, $plan, $occurrence] = $this->planWithOccurrence();
        $asset = $this->storedAsset($plan->context, $actor, 'proof.png', 'image/png');
        app(AttachPlanOccurrenceEvidence::class)->execute($occurrence, $actor->user, assetIds: [$asset->id]);

        $assets = Asset::query()->count();
        $contents = \App\Models\SpaceContent::query()->count();

        $this->actingAs($actor->user)->get(route('planner.occurrences.evidence.show', [$plan, $occurrence, $asset]))->assertOk();
        $this->actingAs($actor->user)->get(route('planner.occurrences.assets.show', [$plan, $occurrence, $asset]))->assertOk();

        $this->assertSame($assets, Asset::query()->count());
        $this->assertSame($contents, \App\Models\SpaceContent::query()->count());
    }

    /** @return array{Actor, Plan, PlanOccurrence} */
    private function planWithOccurrence(): array
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $plan = app(CreatePlan::class)->execute($context, $actor->user, 'Evidence plan '.Str::random(4), timezone: 'UTC');
        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan, $actor->user, PlanScheduleFrequency::Once, now('UTC')->addDay()->toDateString(), '08:00', 60,
        );

        return [$actor, $plan->fresh('context'), $rule->occurrences()->sole()];
    }

    private function storedAsset(Context $context, Actor $actor, string $filename, string $mime, string $bytes = 'evidence'): Asset
    {
        $key = 'assets/'.Str::uuid().'/'.$filename;
        Storage::disk('local')->put($key, $bytes);

        return Asset::query()->create([
            'context_id' => $context->id,
            'original_filename' => $filename,
            'mime_type' => $mime,
            'extension' => pathinfo($filename, PATHINFO_EXTENSION),
            'byte_size' => strlen($bytes),
            'disk' => 'local',
            'storage_key' => $key,
            'sha256' => hash('sha256', $bytes),
            'uploaded_by_actor_id' => $actor->id,
            'scan_status' => 'unavailable',
            'processing_status' => 'ready',
            'rights_status' => 'owned',
        ]);
    }
}
