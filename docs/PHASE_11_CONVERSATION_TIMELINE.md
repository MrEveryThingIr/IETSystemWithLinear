# Phase 11 — Conversation + Unified Timeline

## Status

Remote implementation complete and green on `feat/ideal-v1-11-conversation-timeline`.

Runtime checkpoint:

~~~text
SHA: 6dcd43a056290730eaab608b6291a96ce8ff4b62
GitHub Actions: 36030315938
448 tests / 2533 assertions
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Kernel checkpoint:

~~~text
SHA: 0a0d0c4b38ee9629413a1db12536e9ffbfbb9b0c
GitHub Actions: 36029544109
444 tests / 2503 assertions
~~~

Baseline:

`1ea4e1d84dedf0f666fcc3818ac75d7b21f5abb9`

## Purpose

Give Contexts one durable collaboration-message substrate and one understandable read-only activity view while preserving a strict authority boundary:

> conversation records what people said and referenced; authoritative domain state changes only through explicit domain Actions/events.

## Conversation kernel

Phase 11 introduces one generic Context-scoped Conversation kernel:

- `Conversation` belongs to one Context;
- one default `main` Conversation is created lazily per Context;
- `ConversationMessage` records immutable author/body/reply evidence;
- replies must remain inside the same Conversation;
- messages may reuse existing Assets from the same Context;
- messages may reuse exact Content Evidence References from the same Context;
- no file, Content item or evidence target is copied merely because a message references it.

The implemented posting surfaces are intentionally limited to writable GroupSpace, Admission and Relationship Contexts. Reference/Personal Contexts are not turned into generic chat surfaces by this phase.

## GroupSpace cutover

Legacy GroupSpace chat is migrated, not duplicated.

Migration `2026_09_24_020000_create_context_conversations.php`:

1. creates the generic Conversation/message/reference tables;
2. creates a main Conversation for existing GroupSpace Contexts;
3. copies legacy `group_space_messages` while preserving message IDs and reply links;
4. removes the old message table;
5. keeps the old PHP GroupSpace message path only as a deprecated compatibility adapter onto the new authoritative table.

Active Group chat writes now delegate to `PostContextMessage`.

There is no dual-write and no second message truth.

## Authorization and non-authority

Reading Conversation/Timeline requires Context view authority.

Posting requires the Context's existing `interactContent` authority. Therefore:

- proposed Relationship Contexts are inspectable but read-only;
- active Relationship participants may converse;
- ended/cancelled Relationships remain readable and non-writable;
- Admission terminal-state rules remain enforced;
- GroupSpace deny/restricted/archive rules remain enforced;
- outsiders cannot cross Context boundaries;
- replies, Assets and exact evidence references cannot cross Context boundaries.

A message containing words such as “I agree”, “approved”, “paid”, “accepted” or “owner” creates only message evidence. It cannot activate a Relationship, approve Admission, create Membership, accept a Contract, create ownership, create an obligation, record Fulfillment or settle money.

## Unified Timeline

Timeline is a projection, not a table.

`ContextTimeline` reconstructs entries from durable source truth currently available in the Context:

- Conversation messages;
- Relationship events;
- Admission events;
- Content lifecycle events the viewer is separately authorized to read.

Every Timeline entry links back to its authoritative source surface.

Reloading recomputes the same result from source records. No independent Timeline lifecycle or mutable Timeline state exists.

## User experience

Implemented surfaces:

- generic **Conversation** page for Context collaboration;
- replies;
- reuse of existing Context Assets;
- exact Content Evidence Reference cards;
- generic **Timeline** page;
- source links from Timeline entries;
- Relationship page → Conversation / Timeline / Content;
- Admission page → Conversation / Timeline / Content;
- GroupSpace navigation exposes Timeline while existing Group chat remains compatible.

## Story proof

### Alice ↔ Bob Relationship

After Alice/Bob activate their direct Relationship:

1. either participant can open Conversation;
2. they can reply and reference existing Context evidence;
3. Bob may type “I agree to everything in this chat”;
4. the message appears in Conversation and Timeline;
5. Relationship status and RelationshipEvent count do not change;
6. Timeline links Relationship lifecycle entries back to the Relationship source.

### Admission

A candidate can use the same Conversation kernel inside an authorized mutable Admission Context. Admission lifecycle events appear in the Timeline, but messages do not approve or finalize Admission.

### GroupSpace

Existing Group chat continues to work after migration through the generic message store. GroupSpace authorization remains unchanged. Its Timeline can reconstruct Context messages/source activity without a Group-specific timeline table.

## Remote validation

Runtime CI `36030315938` proves:

- PHPUnit: **448 passed / 2533 assertions**;
- PHPStan: no errors;
- formatting gate: passed;
- Vite production build: passed;
- Phase migration rollback/reapply: passed;
- scheduler smoke: passed;
- database queue smoke: passed with no failed jobs;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

Focused coverage proves:

- “I agree” message text has no Relationship lifecycle authority;
- proposed Relationship Contexts cannot accept messages;
- Admission and GroupSpace compose the same Conversation kernel;
- replies cannot cross Contexts;
- one main Conversation is reused per Context;
- GroupSpace legacy behavior survives the datastore cutover;
- Timeline reconstructs the same source keys on reload;
- Timeline has no independent persistence table;
- outsiders cannot open Conversation/Timeline;
- exact evidence/Asset references are reused rather than copied;
- cross-Context attachment is rejected.

## Deferred

- realtime transport/broadcasting; reload remains authoritative;
- richer thread/topic UX beyond one default main Conversation;
- reviewer-internal/private Conversation audiences;
- upload-from-composer convenience (Phase 11 reuses existing Context Assets rather than duplicating upload logic);
- richer Timeline event families as later domains land;
- mobile/RTL/accessibility cumulative owner acceptance.

## Exit gate

Phase 11 exits when:

- Conversation is Context-scoped and authorization-safe;
- legacy Group chat has one authoritative store;
- Relationship/Admission/Group composition is proven;
- attachments/references reuse existing Context artifacts;
- Timeline is reconstructed from source truth with source links;
- message wording cannot change authoritative lifecycle;
- remote CI and canonical docs are green.
