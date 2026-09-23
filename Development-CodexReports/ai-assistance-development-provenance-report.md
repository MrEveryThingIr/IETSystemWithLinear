# AI Assistance and Development Provenance — Implementation Report

## Status

**Technically complete / remote CI green. Owner-local browser acceptance pending.**

- Branch: `feat/context-ai-assistance-provenance`
- Baseline: `2c7a5c35a31fe86d761a1cafd189560bec220784`
- Green runtime candidate: `f75332d5a5a25ee895c0b043a4693fe1b8803de0`
- GitHub Actions: `35882242039`
- Scope type: explicitly authorized cross-cutting slice on the Phase 7 + Documentation-as-Content baseline
- Phase 8: **not opened**

## Objective

Add a safe first AI-agent seam to IET so an authorized user can describe a Content change in natural language, review a structured proposal, and apply only trusted changes through the existing Content authorization and revision Actions.

Also preserve a curated, immutable development-origin record linking important chats/design sessions to roadmap phase, system version, branch, Git baseline/result commits, and canonical repository paths—without making raw chat history the source of truth.

## Architecture implemented

### AI Content Assistant

Flow:

~~~text
authorized Content Studio
→ exact current editable revision snapshot
→ user prompt
→ configured AI provider
→ strict structured proposal
→ human review
→ reauthorize + stale-base check
→ existing Content Actions
→ normal new draft revision
~~~

Implemented boundaries:

- provider never receives direct database authority;
- planning and application both require Content update permission;
- application rejects a proposal when the editable revision changed after planning;
- structured field changes reuse `ReviseSpaceContent`;
- block changes reuse `UpdateSpaceContentBlocks`;
- presentation changes reuse `UpdateSpaceContentPresentation`;
- normal downstream Content validation remains authoritative;
- AI never publishes Content automatically;
- arbitrary PHP, JavaScript, HTML, SQL, shell/executable behavior is outside the proposal registry;
- generated image/audio/video needs are represented as explicit media requests only;
- provider failure changes no Content;
- the provider credential stays server-side;
- the UI explicitly discloses that creating a proposal sends the user's prompt and current Content snapshot to the configured AI provider.

Initial provider configuration:

~~~text
AI_PROVIDER=openai
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_AUTHORING_MODEL=gpt-5.6-luna
OPENAI_TIMEOUT=60
~~~

The provider request uses the Responses API, schema-constrained structured output, and `store=false`.

### AI assistance provenance

`AiAssistanceRun` preserves:

- Context;
- Content;
- exact base revision;
- requesting Actor;
- provider/model/response ID;
- prompt;
- request hash;
- structured proposal;
- planned timestamp;
- applied revision/timestamp.

Planning provenance is immutable after creation. Application state may only advance through the trusted Action.

### Development Origins

`DevelopmentOrigin` is immutable historical provenance for:

- ChatGPT discussion;
- design session;
- external discussion;
- manual note.

It may record:

- optional source URL;
- reviewed title/summary;
- roadmap phase;
- system version;
- branch;
- full baseline/result commit SHAs;
- affected repository paths;
- occurred-at time;
- optional superseded-origin relation.

Capture requires platform `ViewPlatformAudit`; Group roles do not grant access.

Raw private chat history is not automatically imported.

## Documentation and product integration

Updated:

- `README.md`
- `AGENTS.md`
- `docs/PROJECT_COMPASS.md`
- `docs/TARGET_ARCHITECTURE.md`
- `docs/PRODUCTION_ROADMAP.md`
- `docs/DEVELOPMENT_CIRCUIT.md`
- `docs/CURRENT_STATE.md`
- `docs/AI_ASSISTANCE_AND_DEVELOPMENT_PROVENANCE.md`
- official System Manual with chapter **14. AI-Assisted Authoring and Development Origins**
- contextual Help mapping for the AI assistant and Development Origins
- English, Persian, Arabic, and Simplified Chinese UI catalogs.

The README/Development Circuit stale active-phase guidance was corrected to the current Phase 7 line.

## Database changes

Added append-only migrations:

- `2026_09_23_190000_create_ai_assistance_runs_table.php`
- `2026_09_23_191000_create_development_origins_table.php`

Both use restrictive historical foreign-key behavior where provenance must be preserved.

CI proved rollback/reapply of the latest migration set and normal forward migration from a clean database.

## Tests added / expanded

Added:

- `AiContentAssistanceTest`
  - authorized plan/apply;
  - structured provider request;
  - normal draft revision result;
  - stale proposal rejection;
  - fail-closed unconfigured provider.
- `DevelopmentOriginTest`
  - platform-auditor capture;
  - immutability;
  - normal-user denial;
  - listing UI.
- factory coverage for both new persisted models.
- System Manual materialization updated to 14 chapters.
- Content locale parity now includes `ai.php` and `development.php`.

A full-suite failure found a real factory dependency-order defect in `AiAssistanceRunFactory`; it was corrected before closure.

## Final automated validation

GitHub Actions run `35882242039` on `f75332d5a5a25ee895c0b043a4693fe1b8803de0`:

- PHPUnit: **406 passed / 2219 assertions**
- PHPStan: **no errors**
- changed-file Pint: **353 files passed**
- frontend/Vite build: passed
- npm audit: **0 vulnerabilities**
- latest migration rollback/reapply: passed
- scheduler smoke: passed
- database queue smoke: passed; no failed jobs
- SQLite backup → restore smoke: passed
- Composer security audit: **no vulnerability advisories**

## Git commits

1. `0cb9cbf49d79bda4183bd0c27ddd3dda4f0a3613` — feat(ai): add safe contextual Content assistance
2. `53335aea131e34b159734656d544643063c09176` — test(ai): cover assistance provenance factory
3. `e4541c61f213d8b80e56713ab63a0f6d1adae82b` — feat(provenance): capture development chat origins
4. `8d1051f5999b1c9e195972595aaa088f14860f40` — style(ai): normalize new assistance files
5. `2f2137ecbe3442ade0505890dfad8166d53d784f` — style(ai): avoid conflicting not-operator formatting
6. `6e14136928116463caa71b201f5ae7544c792315` — docs(ai): bind assistance and provenance to project authority
7. `3cda8c1fcd9237ac4a8592c97b8154f49283d555` — style(ai): align planner phpdoc
8. `bc5e8a76faeef49727768031ffa65c6735198956` — style(provenance): separate pagination trait
9. `f75332d5a5a25ee895c0b043a4693fe1b8803de0` — fix(ai): resolve assistance factory content before context

## Owner-local acceptance gate

Do **not** use `migrate:fresh` on the existing local database.

Synchronize:

~~~bash
git fetch origin
git switch feat/context-ai-assistance-provenance
git pull --ff-only origin feat/context-ai-assistance-provenance
git status --short
git rev-parse --short HEAD
~~~

Expected code candidate before closure-only docs: `f75332d5` or later fast-forward descendant.

Apply and validate:

~~~bash
php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status

php artisan test --compact \
  tests/Feature/AiContentAssistanceTest.php \
  tests/Feature/DevelopmentOriginTest.php \
  tests/Feature/SystemManualContentTest.php \
  tests/Feature/ContentLocaleCatalogTest.php

php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm run build
~~~

Synchronize the repository-owned manual source only when ready to publish its new official edition:

~~~bash
php artisan system-manual:sync <owner-email>
~~~

For a real AI browser test, put the provider key only in local `.env`:

~~~text
AI_PROVIDER=openai
OPENAI_API_KEY=<your-server-side-key>
OPENAI_AUTHORING_MODEL=gpt-5.6-luna
~~~

Then:

~~~bash
php artisan optimize:clear
~~~

### Browser acceptance

1. Open an editable Content item → Studio.
2. Confirm **AI Content Assistant** appears only for an authorized editor.
3. Open it and confirm the provider-data disclosure is visible.
4. Ask for a small text/block/presentation change.
5. Review the proposal before applying it.
6. Apply it; return to Studio and confirm:
   - a new normal draft revision exists;
   - requested structured changes are present;
   - published revision did not change automatically.
7. Create another proposal, make a human Studio edit first, then try the old proposal; confirm it refuses as stale and preserves the human edit.
8. Ask for an image/voice/video addition; confirm it is shown as a media request and no fake Asset is created.
9. As Superadmin/platform auditor, open **Development Origins**, capture a test origin, and confirm it remains listed.
10. As a normal active verified user without platform audit authority, confirm Development Origins is inaccessible/absent.
11. Use Help from the new surfaces and confirm it opens System Manual chapter 14.
12. Check the AI assistant and Development Origins on mobile width and an RTL locale (Persian or Arabic).
13. Reconfirm the existing Phase 7 submit → reviewer submitted-count → Evaluation browser journey.

## Explicitly deferred

Not a defect / not part of this slice:

- generated image/audio/video provider adapters;
- arbitrary executable widgets/code generation;
- automatic form submission or approval;
- Admission v2 Conversation;
- realtime transport;
- generic Workflow;
- Planner;
- Contract/Commitment/Fulfillment;
- Matching;
- Accounting.

Future AI form assistance must preserve the distinction between **populate/propose draft values** and **execute authoritative actions**.

## Closure decision

The repository implementation is technically ready for owner-local/browser acceptance. No known automated blocker remains. Phase 8 must not begin until the owner accepts the combined Phase 7/manual/AI-provenance browser gate or explicitly changes the roadmap gate.
