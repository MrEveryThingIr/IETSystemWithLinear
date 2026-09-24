# Phase 9 Closure — Published Content Library and Reference/Placement Semantics

## Result

Phase 9 runtime is remotely complete.

- Branch: `feat/ideal-v1-09-content-library-placement`
- Baseline: `7faa3ea152c7b3f1aee89be0d7bc38aceec94107`
- Feature checkpoint: `fdb7c1cbfbe1a81c284c67df79a71b0e5ee3074a`
- GitHub Actions: `36020336046`
- Local/browser acceptance: deferred

## Product change

A new published Content Library now exposes only viewer-authorized sealed publications and supports search, Blueprint/type and semantic Concept filtering.

Published Content may be presented in another Context through `ContentPlacement` without copying the Content or changing its home Context.

The normal presentation follows later published editions. Exact `ContentEvidenceReference` records continue resolving the historical sealed revision/target they originally identified.

## Architecture result

~~~text
Content home Context
        │
        ├── immutable publication history
        │
        ├── ContentPlacement ──→ target Context read presentation
        │
        └── ContentEvidenceReference ──→ exact sealed revision/target
~~~

Presentation and evidence now have deliberately different semantics.

## Security and provenance guarantees

- placement creator must have source/home read authority and target Content-management authority;
- target-only readers gain published read, not source Context or Studio authority;
- target-only readers cannot transitively reshare;
- removed placement revokes target-only read;
- re-presenting the same source/target reactivates the existing placement identity;
- later publication changes normal presentation but cannot rewrite existing exact evidence;
- Content identity/home Context never moves.

## Story continuity

The Phase 8 Alice/Bob/Carol/Diego story now extends into Content reuse:

- Alice can author and publish an Article/Album;
- an appropriately authorized presenter can present it in Maple Housing Office;
- Bob can consume it through target authorization without being granted source/editor authority;
- an exact revision/block can be cited as evidence;
- newer publication changes the live presentation but not the historical evidence reference.

## Validation

Run `36020336046` on `fdb7c1cbfbe1a81c284c67df79a71b0e5ee3074a`:

- PHPUnit: **432 passed / 2413 assertions**;
- changed-file Pint: **378 files passed**;
- PHPStan: no errors;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler smoke: passed;
- database queue smoke: passed, no failed jobs;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

## Defects found and corrected during continuation

The interruption left Phase 9 implemented but not remotely green or integrated. Continuation uncovered and fixed three concrete defects:

1. PHPStan rejected two unnecessary nullsafe accesses in Library Context labeling.
2. Placement authorization could reuse a stale in-memory `placements` relation after a placement changed during the same request/test lifecycle. Authorization now queries current placement state instead of trusting cached relation state.
3. Livewire/Blade compilation produced malformed PHP around the Library's repeated card loop when conditional component controls were mixed with loop instrumentation. Repeated cards now use stable `wire:key` identities and plain keyed controls where appropriate; the full rendered-flow regression passes.

No test was weakened to hide these defects.

## Known limitations

- presentation requests/approval between differently authorized parties are not yet modeled;
- advanced author/origin/language/date Library filters remain optional future refinement;
- placement does not delegate interaction/editing authority;
- richer contextual ⋮ menus and comprehensive responsive/RTL/accessibility polish remain deferred;
- local/browser acceptance remains cumulative and unclaimed until performed.

## Next

**Phase 10 — Relationship + Relationship Context.**

The next phase must model meaningful direct Actor relationships without inventing fake Groups, while preserving the Context/content/authorization boundaries already proven.
