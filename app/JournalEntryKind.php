<?php

namespace App;

enum JournalEntryKind: string
{
    case OpeningBalance = 'opening_balance';
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case ObligationRecognition = 'obligation_recognition';
    case Settlement = 'settlement';
    case Reversal = 'reversal';
    case Correction = 'correction';
}
