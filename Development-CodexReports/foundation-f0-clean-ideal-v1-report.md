# Foundation F0 — Clean Pre-AI Ideal-v1 Integration Report

## Status

Remote runtime gate passed.

- Integration branch: `integration/ideal-v1`
- Root baseline: `2c7a5c35a31fe86d761a1cafd189560bec220784`
- Reconstructed foundation runtime: `f5b55fb3f1d426e995549efc90856cfd9ac60b34`
- GitHub Actions: `35924838454`
- Local/browser acceptance: intentionally deferred to `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`

## Objective

Create one clean cumulative development line that preserves the useful office/invitation/intent work while removing the currently unused AI-assistance and Development-Origin runtime.

## Result

The clean foundation includes:

- standalone Access Invitation for new-account registration;
- invitation preview before registration;
- email verification → Get Started continuation;
- Group Invitation presented for existing verified accounts;
- guided Need/Offer capture using existing `ActorProfileIntent`;
- subject/arrangement/value-exchange discovery facets;
- permission-aware Needs/Offers/Services directory;
- `office_alpha` focused release profile;
- Phase 0–7 kernels and Documentation-as-Content/manual foundation;
- canonical continuous Ideal-v1 roadmap and interruption handoff;
- canonical Diego/Alice/Bob/Carol example world;
- System Manual chapter 14 with exact current end-to-end UI/browser story.

The active line does **not** contain:

- `AiAssistanceRun`;
- AI provider/planner/actions/routes/UI/config;
- `DevelopmentOrigin` runtime tables/UI;
- AI-specific migrations/catalogs/tests.

Git history remains sufficient development provenance for now.

## Architecture decisions frozen by F0

1. Content is one independent versioned system.
2. Every Content has a home/origin Context for authoring/lifecycle/authorization.
3. Authorized presentation may reuse published Content across Contexts.
4. Historical evidence pins an exact published revision and optional exact target.
5. GroupSpace is one Context kind, not a universal container.
6. User journeys progressively compose capabilities; they do not create combinatorial universal entity types.
7. Need/Offer is current intent only.
8. Conversation/Content wording is never hidden authority.
9. AI is deferred until the dedicated late roadmap phase.

## Remote validation

Run `35924838454` on exact runtime checkpoint `f5b55fb3f1d426e995549efc90856cfd9ac60b34`:

- PHPUnit: **411 passed / 2257 assertions**;
- changed-file Pint: **366 files passed**;
- PHPStan: no errors;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler smoke: passed;
- database queue smoke: passed, no failed jobs;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer security audit: no advisories.

An earlier clean-reconstruction CI run exposed one stale test that referenced the intentionally removed Development Origins route. The test was corrected; no runtime route was restored merely to satisfy stale expectations.

## Deferred local acceptance

See **Checkpoint F0** in `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`.

No `migrate:fresh`.

The browser story begins with Diego issuing Alice a standalone Access Invitation and continues through Alice/Bob/Carol intents, visibility, Group invitation, Content, and structured interactions.

## Next milestone

Phase 8 — Progressive Intent Journey v2.

Implementation constraints:

- branch from latest green `integration/ideal-v1`;
- no new generic wizard engine yet;
- no new domain migration unless existing intent fields prove insufficient;
- human presets are orchestration only;
- prove simple sale, rent, service, paid-work/hire intent, capital and collaboration paths;
- update versioned System Manual and acceptance worksheet with exact UI behavior.
