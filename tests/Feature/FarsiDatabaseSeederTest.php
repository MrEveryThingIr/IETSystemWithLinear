<?php

namespace Tests\Feature;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Admission;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupInvitation;
use App\Models\GroupRoleChangeRequest;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\Farsi\DatabaseSeeder as FarsiDatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FarsiDatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_builds_three_complete_persian_project_scenarios(): void
    {
        $this->seed(FarsiDatabaseSeeder::class);

        $groups = Group::query()->with(['memberships.actor', 'agreements.versions', 'invitations', 'admissions', 'stories.roles'])->get();

        $this->assertCount(3, $groups);
        $this->assertSame([
            'بازسازی کاربری مختلط گرین‌لاین',
            'مرکز اجتماعی و فرهنگی النور',
            'پروژه ساخت مجتمع مسکونی کنار رود',
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
            $this->assertTrue(Role::query()->where('group_id', $group->id)->where('name', 'مدیر پروژه')->exists());
            $this->assertSame(3, $group->agreements->count());
            $this->assertSame(5, $group->stories->count());
            $this->assertGreaterThanOrEqual(5, $group->invitations->count());
            $this->assertTrue($group->stories->every(fn (Story $story): bool => $story->roles->count() === 3));
        }

        $this->assertTrue($groups->every(fn (Group $group): bool => preg_match('/\p{Arabic}/u', $group->name) === 1));
        $this->assertTrue(GroupInvitation::query()->whereNotNull('content')->get()->every(
            fn (GroupInvitation $invitation): bool => preg_match('/\p{Arabic}/u', $invitation->content) === 1,
        ));

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
        $this->assertSame(3, GroupAgreement::query()->where('name', 'یادداشت‌های برنامه‌ریزی پرداخت و تسویه')->where('required_for_admission', false)->count());
        $this->assertSame(3, Story::query()->where('title', 'جایگاه‌های پرداخت و تسویه')->where('body', 'like', '%قرارداد منعقد نشده است%')->count());

        $this->assertFalse(User::query()->where(fn ($query) => $query->whereNull('locale')->orWhere('locale', '!=', 'fa'))->exists());
        $this->assertTrue(User::query()->get()->every(
            fn (User $user): bool => preg_match('/\p{Arabic}/u', $user->username) === 1,
        ));

        $demo = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertSame('کاربر آزمایشی', $demo->username);
        $this->assertSame('fa', $demo->locale);

        $this->assertTrue(Auth::attempt(['email' => 'partner1.riverside@example.test', 'password' => 'password']));
    }

    public function test_seeder_is_safe_to_run_more_than_once(): void
    {
        $this->seed(FarsiDatabaseSeeder::class);
        $counts = [Group::count(), GroupInvitation::count(), Admission::count(), Story::count()];

        $this->seed(FarsiDatabaseSeeder::class);

        $this->assertSame($counts, [Group::count(), GroupInvitation::count(), Admission::count(), Story::count()]);
    }
}
