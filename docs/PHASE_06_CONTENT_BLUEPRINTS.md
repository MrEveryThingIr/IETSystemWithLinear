# Phase 6 — Content Blueprints and Unified Productized Authoring

## Status

**Runtime technically complete / remote-CI green; final owner-local/browser/mobile/RTL acceptance pending** on `feat/phase-06-content-blueprints`.

Accepted Phase 5 baseline:

`f3e93e953dfe58983d2da07bf9bac080896fa8ac`

Frozen Phase 6 runtime candidate:

`ad07445b16a708b4efd67461f5cef12201ffa8b1`

GitHub Actions run `35739828516` on that exact runtime commit is fully green: **364 tests / 1934 assertions**, PHPStan clean, Pint **258 files**, Vite green, migrations/queue/scheduler and SQLite backup→restore smoke green, npm audit 0 vulnerabilities, Composer security audit clean.

Phase 5 proved that Content can belong to Personal, GroupSpace and Admission Contexts. Phase 6 now removes the **experience-level split** between the simple generic Context UI and the mature Group Content Studio.

## Objective

Expose one powerful Content system everywhere while making ordinary authoring dramatically easier.

A user should choose an understandable purpose such as:

- Note / Diary;
- Post;
- Article;
- Book / Booklet;
- Lesson;
- Workbook Page;
- Activity / Report;
- Evidence / Work Sample;
- Media Album;
- Questionnaire shell;

and immediately receive a sensible starting structure, appearance and interaction policy.

The user must not need to understand Content Definitions, schema versions, block registries or render-template internals merely to create useful Content.

## Non-negotiable architecture

### One Content system

There is exactly one Content identity/revision/block/media/publication system.

Personal Content, GroupSpace Content, Admission Content and future Context Content all use:

- `SpaceContent` identity during the compatibility era;
- immutable revisions;
- Content Definitions;
- Blocks;
- Assets/media;
- Presentation;
- Outline/relationships;
- annotations/reactions;
- publication evidence;
- Context authorization.

Do not create separate “simple Context Content”, “learning Content”, “evidence Content”, “album Content”, or “profile Content” tables.

The legacy `SpaceContent*` names may be renamed only at a later proven migration boundary. Phase 6 improves semantics and UX without a cosmetic destructive rename.

### Blueprint is a recipe, not a domain object replacement

A ContentBlueprint packages trusted defaults for creating ordinary Content.

A Blueprint version may define:

- structured Definition fields;
- initial block composition;
- safe presentation preset/tokens;
- semantic classification defaults;
- interaction defaults;
- capability hints for the authoring UI.

Creating from a Blueprint creates normal Content. Published Content remains independently revisioned and immutable.

### Progressive authoring

Authoring has three progressive levels:

1. **Quick** — choose a Blueprint, title, and only essential fields.
2. **Guided** — edit the Blueprint-relevant fields, media and suggested blocks.
3. **Advanced** — full Studio: fields, blocks, files/media, appearance, outline, revision history, publishing and evidence inspection.

Advanced power must remain available without forcing it into the initial creation form.

### Context independence

A Blueprint is reusable across Context kinds unless its declared compatibility says otherwise.

A Lesson can exist in:

- a classroom GroupSpace;
- a teacher's Personal Context;
- an Admission Context when legitimately used there;
- future Project/direct collaboration Contexts.

Context authorization controls access. Blueprint identity does not grant access.

## Evidence-addressable Content

Phase 6 establishes the durable seam that future verification/reputation systems can cite.

Evidence references must bind to immutable truth:

~~~text
Content UUID
+ exact published Revision UUID
+ optional exact target
  - Block UUID
  - Asset placement UUID
  - field key
  - relationship UUID
~~~

Rules:

- reputation/evidence never points only to “current Content” when historical proof matters;
- a later revision cannot silently change old evidence;
- block evidence binds the revision-specific block UUID, not only the cross-revision logical UUID;
- evidence access always rechecks Context authorization;
- an evidence reference is not itself a verification, endorsement, skill score, agreement or fulfillment fact.

## Skill proof and reputation boundary

Phase 4's skill proficiency percentage remains a **self-asserted profile dimension**.

Do not automatically convert artifact count into that percentage.

Later evidence/reputation architecture should separate at least:

- **self proficiency** — what the Actor claims;
- **evidence maturity** — how much relevant work is attached;
- **verification** — who/what evaluated evidence and under which rubric;
- **outcomes** — accepted work, evaluations, fulfillment;
- **reputation** — an explainable aggregate over verified evidence/outcomes.

The owner's proposed progressive evidence levels are a useful policy pattern, but thresholds must be configurable and quality-aware rather than hard-coded globally.

A candidate shape for later proof policies is geometric/Fibonacci-like growth, for example minimum independent qualifying evidence counts such as 1 → 3 → 8 → 21, with quality/recency/reviewer-diversity requirements. The exact formula is **not Phase 6 domain truth**.

Phase 6 only ensures Content can be cited precisely enough for that later system.

## Initial Blueprint catalog

The initial catalog should prove unrelated uses:

### General

- Note / Diary;
- Post;
- Article;
- Activity / Report;
- Evidence / Work Sample;
- Media Album.

### Learning / knowledge

- Book / Booklet;
- Lesson;
- Workbook Page;
- Questionnaire shell.

The Questionnaire Blueprint only authors the human-facing questionnaire artifact. Structured respondent answers remain Phase 7 Submission/Response/Evaluation.

## Blueprint lifecycle

ContentBlueprint has stable UUID identity.

Blueprint versions are immutable after activation.

A version records exact creation defaults and a canonical content hash.

Existing Content stays pinned to the Blueprint version it was created from.

Publishing a new Blueprint version never rewrites existing Content.

### Clone semantics

A user may explicitly clone a reusable Blueprint into a new Blueprint identity.

The clone records its source Blueprint version.

Future edits to the clone never mutate the source.

### Upgrade semantics

No silent upgrades.

A future explicit upgrade flow may create a new draft Content revision/Definition version after presenting the migration impact. Phase 6 may record that an upgrade is available, but must not silently mutate authored Content.

## Unified application experience

The generic Context Content library becomes the canonical entry point.

Expected flow:

~~~text
Context library
→ Create
→ choose/search Blueprint
→ minimal quick-create form
→ normal Content draft
→ unified Studio
   ├─ Content/fields
   ├─ media/files
   ├─ blocks/layout
   ├─ appearance
   ├─ outline
   ├─ revision/history
   └─ publish
→ unified Reader
   ├─ reactions
   ├─ annotations
   └─ evidence-addressable targets
~~~

Existing Group Content routes remain compatibility routes during migration but should converge onto the same application components/services.

## Phase milestones

### 6A — Blueprint kernel

Deliver:

- `ContentBlueprint`;
- immutable `ContentBlueprintVersion`;
- exact version provenance;
- built-in catalog;
- context compatibility metadata;
- Definition schema defaults;
- initial Blocks;
- presentation defaults;
- interaction defaults;
- clone provenance;
- catalog/search service;
- focused lifecycle/hash tests.

### 6B — Blueprint-instantiated Content

Deliver:

- atomic create-from-Blueprint Action;
- context-local hidden Definition materialization/reuse;
- exact Blueprint-version binding on Definition and Content;
- Blueprint initial blocks/presentation on revision 1 without synthetic extra revisions;
- no fake Group and no special Content subtype.

### 6C — Unified authoring surface

Deliver:

- Blueprint picker in Context library;
- Quick creation;
- Guided/Advanced progressive disclosure;
- generic Context Studio parity for media/files, blocks, appearance, outline, revisions and publication;
- existing Group authoring routed through/reusing the same implementation rather than duplicating business logic;
- mobile/RTL accessibility.

### 6D — Unified Reader and evidence locators

Deliver:

- generic Context Reader using the mature interaction engine;
- generic asset/media routes;
- immutable revision permalink;
- stable evidence locator for revision/field/block/asset/relationship targets;
- tests proving an old published evidence locator still resolves after later Content revisions.

### 6E — closure

Deliver:

- focused Phase 6 tests;
- existing Group Content regression;
- Personal Blueprint proof;
- learning/booklet proof;
- evidence/work-sample + media proof;
- full PHPUnit/PHPStan/Pint/Vite;
- migration and rollback proof;
- implementation report;
- owner browser/mobile/RTL acceptance.

## Required proof cases

### Personal evidence portfolio

An Actor creates “Evidence / Work Sample” in Personal Context, adds description + files/media + blocks, publishes it, and can cite an exact published block or asset as historical evidence.

### Classroom booklet

A teacher creates a Book/Booklet with Lesson/Page children in GroupSpace using the same Content kernel and advanced Studio.

### Admission evidence artifact

A candidate creates an evidence-oriented Content artifact inside Admission Context before Membership, while remaining denied ordinary Group access.

### Simple diary

A user creates a diary/note with only a title/body in a few actions without encountering schema administration.

## Exit gate

Phase 6 closes only when:

1. one Content kernel powers Personal, GroupSpace and Admission authoring;
2. common Content creation starts from understandable Blueprints;
3. advanced media/block/presentation/outline/revision capabilities are not Group-only;
4. existing Group Content behavior remains compatible;
5. Blueprint versions are immutable and provenance-bound;
6. existing Content is never silently upgraded;
7. published revisions/blocks/assets can serve as exact evidence targets;
8. normal users do not need raw Definition administration for common authoring;
9. automated gates are green;
10. browser/mobile/RTL behavior is accepted by the human owner.

Automated/runtime conditions **1–9 are satisfied** on `ad07445b16a708b4efd67461f5cef12201ffa8b1`. Condition 10 remains the final owner-local acceptance gate. Phase 7 must not begin before that gate passes.
