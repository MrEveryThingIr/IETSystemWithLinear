<?php

namespace Tests\Feature\Actions\Platform;

use App\Actions\Platform\ArchiveActor;
use App\Models\Actor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ArchiveActorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_superadmin_archives_accountless_actor_and_preserves_row(): void
    {
        $administrator = User::factory()->superadmin()->create();
        $actor = Actor::factory()->withoutUser()->create();

        $archivedActor = app(ArchiveActor::class)->execute(
            $actor,
            $administrator,
            'Synthetic participant is no longer needed for this scenario.',
        );

        $this->assertModelExists($archivedActor);
        $this->assertSame('archived', $archivedActor->status);
        $this->assertSame($administrator->id, $archivedActor->archived_by_user_id);
        $this->assertSame('Synthetic participant is no longer needed for this scenario.', $archivedActor->archive_reason);
        $this->assertNotNull($archivedActor->archived_at);
    }

    public function test_ordinary_user_cannot_archive_actor(): void
    {
        $user = User::factory()->create();
        $actor = Actor::factory()->withoutUser()->create();

        try {
            app(ArchiveActor::class)->execute($actor, $user, 'Attempted unauthorized archive operation.');
            $this->fail('An unauthorized archive unexpectedly succeeded.');
        } catch (AuthorizationException) {
            $this->assertSame('active', $actor->refresh()->status);
        }
    }
}
