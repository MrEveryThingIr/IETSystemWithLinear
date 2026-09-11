<?php

namespace Tests\Feature;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Admission;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupInvitation;
use App\Models\GroupRoleChangeRequest;
use App\Models\Story;
use Database\Seeders\ConstructionProjectSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConstructionProjectSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_builds_three_complete_construction_project_scenarios(): void
    {
        $this->seed(ConstructionProjectSeeder::class);

        $groups = Group::query()->with(['memberships.actor', 'agreements.versions', 'invitations', 'admissions', 'stories.roles'])->get();

        $this->assertCount(3, $groups);
        $this->assertSame([
            'Al Noor Community Center',
            'Greenline Mixed-Use Renovation',
            'Riverside Residence Build',
        ], $groups->pluck('name')->sort()->values()->all());

        foreach ($groups as $group) {
            $activeMemberships = $group->memberships->where('status', 'active');
            $this->assertGreaterThanOrEqual(15, $activeMemberships->count());
            $this->assertLessThanOrEqual(20, $activeMemberships->count());
            $this->assertSame(1, $group->memberships->where('status', 'removed')->count());
            $this->assertSame(3, $group->memberships->filter(
                fn ($membership): bool => app(GroupRoleProvisioner::class)->hasRole($membership->actor, $group, 'Owner'),
            )->count());
            $this->assertSame(14, Role::query()->where('group_id', $group->id)->count());
            $this->assertSame(3, $group->agreements->count());
            $this->assertSame(5, $group->stories->count());
            $this->assertGreaterThanOrEqual(5, $group->invitations->count());
            $this->assertTrue($group->stories->every(fn (Story $story): bool => $story->roles->count() === 3));
        }

        $this->assertSame([
            'approved',
            'cancelled',
            'clarification_required',
            'draft',
            'finalized',
            'rejected',
            'submitted',
            'under_review',
        ], Admission::query()->distinct()->orderBy('status')->pluck('status')->all());
        $this->assertSame(['approved', 'pending', 'rejected'], GroupRoleChangeRequest::query()->distinct()->orderBy('status')->pluck('status')->all());
        $this->assertSame(3, GroupAgreement::query()->where('name', 'Payment and settlement planning notes')->where('required_for_admission', false)->count());
        $this->assertSame(3, Story::query()->where('title', 'Payment and settlement placeholders')->where('body', 'like', '%NOT CONTRACTED%')->count());
        $this->assertSame(3, GroupInvitation::query()->whereNotNull('revoked_at')->count());
        $this->assertSame(3, GroupInvitation::query()->where('expires_at', '<', now())->count());

        $this->assertTrue(Auth::attempt(['email' => 'partner1.riverside@example.test', 'password' => 'password']));
    }

    public function test_seeder_is_safe_to_run_more_than_once(): void
    {
        $this->seed(ConstructionProjectSeeder::class);
        $counts = [Group::count(), GroupInvitation::count(), Admission::count(), Story::count()];

        $this->seed(ConstructionProjectSeeder::class);

        $this->assertSame($counts, [Group::count(), GroupInvitation::count(), Admission::count(), Story::count()]);
    }
}
