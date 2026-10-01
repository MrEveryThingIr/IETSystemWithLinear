# Single Publication Authority

IET has one revelation/publication authority:

```text
FeatureSurfaceRegistry
  -> FeatureSurfaceGrantService
  -> RequireFeatureSurface / EnforceMappedFeatureSurface
```

`EnforceReleaseSurface` is retained only as a no-op compatibility class and is removed from the web middleware stack.

`release.profile` may still influence domain behavior or presentation elsewhere, but it must not independently decide which product pages exist for a user.

## Separation of concerns

### Publication

Answers:

> Is this complete workflow part of the user's application?

Controlled by the feature-surface graph.

### PlatformCapability

Answers:

> Which privileged platform action may this user perform?

Examples:

- CreateGroups
- ManageUsers
- ManageActors
- ManagePlatformAccess
- ManageConcepts
- ViewPlatformAudit
- ManageExchange

These remain useful and are not replaced by publication.

### Record/domain authorization

Answers:

> Which actual business/group/case/contract may the user operate on?

Handled by policies/domain rules.

## Root operator invariant

Canonical platform super-admin:

- is represented only by an active `platform_access_grants` row with role `superadmin`
- must remain an active, verified User
- bypasses publication grants
- `User::hasPlatformCapability(...)` returns true when that method exists
- does not bypass domain Gates/Policies or record invariants
- therefore must never enter an ordinary "request access" ceremony

There is no parallel super-admin table or implicit User flag. Grant and revocation
evidence stays in `platform_access_grants`; publication bypass derives from that
same auditable authority.
