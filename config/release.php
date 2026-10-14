<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Release experience profile
    |--------------------------------------------------------------------------
    |
    | full is the unified-finalization default. It restores the mature
    | integrated product surface while preserving the newer Planner, Calendar,
    | Money, Exchange and Vault implementations already present in the tree.
    |
    | planning_baseline remains available explicitly for focused regression
    | and browser comparison, but it is no longer the default product runtime.
    |
    */
    'profile' => env('IET_RELEASE_PROFILE', 'full'),

    /*
    |--------------------------------------------------------------------------
    | Scheduler process model
    |--------------------------------------------------------------------------
    |
    | Scheduled Artisan commands normally run as child processes, which needs
    | proc_open(). "auto" runs them inside schedule:run when process execution
    | is unavailable (shared hosting). true/false force either behaviour.
    |
    */
    'schedule_in_process' => env('IET_SCHEDULE_IN_PROCESS', 'auto'),
];
