<?php

namespace App\Enums\Bookkeeping;

enum WalletKind: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Bank = 'bank';
    case Other = 'other';
}
