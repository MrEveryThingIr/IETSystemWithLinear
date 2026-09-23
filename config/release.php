<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Release experience profile
    |--------------------------------------------------------------------------
    |
    | office_alpha keeps the first published experience intentionally small:
    | invitation onboarding, Dashboard, Needs/Offers/Services, Profile, Help,
    | and Access Invitations for authorized administrators. Mature kernels
    | remain in the codebase and routes so development can continue without
    | coupling release UX simplification to destructive feature removal.
    |
    */
    'profile' => env('IET_RELEASE_PROFILE', 'office_alpha'),
];
