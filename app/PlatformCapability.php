<?php

namespace App;

enum PlatformCapability: string
{
    case CreateGroups = 'create_groups';
    case ManageUsers = 'manage_users';
    case ManageActors = 'manage_actors';
    case ManagePlatformAccess = 'manage_platform_access';
    case ViewPlatformAudit = 'view_platform_audit';
}
