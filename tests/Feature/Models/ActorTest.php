<?php

namespace Tests\Feature\Models;

use App\Models\Actor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class ActorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_actor_has_only_kernel_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id',
            'user_id',
            'status',
            'archived_at',
            'archived_by_user_id',
            'archive_reason',
            'created_at',
            'updated_at',
        ], Schema::getColumnListing('actors'));
    }

    public function test_multiple_accountless_actors_are_allowed(): void
    {
        $actors = Actor::factory()->withoutUser()->count(2)->create();
        $this->assertDatabaseCount('actors', 2);
        $this->assertDatabaseCount('users', 0);
        foreach ($actors as $actor) {
            $this->assertNull($actor->user_id);
            $this->assertNull($actor->user);
        }
    }

    public function test_both_relationships_resolve(): void
    {
        $user = User::factory()->create();
        $actor = Actor::factory()->for($user)->create();
        $this->assertTrue($actor->user->is($user));
        $this->assertTrue($user->actor->is($actor));
    }

    public function test_rejects_nonexistent_user(): void
    {
        $this->expectException(QueryException::class);
        Actor::factory()->create(['user_id' => 999]);
    }

    public function test_rejects_duplicate_user_association(): void
    {
        $actor = Actor::factory()->create();
        $this->expectException(QueryException::class);
        Actor::factory()->create(['user_id' => $actor->user_id]);
    }

    public function test_existing_actor_identity_link_cannot_be_reassigned_or_detached(): void
    {
        $actor = Actor::factory()->create();
        $originalUser = $actor->user;
        $otherUser = User::factory()->create();

        foreach ([$otherUser->id, null] as $replacementUserId) {
            try {
                $actor->user_id = $replacementUserId;
                $actor->save();
                $this->fail('Actor identity reassignment unexpectedly succeeded.');
            } catch (LogicException $exception) {
                $this->assertSame('Actor identity links cannot be changed through ordinary model updates.', $exception->getMessage());
                $actor->refresh();
            }
        }

        $this->assertSame($originalUser->id, $actor->user_id);
    }

    public function test_deleting_user_detaches_and_preserves_actor(): void
    {
        $actor = Actor::factory()->create();
        $actor->user->delete();
        $this->assertModelExists($actor);
        $this->assertNull($actor->refresh()->user_id);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_actor_cannot_be_physically_deleted(): void
    {
        $actor = Actor::factory()->create();
        $user = $actor->user;

        try {
            $actor->delete();
            $this->fail('Actor deletion unexpectedly succeeded.');
        } catch (LogicException $exception) {
            $this->assertSame('Actors cannot be deleted; archive them instead.', $exception->getMessage());
        }

        $this->assertModelExists($actor);
        $this->assertModelExists($user);
        $this->assertTrue($user->refresh()->actor->is($actor));
    }

    public function test_default_seeder_associates_the_seed_account_with_an_actor(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue($user->actor->user->is($user));
    }
}
