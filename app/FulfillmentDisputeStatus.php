<?php

namespace App;

enum FulfillmentDisputeStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
