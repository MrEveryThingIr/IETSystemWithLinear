# Phase 8 Closure — Progressive Intent Journey v2

## Result

Phase 8 is remotely complete.

- Branch: `feat/ideal-v1-08-intent-journey`
- Baseline: `0134a94aa0d82953614877e80b4b3f50f64a4790`
- Feature checkpoint: `eb82f8af1bac3c75e7bd5550db6d2a4dc6badfce`
- GitHub Actions: `35926251822`
- Local/browser acceptance: deferred

## Product change

The first Intent screen now asks **What do you want to do?** and offers real-world paths:

- Buy/acquire
- Sell/transfer
- Rent/use
- Rent out
- Need service
- Offer service
- Hire
- Find work
- Seek capital
- Offer capital
- Seek collaboration
- Offer collaboration
- Other/manual

These paths map into existing authoritative `ActorProfileIntent` fields. The preset is not persisted.

## Architecture result

No migration and no generic workflow/wizard engine were introduced.

~~~text
friendly journey
→ kind (Need/Offer)
→ subject kind
→ arrangement kind
→ existing CreateActorProfileIntent Action
~~~

Buy/sell/rent permit useful subject refinement between Property, Good and Other. Service/capital/collaboration paths hide irrelevant choices. The Other route keeps generic manual composition available.

## Negative guarantees

- Hire/Find work does not create Employment.
- Capital does not create equity/debt/loan.
- Collaboration does not create partnership.
- Buy/sell/rent does not create a Contract or transfer title.
- No Match, Submission or Evaluation is generated.
- Privacy/visibility remain governed by the existing Intent/Profile rules.

## Story continuity

- Alice can Sell → Property → Riverside Lot.
- Alice can Need service → Residential construction.
- Alice can Seek capital.
- Bob can Offer service / Find work.
- Carol can Offer capital.
- Alice/Bob can model a simple desk sale without activating project/employment layers.

The System Manual and cumulative local acceptance worksheet use these exact paths.

## Validation

Run `35926251822` on `eb82f8af1bac3c75e7bd5550db6d2a4dc6badfce`:

- PHPUnit: **428 passed / 2378 assertions**;
- changed-file Pint: **368 files passed**;
- PHPStan: no errors;
- Vite production build: passed;
- migration rollback/reapply: passed;
- scheduler smoke: passed;
- database queue smoke: passed, no failed jobs;
- SQLite backup/restore: passed;
- npm audit: 0 vulnerabilities;
- Composer audit: no advisories.

## Defects found during implementation

CI found and corrected:

1. conflicting Pint negation formatting;
2. import ordering;
3. one older Intent regression test bypassed the new required journey-entry step.

These were corrected before integration.

## Next

Phase 9 — Published Content Library and reference/placement semantics.

The next phase must keep Content identity at its immutable home Context and add any cross-context presentation semantics as references/placements rather than reassigning or duplicating Content.
