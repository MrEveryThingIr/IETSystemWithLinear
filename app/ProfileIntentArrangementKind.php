<?php

namespace App;

enum ProfileIntentArrangementKind: string
{
    case OwnershipTransfer = 'ownership_transfer';
    case TemporaryUse = 'temporary_use';
    case Service = 'service';
    case Financing = 'financing';
    case Collaboration = 'collaboration';
    case Other = 'other';
}
