<?php

return [
    'source' => '来源付款',
    'return_mode' => '退还类型',
    'unused' => '退还未使用预付款',
    'applied_return' => '撤销已分配付款',
    'obligation' => '已确认债务',
    'advance_paid' => '已付预付款',
    'advance_received' => '已收预付款',
    'not_debt' => '预付款余额不会自动成为应偿还债务。',
    'no_net_debt' => '净债务为零',
    'review' => '需要审核',
    'history' => '付款核算',
    'applied' => '已分配',
    'available' => '可用预付款',
    'reserved' => '预留退还',
    'returned' => '已退还',
    'validation' => [
        'source' => '请选择方向相反的已确认来源付款并填写原因。',
        'review' => '此账本有来源不明的历史退款，需要审核。',
        'obligation' => '请选择正在撤销付款的已确认债务。',
        'capacity' => '金额超过可用余额或已分配金额。',
    ],
    'unrecorded' => '已确认工作，等待账本确认金额',
    'identity' => '已确认债务 = 已分配现金 + 非现金结算 + 剩余债务。账本尚未确认的工作价值单独计算。',
    'legend_on_account' => '已确认但尚未分配的款项。它是预付款余额，不会自动成为退款债务。',
];
