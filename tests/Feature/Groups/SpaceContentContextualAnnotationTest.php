<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Livewire\Groups\SpaceContentReader;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentAnnotationAnchor;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SpaceContentContextualAnnotationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_repeated_text_is_bound_to_the_selected_occurrence_and_legacy_context_is_deterministic(): void
    {
        [$group, $space, $owner, $author, $reader, $other, $content] = $this->fixture('work one work two work');
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        $secondStart = mb_strpos('work one work two work', 'work', 1);
        $this->assertSame(9, $secondStart);

        $annotation = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $reader->user,
            'Second occurrence.',
            null,
            SpaceContentAnnotation::KIND_NOTE,
            SpaceContentAnnotation::VISIBILITY_PRIVATE,
            [[
                'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
                'field_key' => 'body',
                'selector' => [
                    'exact' => 'work',
                    'start' => $secondStart,
                    'end' => $secondStart + 4,
                ],
            ]],
        );

        $selector = $annotation->anchors->first()->selector;
        $this->assertSame(9, $selector['start']);
        $this->assertSame(13, $selector['end']);
        $this->assertSame('work one ', $selector['prefix']);
        $this->assertSame(' two work', $selector['suffix']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $selector['text_hash']);

        $legacy = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $reader->user,
            'Legacy context.',
            null,
            SpaceContentAnnotation::KIND_NOTE,
            SpaceContentAnnotation::VISIBILITY_PRIVATE,
            [[
                'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
                'field_key' => 'body',
                'selector' => [
                    'exact' => 'work',
                    'prefix' => 'work one ',
                    'suffix' => ' two work',
                ],
            ]],
        );
        $this->assertSame(9, $legacy->anchors->first()->selector['start']);

        try {
            app(AddSpaceContentAnnotation::class)->execute(
                $content,
                $revision,
                $reader->user,
                'Wrong occurrence.',
                null,
                SpaceContentAnnotation::KIND_NOTE,
                SpaceContentAnnotation::VISIBILITY_PRIVATE,
                [[
                    'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
                    'field_key' => 'body',
                    'selector' => ['exact' => 'work', 'start' => 5, 'end' => 9],
                ]],
            );
            $this->fail('A mismatched occurrence offset must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_multiline_mixed_direction_text_uses_unicode_character_offsets(): void
    {
        $text = "First line\nسلام world\nآخر";
        [$group, $space, $owner, $author, $reader, $other, $content] = $this->fixture($text);
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        $exact = 'سلام world';
        $start = mb_strpos($text, $exact);
        $this->assertIsInt($start);

        $annotation = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $reader->user,
            'Mixed direction range.',
            null,
            SpaceContentAnnotation::KIND_NOTE,
            SpaceContentAnnotation::VISIBILITY_PRIVATE,
            [[
                'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
                'field_key' => 'body',
                'selector' => [
                    'exact' => $exact,
                    'start' => $start,
                    'end' => $start + mb_strlen($exact),
                ],
            ]],
        );

        $selector = $annotation->anchors->first()->selector;
        $this->assertSame($start, $selector['start']);
        $this->assertSame($start + mb_strlen($exact), $selector['end']);
        $this->assertSame($exact, mb_substr($text, $selector['start'], $selector['end'] - $selector['start']));
    }

    public function test_contextual_note_defaults_private_and_composite_selection_does_not_open_composer_until_action(): void
    {
        [$group, $space, $owner, $author, $reader, $other, $content] = $this->fixture('One two three.');

        $first = [
            'target_type' => SpaceContentAnnotationAnchor::TARGET_FIELD,
            'target_uuid' => null,
            'field_key' => 'body',
            'selector' => ['label' => 'Body'],
        ];
        $second = [
            'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
            'target_uuid' => null,
            'field_key' => 'body',
            'selector' => ['exact' => 'two', 'start' => 4, 'end' => 7],
        ];

        $component = Livewire::actingAs($reader->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->call('addTargetToSelection', $first)
            ->assertSet('annotationComposerOpen', false)
            ->call('addTargetToSelection', $second)
            ->assertSet('annotationComposerOpen', false);

        $this->assertCount(2, $component->get('selectedTargets'));

        $component
            ->call('openSelectionComposer', 'note', 120, 160)
            ->assertSet('annotationComposerOpen', true)
            ->assertSet('annotationComposerMode', 'note')
            ->assertSet('annotationMedium', 'text')
            ->assertSet('annotationVisibility', SpaceContentAnnotation::VISIBILITY_PRIVATE);

        $this->assertCount(2, $component->get('annotationAnchors'));
    }

    public function test_private_range_marker_uuid_is_absent_for_another_reader(): void
    {
        [$group, $space, $owner, $author, $reader, $other, $content] = $this->fixture('Private marker lives here.');
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        $annotation = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $reader->user,
            'Only I should see this marker.',
            null,
            SpaceContentAnnotation::KIND_NOTE,
            SpaceContentAnnotation::VISIBILITY_PRIVATE,
            [[
                'target_type' => SpaceContentAnnotationAnchor::TARGET_TEXT,
                'field_key' => 'body',
                'selector' => ['exact' => 'marker', 'start' => 8, 'end' => 14],
            ]],
        );

        Livewire::actingAs($reader->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->assertSee($annotation->uuid)
            ->assertSee('Only I should see this marker.');

        Livewire::actingAs($other->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->assertDontSee($annotation->uuid)
            ->assertDontSee('Only I should see this marker.');
    }

    public function test_reader_source_supports_touch_keyboard_composite_and_minimal_note_modes(): void
    {
        $source = file_get_contents(resource_path('views/livewire/groups/space-content-reader.blade.php'));
        $this->assertIsString($source);

        $this->assertStringContainsString("document.addEventListener('pointerup'", $source);
        $this->assertStringContainsString("document.addEventListener('selectionchange'", $source);
        $this->assertStringContainsString("document.addEventListener('keyup'", $source);
        $this->assertStringContainsString('data-selection-select', $source);
        $this->assertStringContainsString("openSelectionComposer('note'", $source);
        $this->assertStringContainsString("annotationComposerMode, ['file', 'voice']", $source);
        $this->assertStringContainsString("annotationMedium, ['file', 'advanced']", $source);
        $this->assertStringContainsString('data-content-annotation-mark', $source);
        $this->assertStringContainsString('window.confirm', $source);
    }

    public function test_reader_mounts_on_the_interactive_section_and_voice_status_js_has_statement_boundaries(): void
    {
        [$group, $space, $owner, $author, $reader, $other, $content] = $this->fixture('The Reader must remain interactive.');

        $html = Livewire::actingAs($reader->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->html();

        $this->assertMatchesRegularExpression('/<section[^>]*wire:id=/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<style[^>]*wire:id=/s', $html);

        $source = file_get_contents(resource_path('views/livewire/groups/space-content-reader.blade.php'));
        $this->assertIsString($source);
        $this->assertSame(3, substr_count($source, 'status.textContent = @js('));
        $this->assertStringContainsString("status.textContent = @js(__('interactions.uploading_voice_note'));", $source);
    }

    /** @return array{Group, GroupSpace, Actor, Actor, Actor, Actor, SpaceContent} */
    private function fixture(string $body): array
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $reader = Actor::factory()->create();
        $other = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Contextual reader group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Study reader', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute($space, $owner, $owner->user, 'allow', 'manager');
        app(SetGroupSpaceParticipant::class)->execute($space, $author, $owner->user, 'allow', 'participant');
        app(SetGroupSpaceParticipant::class)->execute($space, $reader, $owner->user, 'allow', 'participant');
        app(SetGroupSpaceParticipant::class)->execute($space, $other, $owner->user, 'allow', 'participant');

        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $owner->user,
            'Study page',
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
            'Study page',
            ['body' => $body],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);

        return [$group, $space, $owner, $author, $reader, $other, $content];
    }
}
