<?php

namespace App;

enum ProfileIntentExchangePreference: string
{
    case CashOnly = 'cash_only';
    case CashPreferredOpenHybrid = 'cash_preferred_open_hybrid';
    case OpenHybrid = 'open_hybrid';
    case DiscussLater = 'discuss_later';
}
