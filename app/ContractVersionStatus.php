<?php

namespace App;

enum ContractVersionStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Active = 'active';
    case Superseded = 'superseded';
}
