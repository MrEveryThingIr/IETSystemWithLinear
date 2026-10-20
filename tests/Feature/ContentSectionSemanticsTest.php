<?php

namespace Tests\Feature;

use App\Actions\Content\ToggleAnnotationEndorsement;
use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\UpdateSpaceContentBlocks;
use App\Livewire\Contexts\ContentShow;
use App\Livewire\Contexts\ContentStudio;
use App\Models\Actor;
use App\Models\GroupInvitation;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentBlock;
use App\Models\SpaceContentRevision;
use App\Support\Content\SectionSemantics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContentSectionSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizing_keeps_known_roles_and_clean_tags(): void
    {
        $this->assertSame(
            ['role' => 'question', 'tags' => ['grammar', 'chapter-2', 'دستور-زبان']],
            SectionSemantics::normalize('paragraph', ['role' => 'question', 'tags_text' => ' Grammar, chapter 2,grammar, دستور زبان ,,']),
        );
        $this->assertSame([], SectionSemantics::normalize('paragraph', ['role' => 'mystery']));
        $this->assertSame([], SectionSemantics::normalize('divider', ['role' => 'question']));
        $this->assertSame('answer', SectionSemantics::actionsFor('question')['primary'][0]);
        $this->assertSame(SectionSemantics::DEFAULT_ACTIONS, SectionSemantics::actionsFor(null)['primary']);
        $this->assertNotContains('answer', SectionSemantics::actionsFor('definition')['primary']);
    }

    public function test_readers_answer_a_question_section_and_the_author_marks_it_correct(): void
    {
        [$owner, $space] = $this->group();
        $student = $this->join($space, $owner);
        $content = $this->lesson($space, $owner);
        $revision = $content->activeRevisionRecord();
        $question = $revision->blocks()->get()->first(fn (SpaceContentBlock $block): bool => SectionSemantics::of($block)['role'] === 'question');
        $this->assertSame(['role' => 'question', 'tags' => ['past-tense']], SectionSemantics::of($question));

        $reader = Livewire::actingAs($student->user)
            ->test(ContentShow::class, ['context' => $content->context, 'content' => $content])
            ->assertSeeHtml('data-section-role="question"')
            ->assertSeeHtml('data-section-answer')
            ->assertSee(__('sections.roles.question.name'))
            ->call('openContextComposer', 'answer', ['target_type' => 'block', 'target_uuid' => $question->uuid, 'field_key' => null, 'selector' => ['label' => 'Question']])
            ->assertSet('annotationKind', SpaceContentAnnotation::KIND_ANSWER)
            ->assertSet('annotationVisibility', SpaceContentAnnotation::VISIBILITY_SPACE)
            ->set('annotationBody', 'I went to school yesterday.')
            ->call('postAnnotation')
            ->assertHasNoErrors();

        $answer = SpaceContentAnnotation::query()->sole();
        $this->assertSame(SpaceContentAnnotation::KIND_ANSWER, $answer->kind);
        $this->assertNull($answer->parent_annotation_id);
        $reader->assertSee(trans_choice('sections.answer_count', 1, ['count' => 1]))->assertSee(__('sections.answer_again'));

        Livewire::actingAs($owner->user)
            ->test(ContentShow::class, ['context' => $content->context, 'content' => $content])
            ->call('openMarkerPreview', [$answer->uuid])
            ->assertSeeHtml('data-answer-mark')
            ->call('toggleEndorsement', $answer->uuid)
            ->assertSee(trans_choice('sections.correct_count', 1, ['count' => 1]))
            ->assertSeeHtml('data-answer-correct');

        // Students cannot mark their own answers correct.
        $this->expectException(HttpException::class);
        app(ToggleAnnotationEndorsement::class)->execute($answer, $student->user);
    }

    public function test_answers_only_belong_to_answerable_sections(): void
    {
        [$owner, $space] = $this->group();
        $student = $this->join($space, $owner);
        $content = $this->lesson($space, $owner);
        $revision = $content->activeRevisionRecord();
        $definition = $revision->blocks()->get()->first(fn (SpaceContentBlock $block): bool => SectionSemantics::of($block)['role'] === 'definition');

        try {
            app(AddSpaceContentAnnotation::class)->execute($content, $revision, $student->user, 'An answer', null, SpaceContentAnnotation::KIND_ANSWER, anchors: [
                ['target_type' => 'block', 'target_uuid' => $definition->uuid, 'field_key' => null, 'selector' => ['label' => 'x']],
            ]);
            $this->fail('A definition cannot be answered.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        Livewire::actingAs($student->user)
            ->test(ContentShow::class, ['context' => $content->context, 'content' => $content])
            ->assertSeeHtml('data-section-role="definition"')
            ->assertSeeHtml('data-context-purpose="remember"');
    }

    public function test_author_tags_sections_in_the_studio(): void
    {
        [$owner, $space] = $this->group();
        $content = $this->lesson($space, $owner, publish: false);

        Livewire::actingAs($owner->user)
            ->test(ContentStudio::class, ['context' => $content->context, 'content' => $content])
            ->assertSeeHtml('data-studio-section-role="question"')
            ->call('setSectionRole', 2, 'exercise')
            ->set('blocks.2.data.semantics.tags_text', 'homework, week 3')
            ->call('saveRevision')
            ->assertHasNoErrors();

        $block = $content->fresh()->draftRevisionRecord()->blocks()->orderBy('position')->get()[2];
        $this->assertSame(['role' => 'exercise', 'tags' => ['homework', 'week-3']], SectionSemantics::of($block));
    }

    /** @return array{Actor, GroupSpace} */
    private function group(): array
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'English class', null);

        return [$owner, GroupSpace::query()->where('group_id', $group->id)->where('is_default', true)->sole()];
    }

    private function join(GroupSpace $space, Actor $owner): Actor
    {
        $token = GroupInvitation::issueToken();
        GroupInvitation::create([
            'group_id' => $space->group_id,
            'invited_by_actor_id' => $owner->id,
            'join_mode' => GroupInvitation::JOIN_DIRECT,
            'token' => $token,
            'expires_at' => now()->addDay(),
            'uses_count' => 0,
        ]);
        $actor = Actor::factory()->create();
        $this->actingAs($actor->user)->post(route('invitations.accept', $token));

        return $actor;
    }

    private function lesson(GroupSpace $space, Actor $owner, bool $publish = true): SpaceContent
    {
        $definition = app(CreateSpaceContentDefinition::class)->execute($space, $owner->user, 'Lesson', null, [[
            'key' => 'body', 'label' => 'Body', 'type' => 'long_text', 'required' => false, 'help' => null, 'options' => [],
        ]]);
        $definition = app(ActivateSpaceContentDefinition::class)->execute($definition, $owner->user);
        $content = app(CreateSpaceContent::class)->execute($space, $definition, $owner->user, 'Past tense', ['body' => '']);
        $content = app(UpdateSpaceContentBlocks::class)->execute($content, $owner->user, SpaceContentRevision::COMPOSITION_BLOCKS, [
            ['type' => 'paragraph', 'data' => ['text' => 'We use the past tense for finished actions.', 'semantics' => ['role' => 'definition']]],
            ['type' => 'paragraph', 'data' => ['text' => 'Write one sentence about yesterday.', 'semantics' => ['role' => 'question', 'tags' => ['past tense']]]],
            ['type' => 'paragraph', 'data' => ['text' => 'Plain text without a role.']],
        ]);

        return $publish ? app(PublishSpaceContent::class)->execute($content, $owner->user) : $content;
    }
}
