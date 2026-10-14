<?php

namespace App\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Schedules an Artisan command so it also runs on hosts without proc_open.
 *
 * Schedule::command() starts a child PHP process (Symfony Process/proc_open).
 * When process execution is unavailable, or IET_SCHEDULE_IN_PROCESS=true, the
 * command runs inside the schedule:run process through Artisan::call().
 */
final class ScheduledArtisan
{
    public static function register(Schedule $schedule, string $command): Event
    {
        if (! self::inProcess()) {
            return $schedule->command($command);
        }

        return $schedule->call(static function () use ($command): void {
            $exitCode = Artisan::call($command);

            if ($exitCode !== 0) {
                throw new RuntimeException("Scheduled command [{$command}] failed with exit code {$exitCode}.");
            }
        });
    }

    public static function inProcess(): bool
    {
        $mode = config('release.schedule_in_process', 'auto');

        if (is_bool($mode)) {
            return $mode;
        }

        return match (strtolower(trim((string) $mode))) {
            'true', '1', 'yes', 'on' => true,
            'false', '0', 'no', 'off' => false,
            default => ! ProcessExecution::available(),
        };
    }
}
