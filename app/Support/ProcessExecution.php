<?php

namespace App\Support;

/**
 * Detects whether this PHP runtime can start child processes.
 *
 * Laravel Process and the scheduler's Schedule::command() events use Symfony
 * Process, which needs proc_open(). Shared hosts often disable it through
 * disable_functions, so callers must check instead of assuming it exists.
 */
final class ProcessExecution
{
    /** Functions Symfony Process needs to run a child process. */
    private const FUNCTIONS = ['proc_open', 'proc_close', 'proc_get_status', 'proc_terminate'];

    public static function available(): bool
    {
        $disabled = array_map(
            static fn (string $name): string => strtolower(trim($name)),
            explode(',', (string) ini_get('disable_functions')),
        );

        foreach (self::FUNCTIONS as $function) {
            if (! function_exists($function) || in_array($function, $disabled, true)) {
                return false;
            }
        }

        return true;
    }
}
