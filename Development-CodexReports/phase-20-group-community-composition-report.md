# Phase 20 Closure — Group / Community Composition

## Result

Phase 20 turns Groups into usable social/application compositions without duplicating domain storage.

Runtime checkpoint:

- branch `feat/ideal-v1-20-group-community-composition`
- base integration SHA `7d5e92cd834335e3b7aa630e41a405231fa8efe0`
- runtime SHA `6782bdaeb2a732ec2f9ae8f72c76dc5f311d6f17`
- CI `36126321777`
- **520 tests / 3219 assertions**
- Pint 651 PHP files
- PHPStan/Vite/migration/ops/backup/security gates green

## Architecture

`GroupCommunityProjection` is a read/composition service only.

It queries the already-authoritative GroupSpace Contexts and participant domains, then applies each existing policy before surfacing a record.

No database migration is required.

## User experience

The Group list now sends an active member to Community first.

Community shows visible Spaces and provides capability links to Conversation, Content, Planner, Timeline and review queues. It also composes People/Roles, visible Needs/Offers, recent published Content, Plans, and Relationships/projects.

The existing Group overview remains the explicit governance/settings surface.

## Negative guarantees

Group membership is not a privacy bypass.

Community creates no copy of Content/Plan/Intent/Relationship/Submission/Timeline truth.

A card/link does not create or mutate its underlying domain record.

## Documentation

Closure adds:

- `docs/PHASE_20_GROUP_COMMUNITY_COMPOSITION.md`;
- System Manual Chapter 25;
- community-specific contextual Help;
- Checkpoint 20 in the cumulative local acceptance worksheet;
- roadmap/current-state/continuous handoff updates.

## Next

Phase 21 — Home / Today personal operating view.
