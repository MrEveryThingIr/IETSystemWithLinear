<?php

namespace Tests\Feature;

use App\Support\ScheduledArtisan;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class SchedulerInProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_commands_run_inside_schedule_run_when_in_process_mode_is_forced(): void
    {
        config(['release.schedule_in_process' => true]);
        Process::fake();
        $schedule = new Schedule;

        $event = ScheduledArtisan::register($schedule, 'notifications:dispatch-outbox --limit=5')
            ->name('test:dispatch-outbox')
            ->everyMinute()
            ->withoutOverlapping();

        $this->assertInstanceOf(CallbackEvent::class, $event);

        $event->run($this->app);

        Process::assertNothingRan();
    }

    public function test_commands_use_child_processes_when_explicitly_allowed(): void
    {
        config(['release.schedule_in_process' => false]);

        $event = ScheduledArtisan::register(new Schedule, 'contracts:activate-due');

        $this->assertNotInstanceOf(CallbackEvent::class, $event);
        $this->assertStringContainsString('contracts:activate-due', (string) $event->command);
    }

    public function test_string_modes_from_the_environment_are_understood(): void
    {
        config(['release.schedule_in_process' => 'true']);
        $this->assertTrue(ScheduledArtisan::inProcess());

        config(['release.schedule_in_process' => 'false']);
        $this->assertFalse(ScheduledArtisan::inProcess());
    }

    public function test_a_failing_in_process_command_is_reported_as_a_failure(): void
    {
        config(['release.schedule_in_process' => true]);

        $event = ScheduledArtisan::register(new Schedule, 'migrate:status --database=missing-connection');

        $this->expectException(\Throwable::class);
        $event->run($this->app);
    }
}
