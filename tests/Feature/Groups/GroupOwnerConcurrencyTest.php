<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\GroupOwnerIntegrity;
use App\Actions\Groups\GroupRoleProvisioner;
use App\Exceptions\CannotLeaveGroupWithoutOwner;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class GroupOwnerConcurrencyTest extends TestCase
{
    private string $databasePath;

    private string $barrierPrefix;

    private string|false $originalDatabaseEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = (string) getmypid();
        $this->databasePath = storage_path("framework/testing/group-owner-integrity-{$suffix}.sqlite");
        $this->barrierPrefix = storage_path("framework/testing/group-owner-integrity-{$suffix}-barrier");
        $this->originalDatabaseEnvironment = getenv('DB_DATABASE');

        touch($this->databasePath);
        putenv("DB_DATABASE={$this->databasePath}");
        $_ENV['DB_DATABASE'] = $this->databasePath;
        $_SERVER['DB_DATABASE'] = $this->databasePath;

        config()->set('database.connections.sqlite.database', $this->databasePath);
        DB::purge('sqlite');
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        foreach ([$this->databasePath, "{$this->databasePath}-shm", "{$this->databasePath}-wal", "{$this->barrierPrefix}-1", "{$this->barrierPrefix}-2"] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        if ($this->originalDatabaseEnvironment === false) {
            putenv('DB_DATABASE');
            unset($_ENV['DB_DATABASE'], $_SERVER['DB_DATABASE']);
        } else {
            putenv("DB_DATABASE={$this->originalDatabaseEnvironment}");
            $_ENV['DB_DATABASE'] = $this->originalDatabaseEnvironment;
            $_SERVER['DB_DATABASE'] = $this->originalDatabaseEnvironment;
        }

        parent::tearDown();
    }

    public function test_concurrent_owner_removals_cannot_leave_the_group_without_an_active_owner(): void
    {
        $firstOwner = Actor::factory()->create();
        $secondOwner = Actor::factory()->create();
        $group = Group::create(['name' => 'Concurrent integrity', 'description' => null, 'created_by_actor_id' => $firstOwner->id]);
        $roles = app(GroupRoleProvisioner::class);
        $ownerRole = $roles->provision($group)['owner'];
        $firstMembership = $group->memberships()->create(['actor_id' => $firstOwner->id, 'status' => 'active']);
        $roles->assign($firstOwner, $group, $ownerRole);
        $secondMembership = $group->memberships()->create(['actor_id' => $secondOwner->id, 'status' => 'active']);
        $roles->assign($secondOwner, $group, $ownerRole);
        $barrierPrefix = $this->barrierPrefix;
        $groupId = $group->id;

        $attemptRemoval = function (int $membershipId, int $barrierNumber) use ($barrierPrefix, $groupId): string {
            file_put_contents("{$barrierPrefix}-{$barrierNumber}", 'ready');
            $deadline = microtime(true) + 5;

            while (! file_exists("{$barrierPrefix}-1") || ! file_exists("{$barrierPrefix}-2")) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Concurrent owner-removal barrier timed out.');
                }

                usleep(10_000);
            }

            try {
                app(GroupOwnerIntegrity::class)->execute(
                    Group::query()->findOrFail($groupId),
                    function () use ($membershipId): void {
                        GroupMembership::query()->whereKey($membershipId)->update(['status' => 'removed']);
                        usleep(250_000);
                    },
                );

                return 'committed';
            } catch (CannotLeaveGroupWithoutOwner) {
                return 'rejected';
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'database is locked')) {
                    throw $exception;
                }

                return 'database-locked';
            }
        };

        $results = Concurrency::driver('process')->run([
            fn (): string => $attemptRemoval($firstMembership->id, 1),
            fn (): string => $attemptRemoval($secondMembership->id, 2),
        ], 15);

        $this->assertContains('committed', $results);
        $this->assertCount(1, array_intersect($results, ['database-locked', 'rejected']));
        $this->assertSame(1, $group->memberships()->where('status', 'active')->count());
    }
}
