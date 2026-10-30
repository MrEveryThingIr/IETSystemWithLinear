<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Content\SetContentPublicAccess;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Livewire\Content\SharePanel;
use App\Livewire\Content\Workspace;
use App\Models\Actor;
use App\Models\ActorBlock;
use App\Models\ActorContact;
use App\Models\ContentBlueprint;
use App\Models\DirectContext;
use App\Models\SpaceContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/** "My contacts" as a Content audience: accepted mutual contacts only, enforced at every reading and media endpoint. */
class ContactsContentTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function person(): Actor
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['content', 'messages']);

        return $actor;
    }

    private function contact(Actor $a, Actor $b, string $status = ActorContact::ACCEPTED): ActorContact
    {
        [$one, $two] = DirectContext::pair($a->id, $b->id);

        return ActorContact::query()->create(['actor_one_id' => $one, 'actor_two_id' => $two, 'requested_by_actor_id' => $a->id, 'status' => $status]);
    }

    /** @return array{0: Actor, 1: SpaceContent} an author's published diary entry with a photo, shared with contacts */
    private function sharedEntry(): array
    {
        $author = $this->person();
        $context = app(EnsurePersonalContext::class)->execute($author->user);
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'article')->firstOrFail()->activeVersionRecord();
        $content = app(CreateContentFromBlueprint::class)->execute($context, $version, $author->user, 'Day 1 diary', ['summary' => 'Read five pages.', 'body' => 'Notes.']);
        $content = app(AttachAssetToSpaceContent::class)->execute($content->fresh(), $author->user, UploadedFile::fake()->image('page.png', 20, 20), 'owned');
        $content = app(PublishSpaceContent::class)->execute($content->fresh(), $author->user);
        app(SetContentPublicAccess::class)->execute($content, $author->user, SpaceContent::ACCESS_CONTACTS);

        return [$author, $content->fresh()];
    }

    private function mediaUrl(SpaceContent $content): string
    {
        $asset = $content->activeRevisionRecord()->assets()->firstOrFail();

        return route('contexts.contents.assets.show', [$content->context, $content, $asset]);
    }

    public function test_accepted_contacts_read_and_see_media_everyone_else_is_refused(): void
    {
        [$a, $content] = $this->sharedEntry();
        $b = $this->person();
        $c = $this->person();
        $this->contact($a, $b);
        $read = route('contexts.contents.show', [$content->context, $content]);

        $this->actingAs($b->user)->get($read)->assertOk()->assertSee('Day 1 diary');
        $this->actingAs($b->user)->get($this->mediaUrl($content))->assertOk();
        Livewire::actingAs($b->user)->test(Workspace::class)->set('view', 'contacts')->assertSee('Day 1 diary')->assertSee($a->user->username);

        $this->actingAs($c->user)->get($read)->assertForbidden();
        $this->actingAs($c->user)->get($this->mediaUrl($content))->assertForbidden();
        Livewire::actingAs($c->user)->test(Workspace::class)->set('view', 'contacts')->assertDontSee('Day 1 diary');
        auth()->logout();
        $this->get(route('public-content.show', $content))->assertNotFound();
    }

    public function test_pending_declined_removed_and_blocked_contacts_lose_or_never_get_access(): void
    {
        [$a, $content] = $this->sharedEntry();
        $read = route('contexts.contents.show', [$content->context, $content]);
        $pending = $this->person();
        $this->contact($a, $pending, ActorContact::PENDING);
        $declined = $this->person();
        $this->contact($a, $declined, ActorContact::DECLINED);
        $blocked = $this->person();
        $this->contact($a, $blocked);
        ActorBlock::query()->create(['blocker_actor_id' => $a->id, 'blocked_actor_id' => $blocked->id]);
        foreach ([$pending, $declined, $blocked] as $who) {
            $this->actingAs($who->user)->get($read)->assertForbidden();
            $this->actingAs($who->user)->get($this->mediaUrl($content))->assertForbidden();
        }

        $b = $this->person();
        $link = $this->contact($a, $b);
        $this->actingAs($b->user)->get($read)->assertOk();
        $link->update(['status' => ActorContact::DECLINED]); // the author removes B
        $this->actingAs($b->user)->get($read)->assertForbidden();
        $this->actingAs($b->user)->get($this->mediaUrl($content))->assertForbidden();
    }

    public function test_only_the_published_edition_is_shared_and_only_while_it_is_contacts_only(): void
    {
        [$a, $content] = $this->sharedEntry();
        $b = $this->person();
        $this->contact($a, $b);
        $read = route('contexts.contents.show', [$content->context, $content]);

        app(ReviseSpaceContent::class)->execute($content->fresh(), $a->user, 'Secret draft title', ['summary' => 'Unfinished.', 'body' => 'Draft body.']);
        $this->actingAs($b->user)->get($read)->assertOk()->assertSee('Day 1 diary')->assertDontSee('Secret draft title');
        $this->actingAs($b->user)->get($read.'?preview=draft')->assertOk()->assertDontSee('Secret draft title');

        app(SetContentPublicAccess::class)->execute($content->fresh(), $a->user, SpaceContent::ACCESS_PRIVATE);
        $this->actingAs($b->user)->get($read)->assertForbidden();
    }

    public function test_the_author_chooses_only_me_my_contacts_or_public_and_groups_cannot_use_contacts(): void
    {
        [$a, $content] = $this->sharedEntry();
        Livewire::actingAs($a->user)->test(SharePanel::class, ['content' => $content])->assertSeeHtml('data-share-contacts-state="contacts"')
            ->call('setContacts', false)->assertSeeHtml('data-share-contacts-state="off"');
        $this->assertSame(SpaceContent::ACCESS_PRIVATE, $content->fresh()->public_access);
        $this->assertDatabaseHas('space_content_lifecycle_events', ['space_content_id' => $content->id, 'event_type' => 'opened_to_contacts']);
    }
}
