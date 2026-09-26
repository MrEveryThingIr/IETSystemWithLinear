<?php

namespace App;

enum PlatformCapability: string
{
    case CreateGroups = 'create_groups';
    case ManageUsers = 'manage_users';
    case ManageAccessInvitations = 'manage_access_invitations';
    case ManageActors = 'manage_actors';
    case ManagePlatformAccess = 'manage_platform_access';
    case ManageConcepts = 'manage_concepts';
    case ViewPlatformAudit = 'view_platform_audit';
}
