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
    case ExchangeDeposit = 'exchange_deposit';
    case ExchangeCashout = 'exchange_cashout';
    case InternalCharge = 'internal_charge';
    case Reversal = 'reversal';
    case Correction = 'correction';
}
