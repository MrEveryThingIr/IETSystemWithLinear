<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Actions\Groups\TransitionGroupMembership;
use App\Actions\Groups\UpdateSpaceContentStructure;
use App\GroupRoleKey;
use App\Livewire\Groups\SpaceContentStructure;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentRevision;
use App\Models\SpaceContentRevisionRelationship;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SpaceContentStructureTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_structure_is_ordered_private_revision_and_survives_normal_text_revision(): void
    {
        [$group, $owner, $author] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner);
        $this->allow($space, $author, $owner);
        $definition = $this->activeDefinition($space, $owner);

        $parent = $this->content($space, $definition, $author, 'Book');
        $first = $this->content($space, $definition, $author, 'Lesson one');
        $second = $this->content($space, $definition, $author, 'Lesson two');
        $parent = app(PublishSpaceContent::class)->execute($parent, $author->user);
        $publishedParentRevisionId = $parent->active_revision_id;

        $parent = app(UpdateSpaceContentStructure::class)->execute(
            $parent,
            $author->user,
            [$second->id, $first->id],
        );

        $this->assertSame($publishedParentRevisionId, $parent->active_revision_id);
        $this->assertNotNull($parent->draft_revision_id);
        $this->assertSame(
            [$second->id, $first->id],
            $this->containedIds($parent->draftRevisionRecord()),
        );

        $parent = app(ReviseSpaceContent::class)->execute(
            $parent,
            $author->user,
            'Book revised',
            ['body' => 'updated text'],
        );

        $this->assertSame(
            [$second->id, $first->id],
            $this->containedIds($parent->draftRevisionRecord()),
        );
    }

    public function test_containment_cycles_are_rejected(): void
    {
        [$group, $owner, $author] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner);
        $this->allow($space, $author, $owner);
        $definition = $this->activeDefinition($space, $owner);

        $book = $this->content($space, $definition, $author, 'Book');
        $unit = $this->content($space, $definition, $author, 'Unit');

        app(UpdateSpaceContentStructure::class)->execute($book, $author->user, [$unit->id]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Content containment cannot create a cycle.');

        app(UpdateSpaceContentStructure::class)->execute($unit, $author->user, [$book->id]);
    }

    public function test_parent_publication_requires_sealed_published_children_and_snapshots_child_edition(): void
    {
        [$group, $owner, $author] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner);
        $this->allow($space, $author, $owner);
        $definition = $this->activeDefinition($space, $owner);

        $parent = $this->content($space, $definition, $author, 'Book');
        $child = $this->content($space, $definition, $author, 'Lesson');
        $parent = app(UpdateSpaceContentStructure::class)->execute($parent, $author->user, [$child->id]);

        try {
            app(PublishSpaceContent::class)->execute($parent, $author->user);
            $this->fail('Publishing should be blocked until contained Content is published.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $child = app(PublishSpaceContent::class)->execute($child, $author->user);
        $childRevision = $child->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $childRevision);
        $this->assertNotNull($childRevision->manifest_hash);

        $parent = app(PublishSpaceContent::class)->execute($parent, $author->user);
        $parentRevision = $parent->activeRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $parentRevision);
        $this->assertNotNull($parentRevision->manifest_hash);

        $relationship = SpaceContentRevisionRelationship::query()
            ->where('parent_revision_id', $parentRevision->id)
            ->where('child_content_id', $child->id)
            ->firstOrFail();

        $this->assertSame($childRevision->id, $relationship->child_revision_id);
        $this->assertSame($childRevision->manifest_hash, $relationship->child_manifest_hash);
        $this->assertNotNull($relationship->sealed_at);
    }

    public function test_structure_studio_can_add_and_save_child_content(): void
    {
        [$group, $owner, $author] = $this->groupWithMember();
        $space = $this->createSpace($group, $owner);
        $this->allow($space, $author, $owner);
        $definition = $this->activeDefinition($space, $owner);

        $parent = $this->content($space, $definition, $author, 'Course');
        $child = $this->content($space, $definition, $author, 'Lesson');

        Livewire::actingAs($author->user)
            ->test(SpaceContentStructure::class, [
                'group' => $group,
                'space' => $space,
                'content' => $parent,
            ])
            ->set('selectedChildId', (string) $child->id)
            ->call('addChild')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee(__('structure.saved'));

        $parent->refresh();
        $this->assertSame([$child->id], $this->containedIds($parent->draftRevisionRecord()));
    }

    /** @return array{Group, Actor, Actor, GroupMembership} */
    private function groupWithMember(): array
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Structure group', null);
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
        app(TransitionGroupMembership::class)->recordInitial($membership, $owner, 'Structure test member.');

        return [$member, $membership];
    }

    private function createSpace(Group $group, Actor $owner): GroupSpace
    {
        return app(CreateGroupSpace::class)->execute($group, $owner->user, 'Learning', 'restricted');
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
            'Learning Content',
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

    private function content(
        GroupSpace $space,
        SpaceContentDefinition $definition,
        Actor $author,
        string $title,
    ): SpaceContent {
        return app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            $title,
            ['body' => $title.' body'],
        );
    }

    /** @return list<int> */
    private function containedIds(?SpaceContentRevision $revision): array
    {
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        return $revision->relationships()
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->orderBy('position')
            ->pluck('child_content_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
