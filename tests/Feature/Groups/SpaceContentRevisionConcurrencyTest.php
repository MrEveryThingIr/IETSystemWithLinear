<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SpaceContentRevisionConcurrencyTest extends TestCase
{
    private string $databasePath;

    private string $barrierPrefix;

    private string|false $originalDatabaseEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = (string) getmypid();
        $this->databasePath = storage_path("framework/testing/space-content-revisions-{$suffix}.sqlite");
        $this->barrierPrefix = storage_path("framework/testing/space-content-revisions-{$suffix}-barrier");
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

    public function test_concurrent_revisions_cannot_reuse_a_revision_number(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Concurrent Content revisions', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Engineering', 'restricted');
        app(SetGroupSpaceParticipant::class)->execute($space, $owner, $owner->user, 'allow', 'participant');

        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $owner->user,
            'Daily Report',
            null,
            [[
                'key' => 'work_completed',
                'label' => 'Work completed',
                'type' => 'long_text',
                'required' => true,
                'help' => null,
                'options' => [],
            ]],
        );
        $definition = app(ActivateSpaceContentDefinition::class)->execute($definition, $owner->user);
        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $owner->user,
            'Initial report',
            ['work_completed' => 'Initial work.'],
        );

        $barrierPrefix = $this->barrierPrefix;
        $contentId = $content->id;
        $userId = $owner->user_id;

        $revise = function (int $barrierNumber) use ($barrierPrefix, $contentId, $userId): string {
            file_put_contents("{$barrierPrefix}-{$barrierNumber}", 'ready');
            $deadline = microtime(true) + 5;

            while (! file_exists("{$barrierPrefix}-1") || ! file_exists("{$barrierPrefix}-2")) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Concurrent Content-revision barrier timed out.');
                }

                usleep(10_000);
            }

            try {
                app(ReviseSpaceContent::class)->execute(
                    SpaceContent::query()->findOrFail($contentId),
                    User::query()->findOrFail($userId),
                    "Concurrent revision {$barrierNumber}",
                    ['work_completed' => "Concurrent work {$barrierNumber}."],
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
            fn (): string => $revise(1),
            fn (): string => $revise(2),
        ], 15);

        $this->assertContains('committed', $results);
        $this->assertCount(2, array_intersect($results, ['committed', 'database-locked']));

        $content->refresh();
        $revisions = $content->revisions()->orderBy('revision')->pluck('revision')->all();

        $this->assertSame(range(1, count($revisions)), $revisions);
        $this->assertSame(count($revisions), $content->current_revision);
        $this->assertSame(count($revisions), count(array_unique($revisions)));
    }
}
