<?php

namespace App;

enum ProposalEventType: string
{
    case Created = 'created';
    case VersionProposed = 'version_proposed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
    case Cancelled = 'cancelled';
}
