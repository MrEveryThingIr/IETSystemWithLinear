# Phase 20 — Groups / Communities social composition

## Status

Runtime-green on `feat/ideal-v1-20-group-community-composition`.

Runtime checkpoint:

- SHA: `6782bdaeb2a732ec2f9ae8f72c76dc5f311d6f17`
- CI: `36126321777`
- **520 tests / 3219 assertions**
- Pint: 651 PHP files green
- PHPStan: green
- Vite: green
- migration rollback/reapply: green
- scheduler/database queue smoke: green
- SQLite backup/restore smoke: green
- npm audit: 0 vulnerabilities
- Composer audit: no advisories

## Purpose

Make a Group feel like a coherent social/application environment while keeping the underlying domain kernels independent and authoritative.

The implementation follows the rule:

> compose existing truth; do not clone it into Group-specific storage.

## Member-facing Community

`groups.community` is the member-facing entry point.

It composes, as independently authorized:

- Group Spaces;
- Space Conversation;
- published Content;
- active membership + Group roles;
- visible member Needs/Offers;
- Plans/activities in visible GroupSpace Contexts;
- reviewable Submissions;
- Relationships/projects already visible to the current participant;
- Context timelines.

The existing Group Show page remains governance/settings.

## No duplicate storage

Phase 20 introduces **no migration**.

It does not create:

- Group posts;
- Group articles;
- Group plans;
- Group needs/offers;
- Group relationships;
- Group submissions;
- Group timelines.

Those remain normal Content, Plan, ActorProfileIntent, Relationship, Submission and Context/Conversation/Timeline records.

## Authorization composition

Community visibility is the intersection of the existing kernels:

- Group: `GroupPolicy::view`;
- Space: `GroupSpacePolicy::view`;
- Content: `SpaceContentPolicy::view`;
- Intent: `ActorProfileIntentPolicy::view`;
- Plan: `PlanPolicy::view`;
- Submission: Context review authority + Submission policy;
- Relationship: Relationship policy and current-viewer participation.

Group membership is never treated as a wildcard permission into another domain.

## Privacy guarantees

A Group member cannot use Community to discover:

- a restricted Space they cannot view;
- a private Intent;
- a private Profile identity merely because a visible Intent exists;
- a Relationship they do not participate in;
- a Submission from a Context they cannot review.

## Proofs

Automated Phase 20 tests prove:

1. one Community page composes existing Content, Plan, Intent and Relationship records;
2. the composition introduces no Story/domain-copy records;
3. restricted Space Plans remain hidden from an unauthorized member;
4. private Intents remain hidden;
5. unrelated Relationships remain hidden;
6. an outsider cannot open the Group Community page;
7. the Group list opens Community as the normal member-facing entry.

## UX boundary

Community is for using the Group.

Governance/settings remains for:

- roles/permissions;
- members;
- invitations/admissions;
- agreements;
- Space configuration.

This prevents operational governance controls from becoming the only way users experience a Group.

## Next

Phase 21 — **Home / Today personal operating view**.

Home/Today should summarize the current user's real actionable state across the existing kernels without becoming another source of truth.
