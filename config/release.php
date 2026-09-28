<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Release experience profile
    |--------------------------------------------------------------------------
    |
    | planning_baseline is the default experience-first assembly. It exposes
    | only the system foundation (identity/auth/access invitation) plus the
    | basic Planning Studio and its shared temporal/calendar surface.
    |
    | Mature capabilities remain in the repository as source-library code and
    | can be re-admitted deliberately. They are not part of this runtime until
    | a later selective-assembly milestone accepts them.
    |
    | full keeps the historical integrated runtime available for regression
    | tests and source-library verification.
    |
    */
    'profile' => env('IET_RELEASE_PROFILE', 'planning_baseline'),
];
