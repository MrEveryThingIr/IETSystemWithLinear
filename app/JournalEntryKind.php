<?php

namespace App;

enum JournalEntryKind: string
{
    case OpeningBalance = 'opening_balance';
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case Reversal = 'reversal';
    case Correction = 'correction';
}
