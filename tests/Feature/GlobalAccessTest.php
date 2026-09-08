<?php

namespace Tests\Feature;

use App\Actions\Administration\GlobalAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GlobalAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_partial_permissions_are_isolated_from_group_scoped_rbac(): void
    {
        $user = User::factory()->create();
        $access = app(GlobalAccess::class);
        $access->sync($user, false, ['actors.manage']);

        $this->assertTrue($access->can($user, 'actors.manage'));
        $this->assertFalse($access->can($user, 'groups.manage'));
        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertDatabaseCount('global_user_permissions', 1);
    }

    public function test_last_global_administrator_cannot_be_demoted(): void
    {
        $user = User::factory()->create();
        $access = app(GlobalAccess::class);
        $access->sync($user, true, []);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $access->sync($user, false, []);
    }
}
