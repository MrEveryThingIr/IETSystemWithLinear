<?php

namespace Tests\Feature\Actors;

use App\Livewire\Actors\Create;
use App\Livewire\Actors\Edit;
use App\Livewire\Actors\Index;
use App\Livewire\Actors\Show;
use App\Models\Actor;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActorManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function paths(Actor $actor): array
    {
        return ['/actors', '/actors/create', '/actors/'.$actor->id, '/actors/'.$actor->id.'/edit'];
    }

    public function test_guests_cannot_access_actor_pages(): void
    {
        foreach ($this->paths(Actor::factory()->withoutUser()->create()) as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_unverified_users_cannot_access_actor_pages(): void
    {
        $this->actingAs(User::factory()->unverified()->create());
        foreach ($this->paths(Actor::factory()->withoutUser()->create()) as $path) {
            $this->get($path)->assertRedirect(route('verification.notice'));
        }
    }

    #[DataProvider('inactiveStatuses')]
    public function test_inactive_users_cannot_access_actor_pages(string $status): void
    {
        $user = User::factory()->create(['status' => $status]);
        foreach ($this->paths(Actor::factory()->withoutUser()->create()) as $path) {
            $this->actingAs($user)->get($path)->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public static function inactiveStatuses(): array
    {
        return ['suspended' => ['suspended'], 'closed' => ['closed']];
    }

    public function test_verified_active_users_can_view_all_actor_pages(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $actor = Actor::factory()->create();
        foreach ($this->paths($actor) as $path) {
            $this->get($path)->assertOk();
        }
        Livewire::test(Index::class)->assertSee($actor->user->username);
    }

    public function test_creates_accountless_actor(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Create::class)->call('save')->assertHasNoErrors();
        $this->assertNull(Actor::sole()->user_id);
    }

    public function test_creates_actor_linked_to_available_user(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(Create::class)->set('userId', (string) $user->id)->call('save')
            ->assertHasNoErrors()->assertRedirect(route('actors.show', Actor::sole()));
        $this->assertSame($user->id, Actor::sole()->user_id);
    }

    public function test_duplicate_associations_are_rejected_on_create_and_edit(): void
    {
        $actor = Actor::factory()->create();
        $other = Actor::factory()->withoutUser()->create();
        Livewire::actingAs($actor->user)->test(Create::class)->set('userId', (string) $actor->user_id)
            ->call('save')->assertHasErrors(['userId' => 'unique']);
        Livewire::test(Edit::class, ['actor' => $other])->set('userId', (string) $actor->user_id)
            ->call('save')->assertHasErrors(['userId' => 'unique']);
        $this->assertDatabaseCount('actors', 2);
        $this->assertNull($other->refresh()->user_id);
    }

    public function test_attach_change_and_detach_association(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $actor = Actor::factory()->withoutUser()->create();
        $component = Livewire::actingAs($user)->test(Edit::class, ['actor' => $actor]);
        $component->set('userId', (string) $user->id)->call('save')->assertHasNoErrors();
        $this->assertSame($user->id, $actor->refresh()->user_id);
        $component->call('save')->assertHasNoErrors();
        $component->set('userId', (string) $other->id)->call('save')->assertHasNoErrors();
        $this->assertSame($other->id, $actor->refresh()->user_id);
        $component->set('userId', '')->call('save')->assertHasNoErrors();
        $this->assertNull($actor->refresh()->user_id);
    }

    public function test_delete_leaves_the_associated_user_intact(): void
    {
        $actor = Actor::factory()->create();
        $user = $actor->user;
        Livewire::actingAs($user)->test(Show::class, ['actor' => $actor])->call('delete')
            ->assertRedirect(route('actors.index'));
        $this->assertModelMissing($actor);
        $this->assertModelExists($user);
    }

    public function test_nonexistent_association_is_rejected(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Create::class)->set('userId', '999')
            ->call('save')->assertHasErrors(['userId' => 'exists']);
        $this->assertDatabaseCount('actors', 0);
    }

    public function test_livewire_mutation_rechecks_verification_after_page_load(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $page = $this->actingAs($user)->get('/actors/create')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $user->email_verified_at = null;
        $user->save();
        $this->postJson(app('livewire')->getUpdateUri(), ['components' => [[
            'snapshot' => html_entity_decode($matches[1], ENT_QUOTES), 'updates' => [],
            'calls' => [['method' => 'save', 'params' => [], 'path' => '']],
        ]]], ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertDatabaseCount('actors', 0);
    }
}
