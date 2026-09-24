<?php

namespace App;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function debitNormal(): bool
    {
        return in_array($this, [self::Asset, self::Expense], true);
    }
}
