<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Actions\Groups\ReviewGroupRoleChangeRequest;
use App\Actions\Groups\SubmitGroupRoleChangeRequest;
use App\GroupPermission;
use App\GroupRoleKey;
use App\Livewire\Groups\Show;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GroupRoleChangeRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_duplicate_pending_request_is_idempotent(): void
    {
        [$group, , $member, $membership, $roles] = $this->groupWithReviewer();
        $editor = $roles->createRole($group, 'Editor', [GroupPermission::ManageGroup->value]);
        $submit = app(SubmitGroupRoleChangeRequest::class);

        $first = $submit->execute($membership, $member, $editor, 'grant');
        $second = $submit->execute($membership, $member, $editor, 'grant');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('group_role_change_requests', 1);
        $this->assertSame("{$membership->id}:{$editor->id}:grant", $first->pending_key);
    }

    public function test_approval_grants_only_the_requested_custom_role(): void
    {
        [$group, $reviewer, $member, $membership, $roles] = $this->groupWithReviewer();
        $editor = $roles->createRole($group, 'Editor', [GroupPermission::ManageGroup->value]);
        $request = app(SubmitGroupRoleChangeRequest::class)->execute($membership, $member, $editor, 'grant');

        $reviewed = app(ReviewGroupRoleChangeRequest::class)->execute($request, $reviewer, true);

        $this->assertSame('approved', $reviewed->status);
        $this->assertNull($reviewed->pending_key);
        $this->assertSame($reviewer->id, $reviewed->reviewed_by_actor_id);
        $this->assertEqualsCanonicalizing(['Editor', 'Member'], $roles->roleNames($member, $group)->all());
    }

    public function test_approved_revoke_request_removes_only_the_requested_role(): void
    {
        [$group, $reviewer, $member, $membership, $roles] = $this->groupWithReviewer();
        $editor = $roles->createRole($group, 'Editor', [GroupPermission::ManageGroup->value]);
        $coordinator = $roles->createRole($group, 'Coordinator', [GroupPermission::ManageInvitations->value]);
        $roles->grant($member, $group, $editor);
        $roles->grant($member, $group, $coordinator);
        $request = app(SubmitGroupRoleChangeRequest::class)->execute($membership, $member, $editor, 'revoke');

        app(ReviewGroupRoleChangeRequest::class)->execute($request, $reviewer, true);

        $this->assertEqualsCanonicalizing(['Coordinator', 'Member'], $roles->roleNames($member, $group)->all());
    }

    public function test_member_cannot_review_their_own_request(): void
    {
        [$group, , $member, $membership, $roles] = $this->groupWithReviewer();
        $editor = $roles->createRole($group, 'Editor', [GroupPermission::ManageGroup->value]);
        $request = app(SubmitGroupRoleChangeRequest::class)->execute($membership, $member, $editor, 'grant');

        $this->expectException(HttpException::class);

        app(ReviewGroupRoleChangeRequest::class)->execute($request, $member, true);
    }

    public function test_reviewer_does_not_see_their_own_pending_request(): void
    {
        [$group, , $member, $membership, $roles] = $this->groupWithReviewer();
        $editor = $roles->createRole($group, 'Editor', [GroupPermission::ManageGroup->value]);
        $approver = $roles->createRole($group, 'Approver', [GroupPermission::ApproveRoleChanges->value]);
        $roles->grant($member, $group, $approver);

        app(SubmitGroupRoleChangeRequest::class)->execute($membership, $member, $editor, 'grant');

        Livewire::actingAs($member->user)
            ->test(Show::class, ['group' => $group])
            ->assertDontSee(__('ui.groups.pending_role_changes'));
    }

    public function test_built_in_roles_cannot_be_requested(): void
    {
        [$group, , $member, $membership, $roles] = $this->groupWithReviewer();

        $this->expectException(HttpException::class);

        app(SubmitGroupRoleChangeRequest::class)->execute(
            $membership,
            $member,
            $roles->builtInRole($group, GroupRoleKey::Owner),
            'grant',
        );
    }

    public function test_reviewed_request_cannot_be_reviewed_again(): void
    {
        [$group, $reviewer, $member, $membership, $roles] = $this->groupWithReviewer();
        $editor = $roles->createRole($group, 'Editor', [GroupPermission::ManageGroup->value]);
        $request = app(SubmitGroupRoleChangeRequest::class)->execute($membership, $member, $editor, 'grant');
        $review = app(ReviewGroupRoleChangeRequest::class);
        $review->execute($request, $reviewer, false);

        $this->expectException(HttpException::class);

        $review->execute($request, $reviewer, true);
    }

    /** @return array{Group, Actor, Actor, GroupMembership, GroupRoleProvisioner} */
    private function groupWithReviewer(): array
    {
        $reviewer = Actor::factory()->create();
        $member = Actor::factory()->create();
        $group = Group::factory()->for($reviewer, 'creator')->create();
        $group->memberships()->create(['actor_id' => $reviewer->id, 'status' => 'active']);
        $membership = $group->memberships()->create(['actor_id' => $member->id, 'status' => 'active']);
        $roles = app(GroupRoleProvisioner::class);
        $builtIns = $roles->provision($group);
        $roles->grant($reviewer, $group, $builtIns['owner']);
        $roles->grant($member, $group, $builtIns['member']);

        return [$group, $reviewer, $member, $membership, $roles];
    }
}
