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
    case IetExchangeDeposit = 'iet_exchange_deposit';
    case IetExchangeCashout = 'iet_exchange_cashout';
    case IetFlowCharge = 'iet_flow_charge';
    case IetInternalTransfer = 'iet_internal_transfer';
    case Reversal = 'reversal';
    case Correction = 'correction';
}
