<?php

namespace Tests\Feature\Groups;

use App\Actions\Platform\RequestPlatformAccess;
use App\Livewire\Groups\Index;
use App\Models\Actor;
use App\Models\User;
use App\PlatformCapability;
use App\PlatformRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupCreationAccessReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_superadmin_can_review_group_creation_access_from_groups_page(): void
    {
        $requester = User::factory()->create();
        Actor::factory()->for($requester)->create();
        $administrator = User::factory()->superadmin()->create();
        Actor::factory()->for($administrator)->create();

        $request = app(RequestPlatformAccess::class)->execute(
            $requester,
            PlatformRole::GroupCreator,
            'I need to create a project group.',
        );

        Livewire::actingAs($administrator)
            ->test(Index::class)
            ->assertSee('Pending group-creation access requests')
            ->assertSee($requester->username)
            ->assertSee('I need to create a project group.')
            ->call('reviewGroupCreationAccess', $request->id, true)
            ->assertStatus(200);

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertTrue($requester->fresh()->hasPlatformCapability(PlatformCapability::CreateGroups));
    }

    public function test_pending_group_creation_request_is_visible_to_requester_as_pending(): void
    {
        $requester = User::factory()->create();
        Actor::factory()->for($requester)->create();

        app(RequestPlatformAccess::class)->execute($requester, PlatformRole::GroupCreator);

        Livewire::actingAs($requester)
            ->test(Index::class)
            ->assertSee('Access request pending')
            ->assertDontSee('Pending group-creation access requests');
    }
}
