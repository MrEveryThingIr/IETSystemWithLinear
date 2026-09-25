<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\DomainBlueprints\EnsureSystemDomainBlueprints;
use App\Actions\Planner\CreatePlan;
use App\Actions\Relationships\CreateRelationship;
use App\ConceptStatus;
use App\DomainJourneyKind;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptVocabulary;
use App\Models\DomainBlueprint;
use App\Models\DomainBlueprintVersion;
use App\Support\DomainBlueprintCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DomainBlueprintKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_domain_blueprints_are_versioned_idempotent_and_published(): void
    {
        $first = app(EnsureSystemDomainBlueprints::class)->execute();
        $second = app(EnsureSystemDomainBlueprints::class)->execute();

        $this->assertCount(6, $first);
        $this->assertCount(6, $second);
        $this->assertDatabaseCount('domain_blueprints', 6);
        $this->assertDatabaseCount('domain_blueprint_versions', 6);

        foreach ($second as $blueprint) {
            $version = $blueprint->activeVersionRecord();

            $this->assertInstanceOf(DomainBlueprintVersion::class, $version);
            $this->assertNotNull($version->published_at);
            $this->assertSame(64, strlen($version->content_hash));
        }

        $this->assertSame(
            [
                'construction-partnership',
                'paid-work',
                'personal-activity',
                'rental',
                'service-job',
                'simple-sale',
            ],
            DomainBlueprint::query()->orderBy('slug')->pluck('slug')->all(),
        );
    }

    public function test_relationship_remembers_exact_blueprint_version_after_blueprint_upgrade(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $purpose = $this->concept('service');

        $versionOne = app(DomainBlueprintCatalog::class)->version(
            'service-job',
            DomainJourneyKind::Relationship,
        );

        $relationship = app(CreateRelationship::class)->execute(
            $alice->user,
            $purpose,
            'client',
            [[
                'actor' => $bob,
                'role' => 'service provider',
            ]],
            title: 'Workshop service',
            domainBlueprintVersion: $versionOne,
        );

        $blueprint = $versionOne->blueprint;
        $versionTwo = $blueprint->versions()->create([
            'version' => 2,
            'journey_kind' => DomainJourneyKind::Relationship,
            'terminology' => [
                'journey' => 'Service Job',
                'creator_role' => 'customer',
                'participant_role' => 'provider',
            ],
            'capabilities' => ['conversation', 'content', 'planner'],
            'content_blueprint_slugs' => ['activity-report'],
            'guided_entry' => [
                'purpose_hint' => 'service',
                'creator_role' => 'customer',
                'participant_role' => 'provider',
            ],
            'created_by_actor_id' => null,
        ]);
        $versionTwo->publish();
        $blueprint->update(['current_version' => 2]);

        $relationship = $relationship->fresh('domainBlueprintVersion.blueprint');

        $this->assertSame($versionOne->id, $relationship->domain_blueprint_version_id);
        $this->assertSame(1, $relationship->domainBlueprintVersion->version);
        $this->assertSame(2, $blueprint->fresh()->current_version);
        $this->assertSame(
            $versionOne->uuid,
            $relationship->events()->firstOrFail()->payload['domain_blueprint_version_uuid'],
        );
    }

    public function test_personal_activity_plan_remembers_blueprint_without_creating_other_domain_truth(): void
    {
        $alice = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($alice->user);
        $version = app(DomainBlueprintCatalog::class)->version(
            'personal-activity',
            DomainJourneyKind::PersonalActivity,
        );

        $plan = app(CreatePlan::class)->execute(
            $context,
            $alice->user,
            'Study mathematics',
            'Read and practice for one hour.',
            'UTC',
            domainBlueprintVersion: $version,
        );

        $this->assertSame($version->id, $plan->domain_blueprint_version_id);
        $this->assertSame(
            $version->uuid,
            $plan->events()->firstOrFail()->payload['domain_blueprint_version_uuid'],
        );
        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('commitments', 0);
        $this->assertDatabaseCount('financial_obligations', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_wrong_journey_blueprint_cannot_be_applied_to_relationship_or_plan(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $purpose = $this->concept('service');
        $personal = app(DomainBlueprintCatalog::class)->version(
            'personal-activity',
            DomainJourneyKind::PersonalActivity,
        );

        try {
            app(CreateRelationship::class)->execute(
                $alice->user,
                $purpose,
                'client',
                [['actor' => $bob, 'role' => 'provider']],
                domainBlueprintVersion: $personal,
            );

            $this->fail('Personal Activity Blueprint was applied to Relationship.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $relationshipVersion = app(DomainBlueprintCatalog::class)->version(
            'service-job',
            DomainJourneyKind::Relationship,
        );
        $context = app(EnsurePersonalContext::class)->execute($alice->user);

        try {
            app(CreatePlan::class)->execute(
                $context,
                $alice->user,
                'Wrong blueprint',
                timezone: 'UTC',
                domainBlueprintVersion: $relationshipVersion,
            );

            $this->fail('Relationship Blueprint was applied to Plan.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_published_domain_blueprint_version_is_immutable(): void
    {
        $version = app(DomainBlueprintCatalog::class)->version('paid-work');

        $this->expectException(LogicException::class);
        $version->update(['capabilities' => ['content']]);
    }

    private function concept(string $slug): Concept
    {
        $vocabulary = ConceptVocabulary::factory()->create();

        return Concept::factory()->create([
            'vocabulary_id' => $vocabulary->id,
            'slug' => $slug,
            'status' => ConceptStatus::Active,
        ]);
    }
}
