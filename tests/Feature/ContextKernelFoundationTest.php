<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Contexts\EnsureReferenceContext;
use App\Actions\Groups\CreateGroup;
use App\ContextKind;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Context;
use App\Models\GroupSpace;
use App\Models\GroupSpaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class ContextKernelFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_generates_uuid_when_created_without_one(): void
    {
        $context = Context::query()->create([
            'kind' => ContextKind::Personal,
        ]);

        $this->assertNotNull($context->uuid);
        $this->assertSame(36, strlen($context->uuid));
    }

    public function test_group_creation_provisions_one_group_space_context_and_ensure_is_idempotent(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Context group', null);
        $space = $group->spaces()->sole();

        $binding = GroupSpaceContext::query()
            ->with('context')
            ->where('group_space_id', $space->id)
            ->sole();

        $this->assertSame(ContextKind::GroupSpace, $binding->context->kind);

        $again = app(EnsureGroupSpaceContext::class)->execute($space);

        $this->assertSame($binding->context_id, $again->id);
        $this->assertDatabaseCount('group_space_contexts', 1);
    }

    public function test_group_space_context_provisioning_survives_disabled_model_events(): void
    {
        $space = GroupSpace::factory()->create();

        $context = Model::withoutEvents(
            fn (): Context => app(EnsureGroupSpaceContext::class)->execute($space),
        );

        $this->assertNotNull($context->uuid);
        $this->assertSame(ContextKind::GroupSpace, $context->kind);
        $this->assertDatabaseHas('group_space_contexts', [
            'context_id' => $context->id,
            'group_space_id' => $space->id,
        ]);
    }

    public function test_personal_context_is_unique_private_and_owner_only(): void
    {
        $owner = Actor::factory()->create();
        $outsider = Actor::factory()->create();

        $context = app(EnsurePersonalContext::class)->execute($owner->user);
        $again = app(EnsurePersonalContext::class)->execute($owner->user);

        $this->assertSame(ContextKind::Personal, $context->kind);
        $this->assertSame($context->id, $again->id);
        $this->assertTrue(Gate::forUser($owner->user)->allows('view', $context));
        $this->assertTrue(Gate::forUser($owner->user)->allows('createContent', $context));
        $this->assertTrue(Gate::forUser($owner->user)->allows('manageDefinitions', $context));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('view', $context));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('createContent', $context));
        $this->assertDatabaseCount('personal_contexts', 1);
    }

    public function test_admission_context_allows_candidate_and_reviewer_before_membership_without_group_access(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $outsider = Actor::factory()->create();

        $group = app(CreateGroup::class)->execute($reviewer, 'Admission context group', null);
        $space = $group->spaces()->sole();

        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
        ]);

        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);

        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);
        $again = app(EnsureAdmissionContext::class)->execute($admission, $reviewer->user);

        $this->assertSame(ContextKind::Admission, $context->kind);
        $this->assertSame($context->id, $again->id);

        $this->assertTrue(Gate::forUser($candidate->user)->allows('view', $context));
        $this->assertTrue(Gate::forUser($candidate->user)->allows('createContent', $context));
        $this->assertTrue(Gate::forUser($candidate->user)->allows('interactContent', $context));
        $this->assertFalse(Gate::forUser($candidate->user)->allows('manageContent', $context));
        $this->assertFalse(Gate::forUser($candidate->user)->allows('manageDefinitions', $context));

        $this->assertTrue(Gate::forUser($reviewer->user)->allows('view', $context));
        $this->assertTrue(Gate::forUser($reviewer->user)->allows('createContent', $context));
        $this->assertTrue(Gate::forUser($reviewer->user)->allows('manageContent', $context));
        $this->assertTrue(Gate::forUser($reviewer->user)->allows('manageDefinitions', $context));

        $this->assertFalse(Gate::forUser($outsider->user)->allows('view', $context));
        $this->assertFalse(Gate::forUser($candidate->user)->allows('view', $space));
    }

    public function test_terminal_admission_context_is_read_only_for_candidate_and_reviewer(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Terminal admission context group', null);

        $admission = Admission::factory()->cancelled()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
        ]);

        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);

        $this->assertTrue(Gate::forUser($candidate->user)->allows('view', $context));
        $this->assertTrue(Gate::forUser($reviewer->user)->allows('view', $context));
        $this->assertFalse(Gate::forUser($candidate->user)->allows('createContent', $context));
        $this->assertFalse(Gate::forUser($reviewer->user)->allows('createContent', $context));
        $this->assertFalse(Gate::forUser($reviewer->user)->allows('manageDefinitions', $context));
    }

    public function test_reference_context_is_shared_for_reading_and_feedback_but_manager_owned_for_authoring(): void
    {
        $manager = Actor::factory()->create();
        $reader = Actor::factory()->create();
        $unverifiedUser = \App\Models\User::factory()->unverified()->create();
        $unverifiedUser->actor()->create();

        $context = app(EnsureReferenceContext::class)->execute($manager->user, 'system-reference');
        $again = app(EnsureReferenceContext::class)->execute($manager->user, 'system-reference');

        $this->assertSame(ContextKind::Reference, $context->kind);
        $this->assertSame($context->id, $again->id);

        $this->assertTrue(Gate::forUser($reader->user)->allows('view', $context));
        $this->assertTrue(Gate::forUser($reader->user)->allows('interactContent', $context));
        $this->assertFalse(Gate::forUser($reader->user)->allows('createContent', $context));
        $this->assertFalse(Gate::forUser($reader->user)->allows('manageContent', $context));

        $this->assertTrue(Gate::forUser($manager->user)->allows('createContent', $context));
        $this->assertTrue(Gate::forUser($manager->user)->allows('manageContent', $context));
        $this->assertFalse(Gate::forUser($unverifiedUser)->allows('view', $context));
        $this->assertDatabaseCount('reference_contexts', 1);
    }

    public function test_context_kind_and_binding_identity_are_immutable(): void
    {
        $context = Context::factory()->create(['kind' => ContextKind::Personal]);

        $this->expectException(LogicException::class);

        $context->update(['kind' => ContextKind::Admission]);
    }
}
