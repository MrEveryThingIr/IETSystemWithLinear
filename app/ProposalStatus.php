<?php

namespace App;

enum ProposalStatus: string
{
    case Negotiating = 'negotiating';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
