<?php

namespace App;

enum ContractEventType: string
{
    case Created = 'created';
    case VersionProposed = 'version_proposed';
    case PartyAccepted = 'party_accepted';
    case VersionAccepted = 'version_accepted';
    case VersionActivated = 'version_activated';
    case VersionSuperseded = 'version_superseded';
}
