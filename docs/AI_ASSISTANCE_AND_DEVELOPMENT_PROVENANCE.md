# AI Assistance and Development Provenance

## Status

Cross-cutting implementation candidate on `feat/context-ai-assistance-provenance`, based on the Phase 7 + Documentation-as-Content line. This contract does not open Phase 8.

## Why this capability exists

IET already has safe structured Content, immutable revisions, Context authorization, trusted Actions, private Assets, and exact evidence. Users should be able to describe their intent in ordinary language and let an AI assistant translate that intent into those existing structures without requiring the model to own database semantics or authorization.

The project also has a repeated development pattern in which an important chat or design session becomes the origin of a roadmap change. That origin should be traceable without making chat history the architectural source of truth.

## Authority boundary

AI output is untrusted proposal data.

~~~text
human intent
→ AI proposal
→ local validation
→ user review / explicit confirmation
→ current policy
→ existing domain Action
→ durable state/evidence
~~~

The model never bypasses Context, Group, platform, Content, Submission, Agreement, or future Contract/Accounting authorization.

Natural-language phrases such as “submit”, “approve”, “I accept”, “publish this”, or “mark paid” do not perform those transitions by themselves.

## First implemented slice — Content Studio

An authorized Content editor may:

1. open AI Content Assistant from Content Studio;
2. describe the desired change;
3. receive a structured proposal tied to the exact editable revision;
4. inspect proposed title, field, block, presentation, and media-generation requests;
5. explicitly apply the proposal;
6. receive a normal new draft revision produced through existing Content Actions;
7. inspect/edit/publish through the ordinary Studio flow.

Planning and application both require Content update authority. Application rejects a proposal if its base revision is no longer the current editable revision.

The assistant may currently change only trusted structures already supported by IET:

- existing structured Content fields;
- paragraph, heading, quote, list, callout, divider, and field blocks;
- existing logical block targets;
- built-in safe presentation templates/tokens.

It does not execute arbitrary HTML/JavaScript/PHP/SQL/shell content and does not auto-publish.

## Provider boundary

The initial provider adapter uses the OpenAI Responses API from the Laravel server.

The API key remains server-side. The request asks for schema-constrained structured output and sets provider response storage off for this request. Provider/model configuration is environment-driven rather than hard-coded into domain state.

A provider failure produces no Content mutation.

## Media generation boundary

A user may already ask for “add an image here” or “add voice explaining this section.” The initial assistant preserves that intent as a media request but does not create a fake Asset.

The production target is:

~~~text
media request
→ authorized image/audio/video provider
→ private Asset
→ immutable source/provider/file provenance
→ rights status
→ scan/process/readiness
→ user review
→ normal Content placement
→ ordinary publication evidence
~~~

Until that adapter exists, media-generation requests remain explicit unapplied work.

## Future section/form assistance

The same mechanism may later assist any section that exposes a trusted manifest of:

- visible/current values;
- allowed fields;
- allowed actions;
- validation schema;
- relevant Context/object/revision;
- permissions available to the current authenticated User/Actor.

AI may fill or propose draft form values. Authoritative actions remain separate. For example:

~~~text
AI fills employment application draft
≠ Submission submitted

AI summarizes Admission evidence
≠ Admission approved

AI proposes Contract terms
≠ parties accepted Contract

AI drafts payment details
≠ accounting/settlement recorded
~~~

This separation is mandatory.

## Development Origin

`DevelopmentOrigin` is immutable historical provenance for a meaningful design/development source.

It may record:

- source type: ChatGPT, design session, external discussion, or manual note;
- optional source URL;
- reviewed title and summary;
- roadmap phase;
- system version;
- branch;
- exact 40-character baseline/result Git commit SHAs;
- related canonical repository paths;
- occurred-at time;
- optional superseded-origin relation.

The capture UI is platform-audit-only. Group roles do not grant this access.

## Chat privacy and source-of-truth rule

Do not automatically ingest raw private chat history.

Prefer a deliberate reviewed summary and a source link when the conversation is intentionally shareable/retained. If a full transcript ever becomes a product requirement, it needs its own privacy/retention/export/access design rather than being silently copied into provenance.

Authority remains:

~~~text
human owner
→ accepted ADRs / canonical repository docs
→ active phase contract / source / tests / Git evidence
→ implementation reports
→ Development Origin provenance
→ issue-tracker/live planning metadata
→ raw chat history
~~~

A Development Origin explains why a change began; it does not make the originating chat binding after canonical decisions evolve.

## Relationship to docs and system versions

A recommended development history chain is:

~~~text
Development Origin
→ phase_key
→ branch + baseline_commit_sha
→ affected repository_paths
→ implementation commits
→ result_commit_sha
→ phase report / CURRENT_STATE
→ release/system version when released
→ user-facing System Manual edition
~~~

The system can later add explicit release/content-evidence links when a real release/version domain warrants them. This slice deliberately avoids a universal polymorphic relationship table.

## Security and reliability requirements

- least privilege and reauthorization at apply time;
- server-side provider credentials;
- bounded prompts and schema-constrained output;
- no arbitrary executable output;
- stale-write detection;
- immutable assistance/provenance history where historical truth matters;
- no silent publication;
- provider failure must fail closed;
- normal Content validation remains authoritative;
- no cross-Context leakage in provider snapshots;
- future generated Assets must pass existing media security and rights rules.

## Acceptance gate

Before this slice is accepted:

1. both migrations apply to the existing local database without destructive reset;
2. focused AI/provenance/manual/localization tests pass;
3. full PHPUnit, PHPStan, changed-file Pint, Vite, migration/rollback, backup/restore and security CI gates are green;
4. an authorized editor can plan/review/apply an AI Content proposal in browser;
5. applying creates a normal draft and never publishes automatically;
6. a stale proposal fails without overwriting a newer human edit;
7. a media-generation request is shown as pending/unapplied rather than a fake Asset;
8. a platform auditor can capture/read Development Origins while a normal user cannot;
9. contextual Help from both new surfaces resolves to the System Manual AI/provenance chapter;
10. mobile and RTL presentation is checked;
11. the owner accepts the browser behavior.

Only after this gate may the slice rejoin the accepted Phase 7 line. Phase 8 remains separately gated.
