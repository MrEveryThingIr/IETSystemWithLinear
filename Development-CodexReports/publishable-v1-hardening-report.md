# Publishable v1 Hardening Report

## Objective

Convert the cumulative Ideal-v1 line into a bounded publishable release candidate for real-user learning, while deferring AI Copilot and speculative post-v1 abstraction.

## Inherited defects closed in this checkpoint

- reserved-email Access Invitations are server-enforced single-use; unreserved links retain bounded multi-use;
- Intent Directory visibility is applied in SQL before ordering/pagination instead of filtering a hard 200-row window in PHP;
- Directory uses real pagination and resets it when filters change;
- useful Directory filters persist in the URL;
- create → Directory `highlight` is consumed with focusable visual emphasis;
- Profile Intent management now exposes the subject, arrangement, cash range, currency/basis, exchange preference and negotiation notes supported by the guided Intent journey;
- regression tests cover the >200 hidden-record authorization case, highlight handoff, invitation semantics and complete Profile value-model editing.

## Release boundary

AI Copilot is explicitly deferred. Generic Workflow extraction, Reputation and Recommendations are also not publication blockers for v1. Existing deterministic Actions and durable domain evidence remain authoritative.

## Validation

The final exact SHA and CI run are recorded here only after the complete branch gate is green.

## Human acceptance

Browser acceptance is intentionally not fabricated remotely. The final candidate is handed to the owner with `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`; browser findings become regression-tested correction commits before tagging the stable release.
