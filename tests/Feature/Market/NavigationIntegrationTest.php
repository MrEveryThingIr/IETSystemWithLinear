<?php

namespace Tests\Feature\Market;

use App\Actions\Profile\EnsureActorProfile;
use App\Livewire\Intents\Composer;
use App\Livewire\Items\Index;
use App\Livewire\Items\SavedStructures;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\IntentDetailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class NavigationIntegrationTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_my_items_lists_by_status_and_every_edit_opens_the_composer(): void
    {
        $user = $this->user();
        $this->composer($user)->call('choose', 'offer')->set('core.title', 'Published one')->set('core.description', 'Text')->set('core.visibility', 'public')->call('publish');
        $this->composer($user)->call('choose', 'need')->set('core.title', 'Drafted one')->set('core.description', 'Text')->call('saveDraft');
        $published = ActorProfileIntent::query()->where('title', 'Published one')->sole();
        $draft = ActorProfileIntent::query()->where('title', 'Drafted one')->sole();

        $page = Livewire::actingAs($user)->test(Index::class);
        $page->assertSee('Published one')->assertDontSee('Drafted one')->assertSeeHtml('data-items-filter')
            ->assertSeeHtml(route('intents.compose.edit', $published))->assertDontSeeHtml('profile?intent=');
        $page->set('show', 'draft')->assertSee('Drafted one')->assertDontSee('Published one')->assertSeeHtml(route('intents.compose.edit', $draft));
        $page->set('show', 'nonsense')->call('$refresh')->assertSet('show', 'nonsense');
        Livewire::actingAs($user)->withQueryParams(['show' => 'nonsense'])->test(Index::class)->assertSet('show', 'active');
    }

    public function test_the_old_profile_editor_link_lands_in_the_composer_and_never_for_someone_elses_item(): void
    {
        $user = $this->user();
        $this->composer($user)->call('choose', 'offer')->set('core.title', 'Mine')->set('core.description', 'Text')->set('core.visibility', 'public')->call('publish');
        $mine = ActorProfileIntent::query()->sole();

        $this->actingAs($user)->get(route('profile.edit', ['intent' => $mine->uuid]))
            ->assertRedirect(route('intents.compose.edit', $mine));
        $this->actingAs($this->user())->get(route('profile.edit', ['intent' => $mine->uuid]))->assertOk();
    }

    public function test_saved_templates_and_groups_are_found_previewed_started_renamed_and_deleted(): void
    {
        $user = $this->user();
        $maker = $this->composer($user)->call('choose', 'offer')->call('chooseTopic', 'device')->call('addField', 'brand')->call('addField', 'color');
        $maker->set('templateName', 'Phone post')->call('saveTemplate', 'composition')->set('groupName', 'Look')->call('saveTemplate', 'group');
        $template = IntentDetailTemplate::query()->where('kind', 'composition')->sole();
        $group = IntentDetailTemplate::query()->where('kind', 'group')->sole();

        $page = Livewire::actingAs($user)->test(SavedStructures::class);
        $page->assertSee('Phone post')->assertSee('Look')->assertSeeHtml('data-saved-templates')->assertSeeHtml('data-saved-groups')
            ->assertSeeHtml(e(route('intents.compose', ['side' => 'offer', 'template' => $template->uuid])));
        $page->call('startRename', $template->uuid)->set('renameTo', 'Phone listing')->call('saveRename');
        $this->assertSame('Phone listing', $template->fresh()->name);
        $page->call('delete', $group->uuid);
        $this->assertDatabaseMissing('intent_detail_templates', ['id' => $group->id]);
        $this->assertDatabaseHas('intent_detail_templates', ['id' => $template->id]);

        $this->assertStringContainsString('data-saved-structures', $this->actingAs($user)->get(route('items.index'))->assertOk()->getContent());
        $stranger = $this->user();
        Livewire::actingAs($stranger)->test(SavedStructures::class)->call('delete', $template->uuid)->assertForbidden();
        Livewire::actingAs($stranger)->test(SavedStructures::class)->call('startRename', $template->uuid)->assertForbidden();
        $this->assertDatabaseHas('intent_detail_templates', ['id' => $template->id]);
    }

    public function test_starting_from_a_template_link_applies_it_once_and_ignores_foreign_templates(): void
    {
        $owner = $this->user();
        $this->composer($owner)->call('choose', 'offer')->call('chooseTopic', 'device')->call('addField', 'brand')->call('addField', 'color')
            ->set('templateName', 'Phone post')->call('saveTemplate', 'composition');
        $template = IntentDetailTemplate::query()->sole();

        $started = Livewire::actingAs($owner)->withQueryParams(['side' => 'offer', 'template' => $template->uuid])->test(Composer::class);
        $this->assertSame(['brand', 'color'], array_values(array_filter(array_column($started->get('details'), 'canonical_key'))));
        $started->assertSet('topic', 'device')->assertSet('template', '');

        $foreign = Livewire::actingAs($this->user())->withQueryParams(['template' => $template->uuid])->test(Composer::class);
        $this->assertSame([], $foreign->get('details'));
    }

    public function test_use_template_is_always_visible_when_starting_and_explains_when_nothing_is_saved(): void
    {
        $user = $this->user();
        $this->composer($user)->call('choose', 'need')->assertSeeHtml('data-use-template')->assertSeeHtml('data-template-empty')->assertSeeHtml('data-save-template');
    }

    private function user(): User
    {
        $actor = Actor::factory()->create();
        app(EnsureActorProfile::class)->execute($actor->user);
        $this->publishSurfaces($actor->user, ['market', 'profile']);
        $actor->user->forceFill(['email_verified_at' => now()])->save();

        return $actor->user;
    }

    private function composer(User $user): Testable
    {
        return Livewire::actingAs($user)->test(Composer::class);
    }
}
