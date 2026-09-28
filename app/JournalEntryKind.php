<?php

namespace App;

enum JournalEntryKind: string
{
    case OpeningBalance = 'opening_balance';
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case IetExchangeDeposit = 'iet_exchange_deposit';
    case IetExchangeCashout = 'iet_exchange_cashout';
    case IetSettlementReserve = 'iet_settlement_reserve';
    case IetSettlementTransfer = 'iet_settlement_transfer';
    case IetSettlementRelease = 'iet_settlement_release';
    case ObligationRecognition = 'obligation_recognition';
    case Settlement = 'settlement';
    case Reversal = 'reversal';
    case Correction = 'correction';
}
