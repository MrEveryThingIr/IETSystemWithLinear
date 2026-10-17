<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\PublishSpaceContent;
use App\Livewire\Content\CreateContent;
use App\Livewire\Content\Workspace;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\Context;
use App\Models\SpaceContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class ContentWorkspaceTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_workspace_lists_my_content_with_human_views_and_hides_others_private_work(): void
    {
        $me = Actor::factory()->create();
        $other = Actor::factory()->create();
        $mine = app(EnsurePersonalContext::class)->execute($me->user);
        $theirs = app(EnsurePersonalContext::class)->execute($other->user);

        $draft = $this->make($me, $mine, 'note-diary', 'My draft note', ['body' => 'x']);
        $published = app(PublishSpaceContent::class)->execute(
            $this->make($me, $mine, 'lesson', 'My published lesson', ['objective' => null, 'body' => 'y']),
            $me->user,
        );
        $this->make($other, $theirs, 'note-diary', 'Someone else private note', ['body' => 'z']);
        $this->publishSurfaces($me->user, ['content']);

        Livewire::actingAs($me->user)
            ->test(Workspace::class)
            ->assertSee('My draft note')
            ->assertSee('My published lesson')
            ->assertDontSee('Someone else private note')
            ->call('setView', 'drafts')
            ->assertSee('My draft note')
            ->assertDontSee('My published lesson')
            ->call('setView', 'published')
            ->assertSee('My published lesson')
            ->assertDontSee('My draft note')
            ->call('setView', 'learning')
            ->assertSee('My published lesson')
            ->assertDontSee('My draft note')
            ->call('setView', 'all')
            ->set('search', 'lesson')
            ->assertSee('My published lesson')
            ->assertDontSee('My draft note');

        $this->assertSame('draft', $draft->status);
        $this->assertSame('published', $published->status);
    }

    public function test_page_is_reachable_through_the_content_route_and_respects_publication(): void
    {
        $actor = Actor::factory()->create();

        $this->actingAs($actor->user)->get(route('experience.content'))->assertForbidden();

        $this->publishSurfaces($actor->user, ['content']);
        $this->actingAs($actor->user)
            ->get(route('experience.content'))
            ->assertOk()
            ->assertSeeHtml('data-content-workspace')
            ->assertSee(__('content_workspace.create_button'));
    }

    public function test_create_dialog_uses_plain_types_and_opens_the_studio(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);

        $component = Livewire::actingAs($actor->user)
            ->test(CreateContent::class)
            ->call('openDialog')
            ->assertSee(__('content_workspace.kinds.note.name'))
            ->assertSee(__('content_workspace.kinds.course.name'))
            ->call('choose', 'lesson')
            ->assertSet('contextUuid', $context->uuid)
            ->set('title', 'First lesson')
            ->set('payload.body', 'We start here.')
            ->call('create')
            ->assertHasNoErrors();

        $content = SpaceContent::query()->where('context_id', $context->id)->sole();
        $component->assertRedirect(route('contexts.contents.studio', [$context, $content]));
        $this->assertSame('lesson', $content->blueprintVersion?->blueprint?->slug);
        $this->assertSame('draft', $content->status);
    }

    public function test_create_dialog_asks_for_required_fields(): void
    {
        $actor = Actor::factory()->create();
        app(EnsurePersonalContext::class)->execute($actor->user);

        Livewire::actingAs($actor->user)
            ->test(CreateContent::class)
            ->call('openDialog', 'note')
            ->set('title', 'Empty note')
            ->call('create')
            ->assertHasErrors('payload.body');

        $this->assertSame(0, SpaceContent::query()->count());
    }

    public function test_course_is_a_real_system_blueprint(): void
    {
        app(EnsureSystemContentBlueprints::class)->execute();

        $course = ContentBlueprint::query()->where('slug', 'course')->sole();
        $this->assertSame('learning', $course->category);
        $this->assertNotNull($course->activeVersionRecord());
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
