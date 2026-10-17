<?php

return [
    'open_app' => 'Open the app',

    'index' => [
        'nav' => 'Public posts',
        'title' => 'Public posts',
        'description' => 'Posts, articles, lessons and courses their authors chose to share with everyone.',
        'search' => 'Search titles',
        'kind' => 'Type',
        'all_kinds' => 'All types',
        'apply' => 'Show',
        'empty' => 'Nothing has been shared publicly yet.',
        'no_results' => 'No public posts match this search.',
    ],

    'home' => [
        'title' => 'Latest public posts',
        'help' => 'Shared by their authors for everyone to read.',
        'all' => 'See all',
    ],

    'reader' => [
        'contents' => 'Contents',
        'read_only' => 'You are reading a shared copy. Comments and other tools are available to members.',
    ],

    'invitation' => [
        'greeting' => 'Hello :name,',
        'invited_by' => ':name invited you to read this.',
        'someone' => 'Someone',
        'expires' => 'This link works until',
        'unavailable_title' => 'This link no longer works',
        'unavailable_revoked' => 'The person who shared it turned this link off.',
        'unavailable_expired' => 'This link has expired.',
        'unavailable_unavailable' => 'What it pointed to is not available right now.',
        'ask_inviter' => 'You can ask :name for a new link.',
    ],

    'share' => [
        'button' => 'Share',
        'title' => 'Share outside',
        'not_published' => 'Publish this first. Only the published version can be shared, and later edits stay private until you publish them.',
        'not_allowed' => 'Only the author can share this outside. In a group or business the author also needs to manage that place\'s content.',
        'public_title' => 'Anyone on the web',
        'public_help' => 'Anyone can read the published version without signing in. It is also listed on the public posts page.',
        'public_help_unlisted' => 'Anyone with the link can read the published version without signing in.',
        'public_on' => 'Public',
        'public_off' => 'Private',
        'make_public' => 'Make public',
        'make_private' => 'Make private',
        'public_link' => 'Public link',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'open' => 'Open',
        'made_public' => 'Anyone can now read this.',
        'made_private' => 'This is private again. Invitation links still work until you turn them off.',
        'invitations_title' => 'Invitation links',
        'invitations_help' => 'A personal link for one person. They can read it without an account. For a course, the link also opens its lessons.',
        'none' => 'No invitation links yet.',
        'created' => 'Invitation link ready. Copy it and send it to :name.',
        'revoked' => 'Link turned off.',
        'views' => '{0} Not opened yet|{1} Opened once|[2,*] Opened :count times',
        'last_viewed' => 'last',
        'status' => [
            'active' => 'Active',
            'expired' => 'Expired',
            'revoked' => 'Turned off',
        ],
        'revoke' => 'Turn off',
        'revoke_confirm' => 'Turn this link off? Whoever has it will no longer be able to read.',
        'never_expires' => 'No end date',
        'until' => 'until',
    ],

    'invite' => [
        'invitee_name' => 'Who is it for?',
        'invitee_placeholder' => 'Their name',
        'message' => 'Message (optional)',
        'message_placeholder' => 'A note shown above the content',
        'expires' => 'Link works for',
        'expires_options' => [
            0 => 'No end date',
            1 => '1 day',
            7 => '1 week',
            30 => '1 month',
            90 => '3 months',
            365 => '1 year',
        ],
        'create' => 'Create link',
    ],

    'workspace_badge' => 'Public',

    'errors' => [
        'unknown_access' => 'Choose public or private.',
        'too_many_invitations' => 'This already has :max active links. Turn some off first.',
    ],
];
