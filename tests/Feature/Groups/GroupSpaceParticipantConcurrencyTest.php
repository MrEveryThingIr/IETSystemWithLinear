<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Models\Actor;
use App\Models\GroupSpaceParticipant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class GroupSpaceParticipantConcurrencyTest extends TestCase
{
    private string $databasePath;

    private string $barrierPrefix;

    private string|false $originalDatabaseEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = (string) getmypid();
        $this->databasePath = storage_path("framework/testing/group-space-participants-{$suffix}.sqlite");
        $this->barrierPrefix = storage_path("framework/testing/group-space-participants-{$suffix}-barrier");
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

        foreach ([
            $this->databasePath,
            "{$this->databasePath}-shm",
            "{$this->databasePath}-wal",
            "{$this->barrierPrefix}-1",
            "{$this->barrierPrefix}-2",
        ] as $path) {
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

    public function test_concurrent_participant_updates_cannot_create_duplicate_rules(): void
    {
        $owner = Actor::factory()->create();
        $target = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Concurrent Space rules', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Engineering', 'restricted');

        $barrierPrefix = $this->barrierPrefix;
        $spaceId = $space->id;
        $targetId = $target->id;
        $ownerUserId = $owner->user_id;

        $setRule = function (int $barrierNumber) use ($barrierPrefix, $spaceId, $targetId, $ownerUserId): string {
            file_put_contents("{$barrierPrefix}-{$barrierNumber}", 'ready');
            $deadline = microtime(true) + 5;

            while (! file_exists("{$barrierPrefix}-1") || ! file_exists("{$barrierPrefix}-2")) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Concurrent Space-participant barrier timed out.');
                }

                usleep(10_000);
            }

            try {
                app(SetGroupSpaceParticipant::class)->execute(
                    \App\Models\GroupSpace::query()->findOrFail($spaceId),
                    Actor::query()->findOrFail($targetId),
                    User::query()->findOrFail($ownerUserId),
                    'allow',
                    'participant',
                );

                return 'committed';
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'database is locked')) {
                    throw $exception;
                }

                return 'database-locked';
            }
        };

        $results = Concurrency::driver('process')->run([
            fn (): string => $setRule(1),
            fn (): string => $setRule(2),
        ], 15);

        $this->assertContains('committed', $results);
        $this->assertCount(2, array_intersect($results, ['committed', 'database-locked']));
        $this->assertSame(
            1,
            GroupSpaceParticipant::query()
                ->where('group_space_id', $spaceId)
                ->where('actor_id', $targetId)
                ->count(),
        );
    }
}
