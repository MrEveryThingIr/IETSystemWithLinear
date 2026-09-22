# Phase 6 — Content Blueprints and Unified Productized Authoring

## Status

**Runtime technically complete and remote-CI green. Final owner-local/browser/mobile/RTL acceptance is pending.**

Branch:

`feat/phase-06-content-blueprints`

Accepted Phase 5 baseline:

`f3e93e953dfe58983d2da07bf9bac080896fa8ac`

Frozen Phase 6 runtime candidate:

`ad07445b16a708b4efd67461f5cef12201ffa8b1`

Final code-bearing GitHub Actions proof:

- run `35739828516`;
- PHPUnit: **364 passed / 1934 assertions**;
- PHPStan: **no errors**;
- Pint: **258 files passed**;
- Vite production build: **passed**;
- npm audit: **0 vulnerabilities**;
- Composer security audit: **clean**;
- migration / scheduler / database-queue smoke: **passed**;
- SQLite backup → restore smoke: **passed**;
- Phase 6 migrations `170000`, `180000`, and `190000`: **ran successfully**.

## Why Phase 6 changed the product direction

Phase 5 proved that Content can live in Personal, GroupSpace and Admission Contexts, but its generic Context UI was much simpler than the previously mature Group Content experience.

Phase 6 deliberately avoids creating a second Content system.

The architecture is now:

~~~text
Context
→ one Content identity/revision kernel
→ optional Blueprint recipe/provenance
→ one Studio
→ one Reader
→ exact immutable evidence references
~~~

Personal, GroupSpace and Admission Content all reuse the same underlying Content, Definition, Revision, Block, Asset, Presentation, Outline, interaction and publication-evidence machinery.

The legacy `SpaceContent*` names remain compatibility names only. No destructive mass rename was attempted.

## 6A — Blueprint kernel

Implemented:

- `ContentBlueprint` stable UUID identity;
- immutable `ContentBlueprintVersion`;
- canonical SHA-256 version hash;
- system / Actor / Context Blueprint scopes;
- Context-kind compatibility;
- Definition schema defaults;
- initial Blocks;
- presentation defaults;
- Concept assertion defaults;
- interaction defaults;
- authoring capability hints;
- exact active-version pointers;
- clone provenance through `cloned_from_version_id`;
- catalog/search service;
- SQLite-safe rollback.

Initial built-in Blueprint catalog:

- Note / Diary;
- Post;
- Article;
- Activity / Report;
- Evidence / Work Sample;
- Media Album;
- Book / Booklet;
- Lesson;
- Workbook Page;
- Questionnaire shell.

The Questionnaire Blueprint authors the human-facing artifact only. Structured respondent Submissions remain Phase 7.

## 6B — Blueprint-instantiated Content

`CreateContentFromBlueprint` now creates ordinary Content atomically.

It:

- authorizes against the target Context;
- verifies the exact active Blueprint version is visible in that Context;
- materializes/reuses one Context-local hidden Definition for the exact Blueprint version;
- binds both Definition and Content to that exact Blueprint version;
- creates revision 1 with Blueprint schema, presentation and composition mode;
- creates initial Blocks directly on revision 1 without synthetic intermediate revisions;
- creates revision-scoped Concept assertions from Blueprint semantic defaults;
- works without a fake Group;
- lets an Admission candidate use an approved system Blueprint without Definition-management authority or Membership.

Existing Content is pinned to the Blueprint version used at creation. A later Blueprint version never silently upgrades existing Content.

## Clone and upgrade semantics

`CloneContentBlueprint` creates a new Actor-scoped Blueprint identity from an exact source version.

The clone:

- records `cloned_from_version_id`;
- receives its own immutable version identity;
- preserves the source configuration/hash;
- cannot mutate the source Blueprint/version.

There is intentionally **no silent Blueprint upgrade**. Any future upgrade must be explicit and create new draft evidence rather than rewriting historical authored Content.

## Interaction defaults are creation defaults, not permanent Blueprint authority

The final hardening migration adds a Content-level `interaction_settings` snapshot.

This matters architecturally:

- Blueprint remains a creation recipe;
- new Content snapshots annotation/reaction/default-visibility settings at creation;
- later Blueprint changes do not silently change existing Content behavior;
- reader UI honors the Content snapshot;
- annotation/reaction Actions enforce the snapshot server-side;
- legacy non-Blueprint Content retains the existing permissive interaction behavior.

This avoids making Blueprint identity a permanent hidden authorization/type system.

## 6C — Unified authoring surface

The generic Context library is now the canonical creation entry point.

Normal creation is progressive:

~~~text
Create Content
→ choose/search Blueprint
→ title + required fields
→ More details only when optional fields are needed
→ create
→ unified Studio
~~~

Raw Definition authoring remains available only inside the advanced/custom-authoring disclosure.

The Context Studio now exposes the mature Content capabilities for any Context:

- structured fields;
- file/media upload through the existing private Asset pipeline;
- media rights/readiness state;
- Blocks/layout;
- appearance/presentation;
- Outline/contained Content;
- immutable revision history;
- publish;
- archive/restore;
- publication evidence status.

Existing Group Content URLs are retained as compatibility routes, but they redirect into the same generic Context Studio/Reader instead of maintaining a second application implementation.

## 6D — Unified Reader and evidence locators

The generic Context Reader now carries the mature interaction experience:

- reactions;
- annotations/replies/questions;
- field/block/asset/revision targets;
- attached interaction media;
- published Outline;
- exact revision display;
- Context authorization.

Phase 6 adds immutable `ContentEvidenceReference` records.

An evidence reference binds:

- Context;
- Content UUID/identity;
- exact sealed published Revision;
- target kind;
- optional exact revision-specific:
  - field key;
  - Block UUID;
  - Asset placement UUID;
  - relationship UUID.

Evidence creation requires a verifiable sealed publication manifest.

Evidence/revision URLs re-authorize access against current Context/Content authorization and then resolve the exact historical sealed edition. A later publication does not rewrite old evidence.

## Required proof cases

Automated coverage proves:

### Personal evidence portfolio

A Personal Context Evidence / Work Sample can:

- carry structured description;
- upload an image/file through the shared Asset pipeline;
- place media in Blocks;
- publish;
- create an exact Asset evidence reference;
- render that exact historical evidence.

### Classroom booklet

A GroupSpace Book / Booklet can contain a separately revisioned/published Lesson through the same Content relationship/Outline kernel.

### Admission evidence before Membership

An Admission candidate can create Blueprint-based evidence before Membership while remaining denied ordinary GroupSpace authority.

### Simple diary

A Personal Note / Diary can be created through the Blueprint-first flow without exposing Definition administration.

### Blueprint provenance

Tests prove:

- immutable Blueprint versions;
- no silent upgrade of existing Content;
- context-local materialized Definition reuse;
- exact clone provenance;
- initial Blocks and presentation on revision 1;
- interaction defaults snapshotted onto Content.

### Historical evidence

Tests prove:

- exact sealed revision permalink remains on the old edition after a newer publication;
- revision/field/block/asset evidence targets remain bound to the exact sealed revision;
- historical evidence resolution does not drift to the latest Content revision.

## Skill proof / reputation boundary

Phase 6 intentionally **does not** turn Content count into skill percentage or reputation.

Current Profile proficiency remains self-reported.

The new Content evidence seam is suitable for later evaluation, where separate concepts should remain distinct:

- self proficiency;
- evidence maturity;
- verification/evaluation;
- outcomes/fulfillment;
- explainable reputation.

The owner's progressive proof-level idea remains valuable as a future configurable policy pattern, but counts/thresholds must not become hard-coded Content truth. Quality, recency, reviewer diversity and domain-specific rubric can be added by later verification/reputation capabilities.

No Phase 7 Submission/Evaluation or Phase 14 Contract authority was pulled into Phase 6.

## Major runtime commits

- `4a5523b` — versioned Blueprint kernel;
- `d50e9f8` — atomic Content creation from Blueprints;
- `df79c49` — Blueprint-first generic Context Studio;
- `4d10d83` — unified Context authoring + evidence references;
- `16161f1` — Blueprint provenance + immutable revision permalinks;
- `77be33a` — media/evidence closure proof;
- `ad07445` — progressive optional fields + Content interaction-setting snapshots.

Intermediate style/static-analysis/migration-hardening commits are intentionally omitted from this summary but remain in Git history.

## Deliberate boundaries

Phase 6 does not:

- create structured questionnaire responses; Phase 7 owns Submission/Response/Evaluation;
- infer or award reputation from Content;
- make evidence references verification/endorsement by themselves;
- silently upgrade Content when a Blueprint changes;
- mass-rename the proven `SpaceContent*` substrate;
- convert all domain truth into Content;
- grant Admission candidates ordinary Group access.

## Remaining human gate

Before formal Phase 6 closure, the owner must synchronize the branch locally and verify:

- the three Phase 6 migrations apply on the existing database;
- focused Phase 6 tests pass;
- full PHPUnit/PHPStan/Pint/Vite pass;
- working tree remains clean;
- My Content:
  - Blueprint picker is understandable;
  - required fields appear first;
  - optional fields appear only after **More details**;
  - created Content opens the full Studio;
- Evidence / Work Sample:
  - media upload works;
  - block composition works;
  - publication works;
  - exact evidence link shows the old sealed edition after later revision;
- GroupSpace:
  - old Group Content URLs safely reach the generic Context experience;
  - Book/Booklet → Lesson Outline works;
- Admission:
  - candidate can create evidence before Membership;
  - candidate still cannot access ordinary GroupSpace Content;
- narrow mobile widths have no horizontal page overflow or clipped controls;
- Persian and Arabic RTL layout remains usable.

Only after that owner-local/browser/mobile/RTL gate passes should Phase 6 be formally closed and Phase 7 begin.
