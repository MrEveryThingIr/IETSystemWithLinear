<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Actions\Groups\ToggleSpaceContentReaction;
use App\Livewire\Groups\SpaceContentReader;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentReaction;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SpaceContentInteractionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reader_can_toggle_revision_bound_reactions(): void
    {
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        $this->assertTrue(app(ToggleSpaceContentReaction::class)->execute(
            $content,
            $revision,
            $reader->user,
            SpaceContentReaction::TYPE_HELPFUL,
        ));
        $this->assertDatabaseHas('space_content_reactions', [
            'space_content_id' => $content->id,
            'space_content_revision_id' => $revision->id,
            'actor_id' => $reader->id,
            'type' => SpaceContentReaction::TYPE_HELPFUL,
        ]);

        $this->assertFalse(app(ToggleSpaceContentReaction::class)->execute(
            $content,
            $revision,
            $reader->user,
            SpaceContentReaction::TYPE_HELPFUL,
        ));
        $this->assertDatabaseCount('space_content_reactions', 0);
    }

    public function test_comments_and_one_level_replies_are_bound_to_exact_published_revision(): void
    {
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();
        $revision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        $comment = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $reader->user,
            'This explanation helped me understand the topic.',
        );
        $reply = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $author->user,
            'Glad it helped.',
            $comment,
        );

        $this->assertSame($revision->id, $comment->space_content_revision_id);
        $this->assertSame($comment->id, $reply->parent_annotation_id);
        $this->assertSame($reader->id, $comment->author_actor_id);
        $this->assertSame($author->id, $reply->author_actor_id);

        try {
            app(AddSpaceContentAnnotation::class)->execute(
                $content,
                $revision,
                $reader->user,
                'Nested reply should not be accepted yet.',
                $reply,
            );
            $this->fail('A second-level reply was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_old_edition_cannot_receive_new_interactions_after_republication(): void
    {
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();
        $oldRevision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $oldRevision);

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $author->user,
            'Edition two',
            ['body' => 'Second edition body'],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);

        try {
            app(AddSpaceContentAnnotation::class)->execute(
                $content,
                $oldRevision,
                $reader->user,
                'This must not attach to an obsolete edition.',
            );
            $this->fail('An obsolete edition accepted a comment.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('space_content_annotations', 0);
    }

    public function test_reader_component_posts_comment_reply_and_reaction_for_current_edition(): void
    {
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();

        $component = Livewire::actingAs($reader->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->set('commentBody', 'Reader comment')
            ->call('addComment')
            ->assertHasNoErrors()
            ->assertSee('Reader comment')
            ->call('toggleReaction', SpaceContentReaction::TYPE_LIKE)
            ->assertHasNoErrors();

        $comment = SpaceContentAnnotation::query()->sole();
        $component
            ->call('startReply', $comment->uuid)
            ->set('replyBody', 'Reader reply')
            ->call('addReply')
            ->assertHasNoErrors()
            ->assertSee('Reader reply');

        $this->assertDatabaseHas('space_content_reactions', [
            'actor_id' => $reader->id,
            'type' => SpaceContentReaction::TYPE_LIKE,
        ]);
        $this->assertDatabaseCount('space_content_annotations', 2);
    }

    public function test_discussion_from_previous_edition_is_not_rendered_on_new_reader_edition(): void
    {
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();
        $oldRevision = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $oldRevision);

        app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $oldRevision,
            $reader->user,
            'Comment on edition one',
        );

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $author->user,
            'Edition two',
            ['body' => 'Second edition body'],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);

        Livewire::actingAs($reader->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->assertSee('Edition two')
            ->assertDontSee('Comment on edition one');
    }

    /** @return array{Group, GroupSpace, Actor, Actor, Actor, SpaceContent} */
    private function fixture(): array
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $reader = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Interaction group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Reader interaction', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute($space, $owner, $owner->user, 'allow', 'manager');
        app(SetGroupSpaceParticipant::class)->execute($space, $author, $owner->user, 'allow', 'participant');
        app(SetGroupSpaceParticipant::class)->execute($space, $reader, $owner->user, 'allow', 'participant');

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
            'Edition one',
            ['body' => 'First edition body'],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);

        return [$group, $space, $owner, $author, $reader, $content];
    }
}
