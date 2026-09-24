<?php

namespace Tests\Feature;

use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\CreateGroup;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Support\ContentBlueprintCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ContentBlueprintKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_catalog_is_versioned_idempotent_and_context_independent(): void
    {
        $actor = Actor::factory()->create();
        $personal = app(EnsurePersonalContext::class)->execute($actor->user);

        $first = app(EnsureSystemContentBlueprints::class)->execute();
        $second = app(EnsureSystemContentBlueprints::class)->execute();

        $this->assertCount(12, $first);
        $this->assertCount(12, $second);
        $this->assertDatabaseCount('content_blueprints', 12);
        $this->assertDatabaseCount('content_blueprint_versions', 12);

        $evidence = ContentBlueprint::query()->where('slug', 'evidence-work-sample')->firstOrFail();
        $version = $evidence->activeVersionRecord();

        $this->assertInstanceOf(ContentBlueprintVersion::class, $version);
        $this->assertNotNull($version->published_at);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $version->content_hash);
        $this->assertTrue($version->supportsContext($personal->kind));

        $catalog = app(ContentBlueprintCatalog::class)->availableFor($actor->user, $personal);
        $this->assertTrue($catalog->contains('slug', 'note-diary'));
        $this->assertTrue($catalog->contains('slug', 'media-album'));
        $this->assertTrue($catalog->contains('slug', 'book-booklet'));
    }

    public function test_system_blueprints_are_available_in_admission_context_without_group_membership(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Blueprint admission group', null);
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'under_review',
        ]);
        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);

        $catalog = app(ContentBlueprintCatalog::class)->availableFor($candidate->user, $context);

        $this->assertTrue($catalog->contains('slug', 'evidence-work-sample'));
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);
    }

    public function test_published_blueprint_version_is_immutable(): void
    {
        $blueprint = ContentBlueprint::factory()->create();
        $version = ContentBlueprintVersion::factory()->published()->create([
            'content_blueprint_id' => $blueprint->id,
        ]);

        $this->expectException(LogicException::class);
        $version->update(['render_template_key' => 'minimal']);
    }

    public function test_blueprint_rejects_media_blocks_without_real_assets(): void
    {
        $this->expectException(LogicException::class);

        ContentBlueprintVersion::factory()->create([
            'initial_blocks' => [[
                'type' => 'image',
                'data' => ['asset_placement_uuid' => 'fake'],
                'style' => [],
            ]],
        ]);
    }
}
