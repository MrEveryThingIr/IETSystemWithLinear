<?php

return [
    'source' => 'Source payment',
    'return_mode' => 'Return type',
    'unused' => 'Return unused advance',
    'applied_return' => 'Reverse an applied payment',
    'obligation' => 'Accepted obligation',
    'advance_paid' => 'Advance paid',
    'advance_received' => 'Advance received',
    'not_debt' => 'Advance credit is not a repayable debt.',
    'no_net_debt' => 'No net debt',
    'review' => 'Needs review',
    'history' => 'Payment accounting',
    'applied' => 'Applied',
    'available' => 'Available advance',
    'reserved' => 'Reserved for return',
    'returned' => 'Returned',
    'validation' => [
        'source' => 'Choose a confirmed source payment in the reverse direction and enter a reason.',
        'review' => 'This book contains unresolved historical refunds. Review is required.',
        'obligation' => 'Choose the accepted obligation whose payment is being reversed.',
        'capacity' => 'The amount exceeds the available credit or applied amount.',
    ],
    'unrecorded' => 'Confirmed work awaiting an accepted amount in the book',
    'identity' => 'Accepted debt = cash applied + noncash settlement + remaining debt. Confirmed work not yet accepted in the book is separate.',
    'legend_on_account' => 'Unallocated confirmed money. It is credit, not an automatic refund debt.',
];
