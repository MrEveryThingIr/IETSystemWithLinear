<?php

return [
    'open_app' => '打开应用',

    'index' => [
        'nav' => '公开文章',
        'title' => '公开文章',
        'description' => '作者选择与所有人分享的文章、随笔、课程与课时。',
        'search' => '搜索标题',
        'kind' => '类型',
        'all_kinds' => '全部类型',
        'apply' => '显示',
        'empty' => '还没有公开分享的内容。',
        'no_results' => '没有符合此搜索的公开文章。',
    ],

    'home' => [
        'title' => '最新公开文章',
        'help' => '由作者分享，所有人都可阅读。',
        'all' => '查看全部',
    ],

    'reader' => [
        'contents' => '目录',
        'read_only' => '你正在阅读分享的版本。评论等工具仅对成员开放。',
    ],

    'invitation' => [
        'greeting' => ':name，你好：',
        'invited_by' => ':name 邀请你阅读此内容。',
        'someone' => '有人',
        'expires' => '此链接有效期至',
        'unavailable_title' => '此链接已失效',
        'unavailable_revoked' => '分享者已关闭此链接。',
        'unavailable_expired' => '此链接已过期。',
        'unavailable_unavailable' => '链接指向的内容暂时无法访问。',
        'ask_inviter' => '你可以请 :name 重新发送链接。',
    ],

    'share' => [
        'button' => '分享',
        'title' => '对外分享',
        'not_published' => '请先发布。只有已发布的版本可以分享，之后的修改在再次发布前保持私密。',
        'not_allowed' => '只有作者可以对外分享。在群组或企业中，作者还需要能管理该处的内容。',
        'public_title' => '网络上的任何人',
        'public_help' => '任何人无需登录即可阅读已发布的版本，并会列在公开文章页面上。',
        'public_help_unlisted' => '任何拥有链接的人无需登录即可阅读已发布的版本。',
        'public_on' => '公开',
        'public_off' => '私密',
        'make_public' => '设为公开',
        'make_private' => '设为私密',
        'public_link' => '公开链接',
        'copy' => '复制',
        'copied' => '已复制',
        'open' => '打开',
        'made_public' => '现在所有人都可以阅读。',
        'made_private' => '已恢复为私密。邀请链接在你关闭前仍然有效。',
        'invitations_title' => '邀请链接',
        'invitations_help' => '给某一个人的专属链接，无需账号即可阅读。课程的邀请链接也会开放其中的课时。',
        'none' => '还没有邀请链接。',
        'created' => '邀请链接已生成。复制后发送给 :name。',
        'revoked' => '链接已关闭。',
        'views' => '{0} 尚未打开|{1} 已打开 1 次|[2,*] 已打开 :count 次',
        'last_viewed' => '最近',
        'status' => [
            'active' => '有效',
            'expired' => '已过期',
            'revoked' => '已关闭',
        ],
        'revoke' => '关闭',
        'revoke_confirm' => '关闭此链接？持有链接的人将无法再阅读。',
        'never_expires' => '无截止日期',
        'until' => '至',
    ],

    'invite' => [
        'invitee_name' => '分享给谁？',
        'invitee_placeholder' => '对方姓名',
        'message' => '留言（可选）',
        'message_placeholder' => '显示在内容上方的附言',
        'expires' => '链接有效期',
        'expires_options' => [
            0 => '无截止日期',
            1 => '1 天',
            7 => '1 周',
            30 => '1 个月',
            90 => '3 个月',
            365 => '1 年',
        ],
        'create' => '生成链接',
    ],

    'workspace_badge' => '公开',

    'errors' => [
        'unknown_access' => '请选择公开或私密。',
        'too_many_invitations' => '此内容已有 :max 个有效链接，请先关闭一些。',
    ],
];
