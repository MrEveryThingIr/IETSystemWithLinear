<?php

namespace Tests\Feature;

use App\Actions\Relationships\CreateRelationship;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\Relationship;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DomainNotificationProjectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_relationship_invitation_projects_only_to_invited_participant(): void
    {
        Bus::fake();

        $owner = Actor::factory()->create();
        $invitee = Actor::factory()->create();
        $purpose = Concept::factory()->create();

        $relationship = app(CreateRelationship::class)->execute(
            $owner->user,
            $purpose,
            'coordinator',
            [['actor' => $invitee, 'role' => 'inspector']],
            title: 'Riverside inspection',
        );

        $this->assertDatabaseHas('notification_outbox', [
            'recipient_user_id' => $invitee->user->id,
            'kind' => 'relationship.event',
            'subject_type' => 'relationship',
            'subject_uuid' => $relationship->uuid,
        ]);

        $this->assertDatabaseMissing('notification_outbox', [
            'recipient_user_id' => $owner->user->id,
            'subject_type' => 'relationship',
            'subject_uuid' => $relationship->uuid,
        ]);
    }

    public function test_projected_notification_rolls_back_with_authoritative_transaction(): void
    {
        Bus::fake();

        $owner = Actor::factory()->create();
        $invitee = Actor::factory()->create();
        $purpose = Concept::factory()->create();

        try {
            DB::transaction(function () use ($owner, $invitee, $purpose): never {
                app(CreateRelationship::class)->execute(
                    $owner->user,
                    $purpose,
                    'coordinator',
                    [['actor' => $invitee, 'role' => 'inspector']],
                    title: 'Rolled back relationship',
                );

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('force rollback', $exception->getMessage());
        }

        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('relationships', 0);
        $this->assertSame(0, Relationship::query()->count());
    }
}
