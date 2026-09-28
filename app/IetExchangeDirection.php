<?php

namespace App;

enum IetExchangeDirection: string
{
    case Deposit = 'deposit';
    case Cashout = 'cashout';
}
