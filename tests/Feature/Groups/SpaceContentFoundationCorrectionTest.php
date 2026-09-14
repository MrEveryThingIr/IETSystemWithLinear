<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\DiscardSpaceContentDefinitionDraft;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\ReviseSpaceContentDefinition;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Actions\Groups\TransitionGroupMembership;
use App\GroupRoleKey;
use App\Livewire\Groups\SpaceContentReader;
use App\Livewire\Groups\SpaceContentShow;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpaceContentFoundationCorrectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_draft_content_is_visible_only_to_author_or_space_manager(): void
    {
        [$group, $owner, $author] = $this->groupWithMember();
        [$reader] = $this->addMember($group, $owner);
        $space = $this->createSpace($group, $owner);
        $this->allow($space, $author, $owner);
        $this->allow($space, $reader, $owner);
        $definition = $this->activeDefinition($space, $owner);

        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            'Private working draft',
            $this->payload('draft'),
        );

        Livewire::actingAs($reader->user)
            ->test(SpaceContentShow::class, compact('group', 'space', 'content'))
            ->assertStatus(403);

        Livewire::actingAs($author->user)
            ->test(SpaceContentShow::class, compact('group', 'space', 'content'))
            ->assertOk()
            ->assertSee('Private working draft');
    }

    public function test_ordinary_viewer_never_receives_unpublished_revision_history(): void
    {
        [$group, $owner, $author] = $this->groupWithMember();
        [$reader] = $this->addMember($group, $owner);
        $space = $this->createSpace($group, $owner);
        $this->allow($space, $author, $owner);
        $this->allow($space, $reader, $owner);
        $definition = $this->activeDefinition($space, $owner);

        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            'Published edition one',
            $this->payload('edition one'),
        );
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $published = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $published);

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $author->user,
            'Secret draft edition two',
            $this->payload('unpublished secret'),
        );
        $draft = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $draft);

        Livewire::actingAs($reader->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->assertOk()
            ->assertSee('Published edition one')
            ->assertDontSee('Secret draft edition two')
            ->assertDontSee($draft->content_hash);

        Livewire::actingAs($author->user)
            ->test(SpaceContentShow::class, compact('group', 'space', 'content'))
            ->assertOk()
            ->assertSee('Secret draft edition two')
            ->assertSee($published->content_hash)
            ->assertSee($draft->content_hash);
    }

    public function test_active_definition_remains_usable_while_replacement_draft_is_edited(): void
    {
        [$group, $owner, $member] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner);
        $this->allow($space, $member, $owner);
        $definition = $this->activeDefinition($space, $owner);
        $active = $definition->activeVersionRecord();

        $definition = app(ReviseSpaceContentDefinition::class)->execute($definition, $owner->user);
        $draft = $definition->draftVersionRecord();

        $this->assertNotNull($active);
        $this->assertNotNull($draft);
        $this->assertSame($active->id, $definition->active_version_id);
        $this->assertSame($draft->id, $definition->draft_version_id);
        $this->assertNull($draft->published_at);

        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $member->user,
            'Created while replacement schema is draft',
            $this->payload('still active'),
        );

        $this->assertSame($active->id, $content->draftRevisionRecord()?->definition_version_id);
    }

    public function test_publishing_new_edition_swaps_pointer_without_mutating_previous_edition(): void
    {
        [$group, $owner, $author] = $this->groupWithMember();
        [$reader] = $this->addMember($group, $owner);
        $space = $this->createSpace($group, $owner);
        $this->allow($space, $author, $owner);
        $this->allow($space, $reader, $owner);
        $definition = $this->activeDefinition($space, $owner);

        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            'Edition one',
            $this->payload('one'),
        );
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $editionOne = $content->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $editionOne);
        $editionOneHash = $editionOne->content_hash;

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $author->user,
            'Edition two draft',
            $this->payload('two'),
        );
        $draft = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $draft);
        $this->assertSame($editionOne->id, $content->active_revision_id);

        Livewire::actingAs($reader->user)
            ->test(SpaceContentReader::class, compact('group', 'space', 'content'))
            ->assertSee('Edition one')
            ->assertDontSee('Edition two draft');

        $content = app(PublishSpaceContent::class)->execute($content, $author->user);

        $this->assertSame($draft->id, $content->active_revision_id);
        $this->assertNull($content->draft_revision_id);
        $this->assertSame($editionOneHash, $editionOne->fresh()->content_hash);
        $this->assertSame('Edition one', $editionOne->fresh()->title);
    }

    public function test_only_unused_unpublished_replacement_definition_draft_can_be_discarded(): void
    {
        [$group, $owner] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner);
        $definition = $this->activeDefinition($space, $owner);
        $definition = app(ReviseSpaceContentDefinition::class)->execute($definition, $owner->user);
        $draft = $definition->draftVersionRecord();

        $this->assertNotNull($draft);

        $definition = app(DiscardSpaceContentDefinitionDraft::class)->execute($definition, $owner->user);

        $this->assertNull($definition->draft_version_id);
        $this->assertSame($definition->active_version_id, $definition->activeVersionRecord()?->id);
        $this->assertDatabaseMissing('space_content_definition_versions', ['id' => $draft->id]);
    }

    /** @return array{Group, Actor, Actor, GroupMembership} */
    private function groupWithMember(): array
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Foundation group', null);
        [$member, $membership] = $this->addMember($group, $owner);

        return [$group, $owner, $member, $membership];
    }

    /** @return array{Actor, GroupMembership} */
    private function addMember(Group $group, Actor $owner): array
    {
        $member = Actor::factory()->create();
        $membership = $group->memberships()->create([
            'actor_id' => $member->id,
            'status' => 'active',
        ]);

        $roles = app(GroupRoleProvisioner::class);
        $roles->grant($member, $group, $roles->builtInRole($group, GroupRoleKey::Member));
        app(TransitionGroupMembership::class)->recordInitial($membership, $owner, 'Foundation correction test member.');

        return [$member, $membership];
    }

    private function createSpace(Group $group, Actor $owner): GroupSpace
    {
        return app(CreateGroupSpace::class)->execute($group, $owner->user, 'Engineering', 'restricted');
    }

    private function allow(GroupSpace $space, Actor $actor, Actor $grantor): void
    {
        app(SetGroupSpaceParticipant::class)->execute($space, $actor, $grantor->user, 'allow', 'participant');
    }

    private function activeDefinition(GroupSpace $space, Actor $manager): SpaceContentDefinition
    {
        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $manager->user,
            'Daily Note',
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

        return app(ActivateSpaceContentDefinition::class)->execute($definition, $manager->user);
    }

    /** @return array<string, mixed> */
    private function payload(string $body): array
    {
        return ['body' => $body];
    }
}
