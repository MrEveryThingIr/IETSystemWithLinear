<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ToggleSpaceContentReaction;
use App\Livewire\Contexts\ContentShow;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContentInteractionSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_blueprint_interaction_defaults_are_snapshotted_and_enforced_on_content(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $blueprint = ContentBlueprint::factory()->create([
            'owner_actor_id' => $actor->id,
        ]);
        $version = ContentBlueprintVersion::factory()->published()->create([
            'content_blueprint_id' => $blueprint->id,
            'interaction_defaults' => [
                'annotations' => false,
                'reactions' => false,
                'default_annotation_visibility' => 'private',
            ],
        ]);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            'Quiet evidence',
            ['body' => 'Published without reader interactions.'],
        );

        $this->assertSame([
            'annotations' => false,
            'reactions' => false,
            'default_annotation_visibility' => 'private',
        ], $content->interaction_settings);

        $content = app(PublishSpaceContent::class)->execute($content, $actor->user);
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        try {
            app(ToggleSpaceContentReaction::class)->execute(
                $content,
                $revision,
                $actor->user,
                'like',
            );
            $this->fail('Disabled reactions must be rejected server-side.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        try {
            app(AddSpaceContentAnnotation::class)->execute(
                $content,
                $revision,
                $actor->user,
                'This annotation must not be accepted.',
            );
            $this->fail('Disabled annotations must be rejected server-side.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('space_content_reactions', 0);
        $this->assertDatabaseCount('space_content_annotations', 0);
    }

    public function test_blueprint_default_annotation_visibility_guides_the_reader_composer(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $blueprint = ContentBlueprint::factory()->create([
            'owner_actor_id' => $actor->id,
        ]);
        $version = ContentBlueprintVersion::factory()->published()->create([
            'content_blueprint_id' => $blueprint->id,
            'interaction_defaults' => [
                'annotations' => true,
                'reactions' => true,
                'default_annotation_visibility' => SpaceContentAnnotation::VISIBILITY_SPACE,
            ],
        ]);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            'Collaborative note',
            ['body' => 'Shared annotation defaults.'],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $actor->user);

        Livewire::actingAs($actor->user)
            ->test(ContentShow::class, ['context' => $context, 'content' => $content])
            ->call('openRevisionAnnotation')
            ->assertSet('annotationVisibility', SpaceContentAnnotation::VISIBILITY_SPACE);
    }
}
