# ADR-001 — Identity, authority, participation, and simulation boundaries

- **Status:** Accepted
- **Accepted:** 2026-09-11
- **Decision authority:** Human owner
- **Scope:** Foundation milestones F0–F4 and simulation milestones S1–S4

## Context

Everything needs secure real-world workflows and realistic simulations without duplicating its domain model or allowing simulated activity to create live authority, evidence, reputation, obligations, or value. Earlier proposals incorrectly coupled identity to Group participation, proposed one Actor per Group, and considered a mirrored simulation schema. Those approaches conflict with the Project Compass and are rejected.

Accepted decisions are versioned and are not silently edited. A material change requires a superseding ADR approved by the human owner.

## Decisions

### 1. Identity and participation remain distinct

- `User` owns credentials, verification, sessions, status, locale, and platform access.
- `Actor` is a stable domain participant and is never Group-specific.
- `Membership` is the contextual Actor–Group relationship and owns contextual profile, status, roles, and responsibilities.
- Every active registered human User has exactly one primary human Actor.
- A human Actor may have zero or more Group Memberships; a User may have zero active Memberships.
- Accountless Actors are allowed for legitimate organization, system, AI, and simulation uses.
- Organization control and delegation use explicit grants; they never reassign `Actor.user_id`.

### 2. User/Actor binding is immutable in ordinary workflows

Generic Actor editing cannot change `Actor.user_id`. A dedicated, audited `RelinkActorIdentity` recovery action is the only exception. It requires active Superadmin authority, recent authentication, row locks, a reason and correlation ID, and a target User without an existing primary Actor. Historical Actors are archived or deactivated rather than physically deleted.

### 3. Platform authority belongs to User

Platform authority is represented by auditable `platform_access_grants`, not Actor type, Group role, or a permanent `users.is_superadmin` flag. The initial platform role is `superadmin`, with these capabilities:

- `create_groups`
- `manage_users`
- `manage_actors`
- `manage_platform_access`
- `view_platform_audit`

Only active, verified Users with an unrevoked grant receive capabilities. The last active Superadmin cannot be revoked. Platform authority does not create Group Membership or implicit cross-Group access.

### 4. Administrator bootstrap is console-only

Normal registration is invitation-oriented. `platform:bootstrap-superadmin {email}` is the one-time exception. It runs transactionally only when no active platform administrator exists, creates or selects an active verified User, ensures a primary Actor, creates an audited grant, and refuses silent replacement. It is never exposed as a web route.

### 5. Group creation uses a platform capability

Group creation requires `create_groups`. Initially Superadmin receives it, but application code checks the capability rather than a role name. Creation transactionally creates an active creator Membership and grants baseline `Member` plus `Owner`; a Group cannot complete creation without an active Owner.

### 6. Contextual roles are additive

An Actor may hold multiple roles in one Group. Active Membership is required for role authority. Every active member has baseline `Member`; `Owner` is an additional governance role. Permissions are the union of assigned roles and never cross Group boundaries.

Built-in roles have immutable internal identities. Their names cannot be created, renamed, deleted, or overwritten case-insensitively; localized display labels do not change identity. Custom roles cannot mutate built-ins. Ownership transfer is a dedicated transaction, and the final active Owner cannot lose Owner, leave, be removed, or be suspended.

Role requests explicitly grant or revoke one requested role and never replace all roles.

### 7. Group permission catalog

The initial catalog is frozen:

- `participate`
- `manage_group`
- `manage_members`
- `manage_roles`
- `manage_invitations`
- `approve_role_changes`
- `manage_admissions`
- `manage_agreements`
- `view_group_audit`
- `manage_simulations`
- `transfer_ownership`

`Member` receives `participate`; `Owner` receives all Group permissions. `transfer_ownership` is Owner-only. UI and backend use the same capabilities. Literal Owner checks are reserved for genuine ownership invariants.

### 8. Membership lifecycle

States are `active`, `suspended`, `left`, and `removed`:

```text
active -> suspended | left | removed
suspended -> active | left | removed
left -> active       only through a new approved admission
removed -> active    only through a new approved admission
```

Suspension preserves assigned roles but disables authority. Leaving or removal revokes custom and Owner roles. Readmission restores baseline `Member` only. Each transition creates an immutable Membership event. Losing the final Membership does not delete the User or Actor.

### 9. Invitation-oriented registration

A valid invitation supplies only an authorized preview and a path to authentication, registration, and admission. It never grants Membership directly. Public pages mask target email addresses. Tokens are bearer secrets and will be hashed at rest when token lookup is revised. Guests may explore public simulations without becoming trusted Users or Actors.

### 10. Simulation uses normal Groups

A Group has `mode = live | simulation` and nullable `source_group_id`. Simulation Groups are independent Group records using the same Membership, role, permission, invitation, admission, agreement, responsibility, and future domain actions. Mirrored demo tables, duplicated migrations, and connection-switching domain actions are prohibited.

### 11. Live/simulation isolation is mandatory

- Every simulation page has a persistent non-binding banner and distinct URL/identity.
- Simulation operational records cannot reference live Group-owned operational records.
- Live payment, settlement, ledger, reputation, webhook, evidence, and contract-execution adapters reject simulation context.
- Simulation evidence and acceptance never satisfy live requirements.
- Simulation agreements and contracts are visibly non-binding.
- Initial simulation notifications are in-app only; arbitrary external communication and anonymous uploads are disabled.
- Reset or deletion cannot affect a source Group.
- Cross-mode invariants are enforced and adversarially tested in backend code.

### 12. Simulated participants

Actor intrinsic kinds are `human`, `organization`, `system`, and `ai`. Simulation status is contextual, not an Actor kind. A simulation-only Actor is accountless, belongs to one `simulation_group_id`, can join only that Group, is visibly labeled, and cannot receive live evidence or reputation. Mutations retain provenance for the controlling session or User.

### 13. Guest simulation sessions

Guests receive no real User or trusted Actor. A `SimulationSession` stores a hashed random expiring token, simulation Group, controlled simulated Actors, and activity timestamps. It is rate-limited, cannot access live routes or protected configuration, and uses scenario-authorized roles and predefined synthetic documents. Registration never promotes simulated history.

### 14. Authenticated simulation collaboration

Real Actors may join simulation Groups, while all resulting activity remains simulation-scoped. Users may control designated simulated personas with explicit provenance; those personas are never merged into real Actors. Simulation invitations use normal admission workflows but cannot create live Membership or consequences.

### 15. Copying simulation designs, never history

Memberships, acceptances, signatures, Results, Evidence, Reputation, completed obligations, transactions, ledger entries, and audit events cannot be promoted to live truth. Explicit reviewed actions may copy role definitions, permission configuration, templates, forms, checklists, content templates, and scenario configuration as new live drafts with new IDs, provenance, and approvals.

### 16. System and AI authority is contextual

System and AI Actors are accountless and require both active Membership and an active Group-scoped `actor_mandate` sponsored by an authorized Actor. Mandates have expiry and constraints. Policy ceilings prevent self-expansion, self-approval, ownership transfer, platform grants, and unapproved live financial operations. Mutations record actor, mandate/sponsor, correlation, and outcome without secrets or private prompts.

### 17. Agreement evidence is exact and immutable

Live acceptance binds Agreement/version identity, version number, canonical content hash, effective period, required/reacceptance flags, accepting Actor, represented party, acting User or mandate, timestamp, and evidence schema/hash version. Published and accepted versions are immutable. Simulation acceptance exercises the lifecycle but is marked non-binding and cannot satisfy live requirements.

### 18. Reputation is derived from validated live events only

Simulation can produce practice scores, completion, readiness, feedback, and reviewable history. It cannot automatically change live Reputation, become verified Evidence, satisfy obligations, grant access, or bypass admission. A reviewer may consider it, but any live decision and justification are new live records.

### 19. Inbox is a delivery surface

The initial inbox references authoritative invitations, admissions, agreement requests, role requests, and system notifications. Dismissal does not delete source records or audit history. General communication belongs to Spaces and Content. Simulation v1 uses in-app delivery or manually shared links.

### 20. Database and time contract

Production concurrency targets MySQL 8+ with InnoDB and one writable primary. Critical invariants use transactions and row locks, and concurrency tests run against MySQL. SQLite may support fast non-concurrency tests. Timestamps are stored in UTC; User and Group timezones use IANA identifiers; local input is interpreted in the selected timezone and converted to UTC for storage.

## Rejected alternatives

- One Actor per User per Group.
- Requiring every User to retain an active Membership.
- Platform administration through Actor kind, Group role, or a permanent boolean flag.
- Mirroring every live table into a second demo schema or database.
- Promoting simulated operational history into live truth.
- Treating inbox as generic messaging or Content.

## Delivery order

1. **F0:** Preserve and establish a reproducible baseline.
2. **F1:** Lock down Actor administration and introduce platform authority.
3. **F2:** Implement additive contextual RBAC and Membership lifecycle integrity.
4. **F3:** Harden invitations, admissions, agreements, evidence, concurrency, privacy, and time handling.
5. **F4:** Close data, localization, build, CI, static-analysis, performance, and documentation gates.
6. **S1:** Establish the simulation/live boundary.
7. **S2:** Add simulated Actors and guest sessions.
8. **S3:** Add collaborative simulation.
9. **S4:** Add constrained System and AI Actor mandates.

Simulation work cannot begin until the F0–F4 foundation exit gate is satisfied.

## Consequences

The platform keeps one coherent model and enforces safety at explicit domain boundaries. This adds migrations, policies, audit records, transactional actions, and adversarial tests, but avoids identity fragmentation and permanent live/simulation drift.
