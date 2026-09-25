<?php

return [
    'title' => '通知',
    'help' => '持久化应用通知。实时传输只会让它更快出现，数据库收件箱才是权威来源。',
    'reconnect_boundary' => '此收件箱会定期与服务器重新同步，因此断线或漏掉实时事件不会丢失持久通知。',
    'unread' => '未读',
    'all' => '全部',
    'new' => '新',
    'mark_read' => '标记已读',
    'mark_all_read' => '全部已读',
    'open' => '打开',
    'empty' => '暂无通知',
    'empty_help' => '当权威 IET 事件需要您关注时，持久通知会显示在这里。',
    'messages' => [
        'generic_title' => 'IET 更新',
        'plan_reminder_title' => '即将开始：:title',
        'plan_reminder_body' => '计划时间：:time。',
    ],
];
