<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\SaveSpaceContentRenderTemplate;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Actions\Groups\UpdateSpaceContentBlocks;
use App\Actions\Groups\UpdateSpaceContentPresentation;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Support\SpaceContentPresentation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpaceContentCompositionPresentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_block_document_preserves_logical_identity_through_later_text_revision(): void
    {
        [$group, $space, $owner, $author, $content] = $this->fixture();
        $logicalUuid = (string) Str::uuid();

        $content = app(UpdateSpaceContentBlocks::class)->execute(
            $content,
            $author->user,
            SpaceContentRevision::COMPOSITION_BLOCKS,
            [[
                'logical_uuid' => $logicalUuid,
                'type' => 'paragraph',
                'data' => ['text' => 'An addressable paragraph.'],
                'style' => ['text_color' => '#112233', 'alignment' => 'start'],
            ]],
        );
        $blockRevision = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $blockRevision);
        $firstBlock = $blockRevision->blocks()->sole();
        $this->assertSame($logicalUuid, $firstBlock->logical_uuid);

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $author->user,
            'Revised title',
            ['body' => 'Changed structured payload without losing block layout.'],
        );
        $laterRevision = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $laterRevision);
        $laterBlock = $laterRevision->blocks()->sole();

        $this->assertNotSame($firstBlock->uuid, $laterBlock->uuid);
        $this->assertSame($firstBlock->logical_uuid, $laterBlock->logical_uuid);
        $this->assertSame('An addressable paragraph.', $laterBlock->data['text']);
        $this->assertSame('#112233', $laterBlock->style['text_color']);
    }

    public function test_unchanged_block_and_appearance_saves_do_not_create_revision_noise(): void
    {
        [$group, $space, $owner, $author, $content] = $this->fixture();
        $blocks = [[
            'logical_uuid' => (string) Str::uuid(),
            'type' => 'paragraph',
            'data' => ['text' => 'Stable layout'],
            'style' => [],
        ]];

        $content = app(UpdateSpaceContentBlocks::class)->execute(
            $content,
            $author->user,
            SpaceContentRevision::COMPOSITION_BLOCKS,
            $blocks,
        );
        $afterBlockCount = $content->revisions()->count();
        $content = app(UpdateSpaceContentBlocks::class)->execute(
            $content,
            $author->user,
            SpaceContentRevision::COMPOSITION_BLOCKS,
            $blocks,
        );
        $this->assertSame($afterBlockCount, $content->revisions()->count());

        $content = app(UpdateSpaceContentPresentation::class)->execute(
            $content,
            $author->user,
            'builtin:lesson',
            ['accent' => '#123456'],
        );
        $afterAppearanceCount = $content->revisions()->count();
        $editable = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $editable);

        $content = app(UpdateSpaceContentPresentation::class)->execute(
            $content,
            $author->user,
            'builtin:lesson',
            $editable->presentation,
        );
        $this->assertSame($afterAppearanceCount, $content->revisions()->count());
    }

    public function test_safe_presentation_rejects_arbitrary_values_and_unknown_tokens(): void
    {
        $resolved = app(SpaceContentPresentation::class)->resolve('article', [
            'background' => 'javascript:alert(1)',
            'accent' => '#ABCDEF',
            'content_width' => 'impossible',
            'arbitrary_css' => 'position:fixed',
            'field_styles' => [
                'body' => [
                    'text_color' => '#112233',
                    'background_color' => 'red',
                    'emphasis' => 'strong',
                    'evil' => 'expression()',
                ],
            ],
        ], ['body']);

        $this->assertSame('#fafafa', $resolved['background']);
        $this->assertSame('#abcdef', $resolved['accent']);
        $this->assertSame('reading', $resolved['content_width']);
        $this->assertArrayNotHasKey('arbitrary_css', $resolved);
        $this->assertSame('#112233', $resolved['field_styles']['body']['text_color']);
        $this->assertArrayNotHasKey('background_color', $resolved['field_styles']['body']);
        $this->assertArrayNotHasKey('evil', $resolved['field_styles']['body']);
    }

    public function test_manifest_v2_seals_blocks_and_resolved_presentation_independently_of_saved_template_changes(): void
    {
        [$group, $space, $owner, $author, $content] = $this->fixture();
        $logicalUuid = (string) Str::uuid();

        $content = app(UpdateSpaceContentBlocks::class)->execute(
            $content,
            $author->user,
            SpaceContentRevision::COMPOSITION_BLOCKS,
            [[
                'logical_uuid' => $logicalUuid,
                'type' => 'paragraph',
                'data' => ['text' => 'Published block evidence'],
                'style' => ['background_color' => '#fef3c7', 'emphasis' => 'callout'],
            ]],
        );

        $template = app(SaveSpaceContentRenderTemplate::class)->execute(
            $content,
            $author->user,
            'Warm lesson',
            'lesson',
            ['accent' => '#7c3aed', 'background' => '#fff7ed'],
        );
        $content = app(UpdateSpaceContentPresentation::class)->execute(
            $content,
            $author->user,
            'custom:'.$template->uuid,
            ['accent' => '#7c3aed', 'background' => '#fff7ed'],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $published = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $published);
        $this->assertSame(2, $published->manifest_version);
        $this->assertIsString($published->canonical_manifest);

        $manifest = json_decode($published->canonical_manifest, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('blocks', $manifest['composition_mode']);
        $this->assertSame('lesson', $manifest['render_template_key']);
        $this->assertSame($template->uuid, $manifest['render_template_uuid']);
        $this->assertSame('#7c3aed', $manifest['presentation']['accent']);
        $this->assertSame($logicalUuid, $manifest['blocks'][0]['logical_uuid']);
        $this->assertSame('Published block evidence', $manifest['blocks'][0]['data']['text']);
        $this->assertSame('#fef3c7', $manifest['blocks'][0]['style']['background_color']);

        $manifestHash = $published->manifest_hash;
        $canonicalManifest = $published->canonical_manifest;
        $template->update(['tokens' => app(SpaceContentPresentation::class)->resolve('lesson', ['accent' => '#dc2626'], ['body'])]);

        $published->refresh();
        $this->assertSame($manifestHash, $published->manifest_hash);
        $this->assertSame($canonicalManifest, $published->canonical_manifest);
        $this->assertSame('#7c3aed', $published->presentation['accent']);
    }

    /** @return array{Group, GroupSpace, Actor, Actor, SpaceContent} */
    private function fixture(): array
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Composition group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Publishing studio', 'restricted');
        app(SetGroupSpaceParticipant::class)->execute($space, $owner, $owner->user, 'allow', 'manager');
        app(SetGroupSpaceParticipant::class)->execute($space, $author, $owner->user, 'allow', 'participant');

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
            'Composable article',
            ['body' => 'Structured source body.'],
        );

        return [$group, $space, $owner, $author, $content];
    }
}
