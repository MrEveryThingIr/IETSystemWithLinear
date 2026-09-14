<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Livewire\Groups\SpaceContentReader;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentAnnotationAnchor;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SpaceContentRichAnnotationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_annotation_can_target_text_and_media_as_one_composite_and_attach_a_file(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $other, $content] = $this->fixtureWithPublishedMedia();
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        $placementUuid = DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $revision->id)
            ->value('uuid');
        $this->assertIsString($placementUuid);

        $annotation = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $reader->user,
            'This sentence and diagram belong together.',
            null,
            SpaceContentAnnotation::KIND_NOTE,
            SpaceContentAnnotation::VISIBILITY_SPACE,
            [
                [
                    'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
                    'field_key' => 'body',
                    'selector' => [
                        'exact' => 'important sentence',
                        'prefix' => 'An ',
                        'suffix' => ' lives here.',
                    ],
                ],
                [
                    'target_type' => SpaceContentAnnotationAnchor::TARGET_ASSET,
                    'target_uuid' => $placementUuid,
                ],
            ],
            UploadedFile::fake()->create('reference.pdf', 12, 'application/pdf'),
            'owned',
            'Supporting reference',
        );

        $this->assertCount(2, $annotation->anchors);
        $textAnchor = $annotation->anchors->firstWhere('target_type', SpaceContentAnnotationAnchor::TARGET_TEXT);
        $this->assertNotNull($textAnchor);
        $this->assertSame('important sentence', $textAnchor->selector['exact']);
        $this->assertSame('An ', $textAnchor->selector['prefix']);
        $this->assertCount(1, $annotation->assets);
        $this->assertSame('reference.pdf', $annotation->assets->first()->original_filename);
    }

    public function test_private_annotation_and_its_attachment_are_visible_only_to_author(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $other, $content] = $this->fixtureWithPublishedMedia();
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        $annotation = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $reader->user,
            'My private study note.',
            null,
            SpaceContentAnnotation::KIND_NOTE,
            SpaceContentAnnotation::VISIBILITY_PRIVATE,
            [],
            UploadedFile::fake()->create('private.pdf', 8, 'application/pdf'),
            'private_study_only',
        );
        $asset = $annotation->assets->first();
        $this->assertInstanceOf(Asset::class, $asset);

        Livewire::actingAs($reader->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->assertSee('My private study note.');

        Livewire::actingAs($other->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->assertDontSee('My private study note.');

        $url = route('groups.spaces.contents.assets.show', [$group, $space, $content, $asset]);
        $this->actingAs($reader->user)->get($url)->assertOk();
        $this->actingAs($other->user)->get($url)->assertNotFound();
    }

    public function test_question_can_receive_attachment_only_answer_with_same_context_and_visibility(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $other, $content] = $this->fixtureWithPublishedMedia();
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        $question = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $reader->user,
            'Can you explain this passage?',
            null,
            SpaceContentAnnotation::KIND_QUESTION,
            SpaceContentAnnotation::VISIBILITY_SPACE,
            [[
                'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
                'field_key' => 'body',
                'selector' => ['exact' => 'important sentence'],
            ]],
        );

        $answer = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $author->user,
            '',
            $question,
            SpaceContentAnnotation::KIND_ANSWER,
            SpaceContentAnnotation::VISIBILITY_PRIVATE,
            [],
            UploadedFile::fake()->create('answer.pdf', 9, 'application/pdf'),
            'owned',
            'Answer attachment',
        );

        $this->assertSame(SpaceContentAnnotation::KIND_ANSWER, $answer->kind);
        $this->assertSame(SpaceContentAnnotation::VISIBILITY_SPACE, $answer->visibility);
        $this->assertSame($question->id, $answer->parent_annotation_id);
        $this->assertCount(1, $answer->anchors);
        $this->assertSame('important sentence', $answer->anchors->first()->selector['exact']);
        $this->assertCount(1, $answer->assets);
    }

    public function test_reader_source_keeps_annotation_recorder_first_render_ready(): void
    {
        $source = file_get_contents(resource_path('views/livewire/groups/space-content-reader.blade.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString('id="annotation-audio-recorder-', $source);
        $this->assertStringContainsString('const suffix = @js((string) $content->uuid);', $source);
        $this->assertStringContainsString("wire:click=\"openReplyComposer('", $source);
        $this->assertStringContainsString("wire:click=\"addBlockAnchor('", $source);
        $this->assertStringNotContainsString('x-on:click="$wire.annotationComposerOpen', $source);
    }

    /** @return array{Group, GroupSpace, Actor, Actor, Actor, Actor, SpaceContent} */
    private function fixtureWithPublishedMedia(): array
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $reader = Actor::factory()->create();
        $other = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Rich annotation group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Reader laboratory', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute($space, $owner, $owner->user, 'allow', 'manager');
        app(SetGroupSpaceParticipant::class)->execute($space, $author, $owner->user, 'allow', 'participant');
        app(SetGroupSpaceParticipant::class)->execute($space, $reader, $owner->user, 'allow', 'participant');
        app(SetGroupSpaceParticipant::class)->execute($space, $other, $owner->user, 'allow', 'participant');

        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $owner->user,
            'Article',
            null,
            [[
                'key' => 'body',
                'label' => 'Body',
                'type' => 'long_text',
                'required' => true,
                'help' => null,
                'options' => [],
            ]],
        );
        $definition = app(ActivateSpaceContentDefinition::class)->execute($definition, $owner->user);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            'Contextual learning',
            ['body' => 'An important sentence lives here.'],
        );
        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->image('diagram.jpg'),
            'owned',
            'Diagram',
        );

        $content = app(PublishSpaceContent::class)->execute($content, $author->user);

        return [$group, $space, $owner, $author, $reader, $other, $content];
    }
}
