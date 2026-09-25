<?php

return [
    'title' => 'Notifications',
    'help' => 'Durable app notifications. Realtime delivery may make them appear sooner, but the database inbox is authoritative.',
    'reconnect_boundary' => 'This inbox periodically reconciles with the server. A missed realtime event or reconnect cannot make a durable notification disappear.',
    'unread' => 'Unread',
    'all' => 'All',
    'new' => 'New',
    'mark_read' => 'Mark read',
    'mark_all_read' => 'Mark all read',
    'open' => 'Open',
    'empty' => 'No notifications',
    'empty_help' => 'When an authoritative IET event requires your attention, its durable notification will appear here.',
    'messages' => [
        'generic_title' => 'IET update',
        'plan_reminder_title' => 'Upcoming: :title',
        'plan_reminder_body' => 'Scheduled for :time.',
    ],
];
