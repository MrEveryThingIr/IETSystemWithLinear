<?php

namespace App;

enum ProposalDecisionKind: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
}
