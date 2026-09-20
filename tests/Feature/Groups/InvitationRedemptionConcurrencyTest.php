<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\RedeemGroupInvitation;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupInvitation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InvitationRedemptionConcurrencyTest extends TestCase
{
    private string $databasePath;

    private string $barrierPrefix;

    private string|false $originalDatabaseEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = (string) getmypid();
        $this->databasePath = storage_path("framework/testing/invitation-redemption-{$suffix}.sqlite");
        $this->barrierPrefix = storage_path("framework/testing/invitation-redemption-{$suffix}-barrier");
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

    public function test_concurrent_candidates_cannot_overconsume_a_single_use_invitation(): void
    {
        $owner = Actor::factory()->create();
        $firstCandidate = Actor::factory()->create();
        $secondCandidate = Actor::factory()->create();
        $group = Group::create(['name' => 'Concurrency group', 'created_by_actor_id' => $owner->id]);
        $token = GroupInvitation::issueToken();
        $invitation = GroupInvitation::create([
            'group_id' => $group->id,
            'invited_by_actor_id' => $owner->id,
            'token' => $token,
            'max_uses' => 1,
            'uses_count' => 0,
        ]);
        $barrierPrefix = $this->barrierPrefix;

        $attemptRedemption = function (int $actorId, int $barrierNumber) use ($barrierPrefix, $token): string {
            file_put_contents("{$barrierPrefix}-{$barrierNumber}", 'ready');
            $deadline = microtime(true) + 5;

            while (! file_exists("{$barrierPrefix}-1") || ! file_exists("{$barrierPrefix}-2")) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Concurrent invitation-redemption barrier timed out.');
                }

                usleep(10_000);
            }

            $actor = Actor::query()->with('user')->findOrFail($actorId);

            try {
                app(RedeemGroupInvitation::class)->execute($token, $actor, $actor->user->email);

                return 'committed';
            } catch (HttpException $exception) {
                if ($exception->getStatusCode() !== 404) {
                    throw $exception;
                }

                return 'exhausted';
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'database is locked')) {
                    throw $exception;
                }

                return 'database-locked';
            }
        };

        $results = Concurrency::driver('process')->run([
            fn (): string => $attemptRedemption($firstCandidate->id, 1),
            fn (): string => $attemptRedemption($secondCandidate->id, 2),
        ], 15);

        $this->assertContains('committed', $results);
        $this->assertCount(1, array_intersect($results, ['database-locked', 'exhausted']));
        $this->assertSame(1, $invitation->refresh()->uses_count);
        $this->assertDatabaseCount('group_invitation_acceptances', 1);
        $this->assertDatabaseCount('admissions', 1);
    }
}
