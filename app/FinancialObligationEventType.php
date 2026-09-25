<?php

namespace App;

enum FinancialObligationEventType: string
{
    case Recognized = 'recognized';
    case AccountingPosted = 'accounting_posted';
    case SettlementProposed = 'settlement_proposed';
    case SettlementConfirmed = 'settlement_confirmed';
    case SettlementRejected = 'settlement_rejected';
    case SettlementAccountingPosted = 'settlement_accounting_posted';
}
