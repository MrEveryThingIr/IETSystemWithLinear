<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentEvidenceReference;
use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Content\PlacePublishedContent;
use App\Actions\Content\RemoveContentPlacement;
use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Livewire\Content\Library;
use App\Models\Actor;
use App\Models\ContentPlacement;
use App\Models\Context;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class PublishedContentLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_placement_grants_published_read_access_without_source_or_studio_authority(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        [$content, $source] = $this->publishedArticle($alice, 'Riverside Lot overview');
        $target = $this->groupContext($alice, $bob, 'Maple Housing Office');

        $this->assertFalse(Gate::forUser($bob->user)->allows('view', $source));
        $this->assertFalse(Gate::forUser($bob->user)->allows('view', $content));

        $placement = app(PlacePublishedContent::class)->execute($content, $target, $alice->user);

        $this->assertSame(ContentPlacement::STATUS_ACTIVE, $placement->status);
        $this->assertTrue(Gate::forUser($bob->user)->allows('view', $content));
        $this->assertFalse(Gate::forUser($bob->user)->allows('revisions', $content));
        $this->assertFalse(Gate::forUser($bob->user)->allows('update', $content));

        Livewire::actingAs($bob->user)
            ->test(Library::class)
            ->assertSee('Riverside Lot overview')
            ->assertSee('Article');

        $this->actingAs($bob->user)
            ->get(route('contexts.contents.show', [$source, $content]))
            ->assertOk();
    }

    public function test_placement_access_cannot_be_transitively_reshared_by_a_reader(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        [$content, $source] = $this->publishedArticle($alice, 'Riverside construction brief');
        $firstTarget = $this->groupContext($alice, $bob, 'Maple Housing Office');
        $secondTarget = $this->groupContext($bob, null, 'Bob Projects');

        app(PlacePublishedContent::class)->execute($content, $firstTarget, $alice->user);

        $this->assertTrue(Gate::forUser($bob->user)->allows('view', $content));
        $this->assertFalse(Gate::forUser($bob->user)->allows('view', $source));
        $this->assertTrue(Gate::forUser($bob->user)->allows('manageContent', $secondTarget));

        $this->expectException(AuthorizationException::class);

        app(PlacePublishedContent::class)->execute($content, $secondTarget, $bob->user);
    }

    public function test_removing_a_placement_revokes_target_only_access_and_replacing_reactivates_same_identity(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        [$content] = $this->publishedArticle($alice, 'Riverside media album');
        $target = $this->groupContext($alice, $bob, 'Maple Housing Office');

        $placement = app(PlacePublishedContent::class)->execute($content, $target, $alice->user);
        $this->assertTrue(Gate::forUser($bob->user)->allows('view', $content));

        app(RemoveContentPlacement::class)->execute($placement, $alice->user);

        $this->assertSame(ContentPlacement::STATUS_REMOVED, $placement->fresh()->status);
        $this->assertFalse(Gate::forUser($bob->user)->allows('view', $content->fresh()));

        $reactivated = app(PlacePublishedContent::class)->execute($content->fresh(), $target, $alice->user);

        $this->assertSame($placement->id, $reactivated->id);
        $this->assertSame(ContentPlacement::STATUS_ACTIVE, $reactivated->status);
        $this->assertTrue(Gate::forUser($bob->user)->allows('view', $content->fresh()));
    }

    public function test_placement_follows_latest_publication_while_evidence_reference_stays_on_exact_old_revision(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        [$content] = $this->publishedArticle($alice, 'Riverside plan v1');
        $target = $this->groupContext($alice, $bob, 'Maple Housing Office');
        app(PlacePublishedContent::class)->execute($content, $target, $alice->user);

        $oldRevision = $content->activeRevisionRecord();
        $this->assertNotNull($oldRevision);

        $evidence = app(CreateContentEvidenceReference::class)->execute(
            $content,
            $oldRevision,
            $bob->user,
        );

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $alice->user,
            'Riverside plan v2',
            [
                'summary' => 'Updated plan',
                'body' => 'The current published plan has changed.',
            ],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $alice->user);

        $this->assertSame('Riverside plan v2', $content->activeRevisionRecord()?->title);
        $this->assertTrue(Gate::forUser($bob->user)->allows('view', $content));
        $this->assertSame($oldRevision->id, $evidence->fresh()->space_content_revision_id);
        $this->assertSame('Riverside plan v1', $evidence->revision()->firstOrFail()->title);
    }

    /** @return array{SpaceContent, Context} */
    private function publishedArticle(Actor $author, string $title): array
    {
        $context = app(EnsurePersonalContext::class)->execute($author->user);
        $blueprint = app(EnsureSystemContentBlueprints::class)
            ->execute()
            ->firstWhere('slug', 'article');
        $this->assertNotNull($blueprint);

        $version = $blueprint->activeVersionRecord();
        $this->assertNotNull($version);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $author->user,
            $title,
            [
                'summary' => 'Riverside Home example',
                'body' => 'Alice documents the Riverside case once and reuses the published artifact by reference.',
            ],
        );

        return [
            app(PublishSpaceContent::class)->execute($content, $author->user),
            $context,
        ];
    }

    private function groupContext(Actor $owner, ?Actor $member, string $name): Context
    {
        $group = app(CreateGroup::class)->execute($owner, $name, null);
        $space = GroupSpace::query()
            ->where('group_id', $group->id)
            ->where('is_default', true)
            ->firstOrFail();

        if ($member instanceof Actor) {
            GroupMembership::query()->create([
                'group_id' => $group->id,
                'actor_id' => $member->id,
                'role' => 'member',
                'status' => 'active',
            ]);

            $roles = app(GroupRoleProvisioner::class)->provision($group);
            app(GroupRoleProvisioner::class)->grant($member, $group, $roles['member']);
        }

        return app(EnsureGroupSpaceContext::class)->execute($space);
    }
}
