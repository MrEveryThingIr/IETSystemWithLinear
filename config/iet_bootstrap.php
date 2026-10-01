<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical bootstrap super-admin
    |--------------------------------------------------------------------------
    |
    | On a new installation, IET must not finish initialization with zero
    | super-admins. Point this at an EXISTING user's email or username.
    |
    | Never place passwords here.
    |
    */
    'superadmin_identifier' => env('IET_SUPERADMIN_IDENTIFIER'),
];
