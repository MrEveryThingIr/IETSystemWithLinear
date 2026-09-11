<?php

namespace App;

enum GroupPermission: string
{
    case Participate = 'participate';
    case ManageGroup = 'manage_group';
    case ManageMembers = 'manage_members';
    case ManageRoles = 'manage_roles';
    case ManageInvitations = 'manage_invitations';
    case ApproveRoleChanges = 'approve_role_changes';
    case ManageAdmissions = 'manage_admissions';
    case ManageAgreements = 'manage_agreements';
    case ViewGroupAudit = 'view_group_audit';
    case ManageSimulations = 'manage_simulations';
    case TransferOwnership = 'transfer_ownership';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
