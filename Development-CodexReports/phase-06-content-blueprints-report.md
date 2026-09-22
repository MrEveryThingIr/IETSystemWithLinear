# Phase 6 — Content Blueprints and Unified Productized Authoring

## Status

**Complete and human-owner accepted for roadmap progression on 2026-09-22.**

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

## Human closure evidence

On 2026-09-22 the owner synchronized local branch `feat/phase-06-content-blueprints` at `b33bdcf` and completed the practical closure gate.

Local evidence:

- Phase 6 migrations `170000`, `180000` and `190000`: applied successfully to the existing database;
- focused Phase 6 gate: **20 tests / 121 assertions**;
- full test suite: **364 tests / 1934 assertions**;
- PHPStan: **no errors**;
- Vite production build: **passed**;
- `git diff --check`: **clean**;
- after a broad local Pint command reformatted 16 inherited files outside the Phase 6/CI change scope, those unrelated formatter-only rewrites were restored;
- final `git status --short`: **clean**;
- final synchronized HEAD before closure documentation: `b33bdcf`.

The owner then performed a non-exhaustive browser review and reported that the system appeared to work as expected with no blocking correctness issue. Some Content-view behavior/UX improvements remain desirable, but the owner explicitly chose to defer them rather than keep Phase 6 open.

Those improvements are tracked in `docs/CURRENT_STATE.md` under **Deferred polish backlog** and should be addressed during the later whole-system polish pass unless a specific item proves to be a correctness, security, authorization, accessibility or data-integrity blocker earlier.

**Phase 6 is therefore closed. Phase 7 — Submission / Response / Evaluation is unblocked as the next roadmap phase.**
