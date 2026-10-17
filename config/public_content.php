<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public Content
    |--------------------------------------------------------------------------
    |
    | Authors can open published Content to anyone (no account needed) and send
    | personal invitation links. "index" lists public Content at /public and
    | previews it on the home page. Turn it off to keep the site invitation-only:
    | public Content then stays reachable only by its direct link, and
    | invitation links keep working either way.
    |
    */

    'index' => (bool) env('PUBLIC_CONTENT_INDEX', true),

    'home_preview' => (int) env('PUBLIC_CONTENT_HOME_PREVIEW', 6),

    'per_page' => 12,
];
