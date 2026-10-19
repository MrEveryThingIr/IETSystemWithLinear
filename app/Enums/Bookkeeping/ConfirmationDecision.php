<?php

namespace App\Enums\Bookkeeping;

enum ConfirmationDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
