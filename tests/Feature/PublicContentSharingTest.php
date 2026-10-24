<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\CreateContentInvitation;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Content\RevokeContentInvitation;
use App\Actions\Content\SetContentPublicAccess;
use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Contexts\EnsureReferenceContext;
use App\Actions\Groups\ArchiveSpaceContent;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\UpdateSpaceContentStructure;
use App\Livewire\Content\SharePanel;
use App\Livewire\Contexts\ContentShow;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\ContentInvitation;
use App\Models\Context;
use App\Models\SpaceContent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicContentSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_published_content_is_private_to_guests_until_its_author_opens_it(): void
    {
        [$author, , $content] = $this->publishedArticle('Field notes from Shiraz');

        $this->get(route('public-content.show', $content))->assertNotFound();
        $this->get(route('public-content.index'))->assertOk()->assertDontSee('Field notes from Shiraz');

        app(SetContentPublicAccess::class)->execute($content, $author->user, SpaceContent::ACCESS_PUBLIC);

        $this->assertGuest();
        $this->get(route('public-content.show', $content))
            ->assertOk()
            ->assertSee('Field notes from Shiraz')
            ->assertSee('The garden was quiet at dawn.')
            ->assertSee('<link rel="canonical" href="'.route('public-content.show', $content).'">', false)
            ->assertDontSee('noindex');
        $this->get(route('public-content.index'))->assertOk()->assertSee('Field notes from Shiraz')->assertSee('data-public-card', false);
        $this->get('/')->assertOk()->assertSee('data-home-public', false)->assertSee('Field notes from Shiraz');

        $this->assertDatabaseHas('space_content_lifecycle_events', [
            'space_content_id' => $content->id,
            'event_type' => 'opened_to_public',
        ]);

        // Signed-in people who cannot see the place read the same public page.
        $stranger = Actor::factory()->create();
        $this->actingAs($stranger->user)->get(route('public-content.show', $content))->assertOk()->assertSee('Field notes from Shiraz');
    }

    public function test_guests_only_ever_see_the_published_edition(): void
    {
        [$author, , $content] = $this->publishedArticle('Published title');
        app(SetContentPublicAccess::class)->execute($content, $author->user, SpaceContent::ACCESS_PUBLIC);

        app(ReviseSpaceContent::class)->execute($content->fresh(), $author->user, 'Secret draft title', [
            'summary' => 'Draft summary that is not ready.',
            'body' => 'Draft body.',
        ]);

        $this->get(route('public-content.show', $content))
            ->assertOk()
            ->assertSee('Published title')
            ->assertDontSee('Secret draft title')
            ->assertDontSee('Draft summary that is not ready.');
    }

    public function test_closing_archiving_or_turning_off_the_index_hide_public_content_as_expected(): void
    {
        [$author, , $content] = $this->publishedArticle('Soon private');
        app(SetContentPublicAccess::class)->execute($content, $author->user, SpaceContent::ACCESS_PUBLIC);

        config()->set('public_content.index', false);
        $this->get(route('public-content.index'))->assertNotFound();
        $this->get(route('public-content.show', $content))->assertOk();
        $this->get('/')->assertOk()->assertDontSee('data-home-public', false);
        config()->set('public_content.index', true);

        app(SetContentPublicAccess::class)->execute($content, $author->user, SpaceContent::ACCESS_PRIVATE);
        $this->get(route('public-content.show', $content))->assertNotFound();

        app(SetContentPublicAccess::class)->execute($content, $author->user, SpaceContent::ACCESS_PUBLIC);
        app(ArchiveSpaceContent::class)->execute($content->fresh(), $author->user, 'Retired');
        $this->get(route('public-content.show', $content))->assertNotFound();
        $this->get(route('public-content.index'))->assertDontSee('Soon private');
    }

    public function test_only_the_author_can_open_content_and_never_drafts_or_closed_places(): void
    {
        [$author, $context, $content] = $this->publishedArticle('Mine to share');
        $other = Actor::factory()->create();

        $this->assertFalse(Gate::forUser($other->user)->allows('share', $content));
        $this->expectDenied(fn () => app(SetContentPublicAccess::class)->execute($content, $other->user, SpaceContent::ACCESS_PUBLIC));
        $this->expectDenied(fn () => app(CreateContentInvitation::class)->execute($content, $other->user, 'Sara'));

        $draft = $this->article($author, $context, 'Still a draft');
        $this->assertFalse(Gate::forUser($author->user)->allows('share', $draft));
        $this->expectDenied(fn () => app(SetContentPublicAccess::class)->execute($draft, $author->user, SpaceContent::ACCESS_PUBLIC));

        // A group's manager may open their own group post.
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Reading circle', null);
        $groupContext = app(EnsureGroupSpaceContext::class)->execute($group->spaces()->sole());
        $groupPost = app(PublishSpaceContent::class)->execute($this->article($owner, $groupContext, 'Circle notes'), $owner->user);
        $this->assertTrue(Gate::forUser($owner->user)->allows('share', $groupPost));

        // Shared reference places are never opened to the web.
        $manager = Actor::factory()->create();
        $reference = app(EnsureReferenceContext::class)->execute($manager->user, 'system-reference');
        $referencePost = app(PublishSpaceContent::class)->execute($this->article($manager, $reference, 'Reference page'), $manager->user);
        $this->assertFalse(Gate::forUser($manager->user)->allows('share', $referencePost));
    }

    public function test_public_media_is_served_to_guests_only_from_the_published_edition(): void
    {
        [$author, , $content] = $this->publishedArticle('Video lesson');
        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content->fresh(),
            $author->user,
            new UploadedFile(base_path('tests/Fixtures/media/lesson-clip.mp4'), 'lesson-clip.mp4', 'video/mp4', null, true),
            'owned',
        );
        $asset = $content->draftRevisionRecord()->assets()->sole();

        // Attached in a draft only: not part of what guests see yet.
        app(SetContentPublicAccess::class)->execute($content, $author->user, SpaceContent::ACCESS_PUBLIC);
        $this->get(route('public-content.assets.show', [$content, $asset]))->assertNotFound();

        $content = app(PublishSpaceContent::class)->execute($content->fresh(), $author->user);
        $this->get(route('public-content.show', $content))
            ->assertOk()
            ->assertSee(route('public-content.assets.show', [$content, $asset]), false)
            ->assertDontSee(route('contexts.contents.assets.show', [$content->context, $content, $asset]), false);

        $this->get(route('public-content.assets.show', [$content, $asset]), ['Range' => 'bytes=0-9'])
            ->assertStatus(206)
            ->assertHeader('Content-Type', 'video/mp4');

        // The same asset cannot be fetched through some other public post.
        [$other, , $otherContent] = $this->publishedArticle('Unrelated');
        app(SetContentPublicAccess::class)->execute($otherContent, $other->user, SpaceContent::ACCESS_PUBLIC);
        $this->get(route('public-content.assets.show', [$otherContent, $asset]))->assertNotFound();

        app(SetContentPublicAccess::class)->execute($content, $author->user, SpaceContent::ACCESS_PRIVATE);
        $this->get(route('public-content.assets.show', [$content, $asset]))->assertNotFound();
    }

    public function test_invitation_lets_one_guest_read_private_content_with_the_authors_note(): void
    {
        [$author, , $content] = $this->publishedArticle('For your eyes');
        $invitation = app(CreateContentInvitation::class)->execute($content, $author->user, 'Maryam', 'Have a look before Friday.', 7);

        $this->get(route('public-content.show', $content))->assertNotFound();

        $response = $this->get($invitation->url());
        $response->assertOk()
            ->assertSee('For your eyes')
            ->assertSee('Maryam')
            ->assertSee('Have a look before Friday.')
            ->assertSee('data-invitation-note', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->get($invitation->url())->assertOk();
        $invitation->refresh();
        $this->assertSame(2, $invitation->view_count);
        $this->assertNotNull($invitation->first_viewed_at);

        // Only a hash is stored for lookup; the readable token is encrypted.
        $this->assertDatabaseMissing('content_invitations', ['token_hash' => $invitation->token()]);
        $this->assertNotSame($invitation->token(), $invitation->getRawOriginal('token_encrypted'));

        $this->get(route('content-invitations.show', str_repeat('a', 40)))->assertNotFound();
        $this->get('/read/short')->assertNotFound();

        app(RevokeContentInvitation::class)->execute($invitation, $author->user);
        $this->get($invitation->url())->assertStatus(410)->assertSee('data-invitation-unavailable="revoked"', false);
    }

    public function test_expired_invitations_stop_working_and_their_media_too(): void
    {
        [$author, , $content] = $this->publishedArticle('Time limited');
        $content = app(AttachAssetToSpaceContent::class)->execute($content->fresh(), $author->user, UploadedFile::fake()->image('map.png', 20, 20), 'owned');
        $content = app(PublishSpaceContent::class)->execute($content->fresh(), $author->user);
        $asset = $content->activeRevisionRecord()->assets()->sole();

        $invitation = app(CreateContentInvitation::class)->execute($content, $author->user, 'Reza', null, 1);
        $assetUrl = route('content-invitations.assets.show', [$invitation->token(), $content, $asset]);
        $this->get($invitation->url())->assertOk()->assertSee($assetUrl, false);
        $this->get($assetUrl)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->travel(2)->days();

        $this->get($invitation->url())->assertStatus(410)->assertSee('data-invitation-unavailable="expired"', false);
        $this->get($assetUrl)->assertNotFound();
    }

    public function test_course_invitation_opens_its_own_lessons_but_never_someone_elses_private_work(): void
    {
        $author = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($author->user);
        $publish = app(PublishSpaceContent::class);

        $first = $publish->execute($this->make($author, $context, 'lesson', 'Lesson one', ['objective' => null, 'body' => 'First steps.']), $author->user);
        $second = $publish->execute($this->make($author, $context, 'lesson', 'Lesson two', ['objective' => null, 'body' => 'Next steps.']), $author->user);

        $course = $this->make($author, $context, 'course', 'Persian for travellers', ['summary' => 'Ten short lessons.']);
        $course = app(UpdateSpaceContentStructure::class)->execute($course, $author->user, [$first->id, $second->id]);
        $course = $publish->execute($course, $author->user);

        $invitation = app(CreateContentInvitation::class)->execute($course, $author->user, 'Lena');

        $this->get($invitation->url())
            ->assertOk()
            ->assertSee('data-public-children', false)
            ->assertSee('Lesson one')
            ->assertSee(route('content-invitations.content', [$invitation->token(), $first]), false);

        $this->get(route('content-invitations.content', [$invitation->token(), $second]))
            ->assertOk()
            ->assertSee('Next steps.')
            ->assertSee('data-reader-course-nav', false)
            ->assertSee('Lesson one');

        // Unrelated private content is not reachable through the course link.
        [, , $elsewhere] = $this->publishedArticle('Someone else entirely');
        $this->get(route('content-invitations.content', [$invitation->token(), $elsewhere]))->assertNotFound();

        // Opening the root through the child route lands on the invitation page.
        $this->get(route('content-invitations.content', [$invitation->token(), $course]))->assertRedirect($invitation->url());
    }

    public function test_share_panel_lets_the_author_open_invite_and_revoke(): void
    {
        [$author, $context, $content] = $this->publishedArticle('Shared from the reader');

        Livewire::actingAs($author->user)
            ->test(ContentShow::class, ['context' => $context, 'content' => $content])
            ->assertSeeHtml('data-reader-share')
            ->assertSeeHtml('data-drawer="share"');

        $panel = Livewire::actingAs($author->user)
            ->test(SharePanel::class, ['content' => $content])
            ->assertSeeHtml('data-share-make-public')
            ->call('setPublic', true)
            ->assertSeeHtml('data-share-public-url')
            ->assertSee(route('public-content.show', $content))
            ->set('inviteeName', 'Arash')
            ->set('inviteMessage', 'Chapter two is the one.')
            ->set('expiresInDays', '30')
            ->call('createInvitation')
            ->assertHasNoErrors()
            ->assertSee(__('public_content.share.created', ['name' => 'Arash']))
            ->assertSeeHtml('data-share-invitation="active"');

        $invitation = ContentInvitation::query()->where('space_content_id', $content->id)->sole();
        $this->assertSame('Arash', $invitation->invitee_name);
        $this->assertTrue($invitation->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $panel->assertSee($invitation->url());

        $panel->call('revoke', $invitation->uuid)->assertSeeHtml('data-share-invitation="revoked"');
        $this->assertNotNull($invitation->fresh()->revoked_at);

        $panel->set('inviteeName', '')->call('createInvitation')->assertHasErrors('inviteeName');

        $panel->call('setPublic', false)->assertSeeHtml('data-share-make-public');
        $this->assertSame(SpaceContent::ACCESS_PRIVATE, $content->fresh()->public_access);

        // Someone who cannot see the place cannot open the panel.
        $stranger = Actor::factory()->create();
        Livewire::actingAs($stranger->user)->test(SharePanel::class, ['content' => $content])->assertForbidden();
    }

    private function expectDenied(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected the action to be denied.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return array{Actor, Context, SpaceContent} */
    private function publishedArticle(string $title): array
    {
        $author = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($author->user);
        $content = app(PublishSpaceContent::class)->execute($this->article($author, $context, $title), $author->user);

        return [$author, $context, $content];
    }

    private function article(Actor $author, Context $context, string $title): SpaceContent
    {
        return $this->make($author, $context, 'article', $title, [
            'summary' => 'The garden was quiet at dawn.',
            'body' => 'A longer account of the morning.',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function make(Actor $actor, Context $context, string $slug, string $title, array $payload): SpaceContent
    {
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', $slug)->firstOrFail()->activeVersionRecord();
        $this->assertNotNull($version);

        return app(CreateContentFromBlueprint::class)->execute($context, $version, $actor->user, $title, $payload);
    }
}
