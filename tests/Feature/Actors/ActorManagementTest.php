<?php

namespace Tests\Feature\Actors;

use App\Livewire\Actors\Create;
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

    /** @return list<string> */
    private function paths(Actor $actor): array
    {
        return ['/actors', '/actors/create', '/actors/'.$actor->id];
    }

    public function test_guests_are_redirected_from_actor_administration(): void
    {
        $actor = Actor::factory()->withoutUser()->create();

        foreach ($this->paths($actor) as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_ordinary_verified_users_are_forbidden_from_actor_administration(): void
    {
        $user = User::factory()->create();
        $actor = Actor::factory()->withoutUser()->create();

        foreach ($this->paths($actor) as $path) {
            $this->actingAs($user)->get($path)->assertForbidden();
        }

        Livewire::actingAs($user)->test(Index::class)->assertForbidden();
        Livewire::actingAs($user)->test(Create::class)->assertForbidden();
        Livewire::actingAs($user)->test(Show::class, ['actor' => $actor])->assertForbidden();
    }

    public function test_unverified_superadmin_is_redirected_to_verification(): void
    {
        $user = User::factory()->unverified()->superadmin()->create();
        $actor = Actor::factory()->withoutUser()->create();

        foreach ($this->paths($actor) as $path) {
            $this->actingAs($user)->get($path)->assertRedirect(route('verification.notice'));
        }
    }

    #[DataProvider('inactiveStatuses')]
    public function test_inactive_superadmin_is_logged_out_of_actor_administration(string $status): void
    {
        $user = User::factory()->superadmin()->create(['status' => $status]);
        $actor = Actor::factory()->withoutUser()->create();

        foreach ($this->paths($actor) as $path) {
            $this->actingAs($user)->get($path)->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    /** @return array<string, array{string}> */
    public static function inactiveStatuses(): array
    {
        return ['suspended' => ['suspended'], 'closed' => ['closed']];
    }

    public function test_superadmin_can_view_actor_administration_and_navigation(): void
    {
        $this->withoutVite();
        $user = User::factory()->superadmin()->create();
        $actor = Actor::factory()->withoutUser()->create();

        foreach ($this->paths($actor) as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }

        $this->actingAs($user)->get('/dashboard')->assertSee(route('actors.index'));
        Livewire::actingAs($user)->test(Index::class)->assertSee((string) $actor->id);
    }

    public function test_ordinary_user_does_not_see_actor_navigation(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertDontSee(route('actors.index'));
    }

    public function test_superadmin_can_create_only_an_accountless_actor(): void
    {
        $user = User::factory()->superadmin()->create();

        Livewire::actingAs($user)->test(Create::class)
            ->assertDontSee($user->email)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('actors.show', Actor::query()->latest('id')->firstOrFail()));

        $createdActor = Actor::query()->latest('id')->firstOrFail();
        $this->assertNull($createdActor->user_id);
    }

    public function test_generic_identity_edit_route_does_not_exist(): void
    {
        $user = User::factory()->superadmin()->create();
        $actor = Actor::factory()->create();

        $this->actingAs($user)->get('/actors/'.$actor->id.'/edit')->assertNotFound();
    }

    public function test_revoked_grant_blocks_a_livewire_mutation_after_page_load(): void
    {
        $user = User::factory()->superadmin()->create();
        $grant = $user->platformAccessGrants()->firstOrFail();
        $component = Livewire::actingAs($user)->test(Create::class);

        $grant->revoked_at = now();
        $grant->revoked_by_user_id = $user->id;
        $grant->save();

        $component->call('save')->assertForbidden();
        $this->assertDatabaseCount('actors', 0);
    }

    public function test_superadmin_can_archive_an_accountless_actor_with_a_reason(): void
    {
        $user = User::factory()->superadmin()->create();
        $actor = Actor::factory()->withoutUser()->create();

        Livewire::actingAs($user)->test(Show::class, ['actor' => $actor])
            ->set('archiveReason', 'Duplicate synthetic participant created during setup.')
            ->call('archive')
            ->assertHasNoErrors()
            ->assertRedirect(route('actors.index'));

        $this->assertSame('archived', $actor->refresh()->status);
        $this->assertSame($user->id, $actor->archived_by_user_id);
    }

    public function test_superadmin_cannot_archive_an_active_users_primary_actor(): void
    {
        $user = User::factory()->superadmin()->create();
        $actor = Actor::factory()->create();

        Livewire::actingAs($user)->test(Show::class, ['actor' => $actor])
            ->set('archiveReason', 'Attempted hostile identity deactivation.')
            ->call('archive')
            ->assertForbidden();

        $this->assertSame('active', $actor->refresh()->status);
    }
}
