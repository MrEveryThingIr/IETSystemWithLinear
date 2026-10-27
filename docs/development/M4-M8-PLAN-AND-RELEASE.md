# Shortest safe path to M4 and M5, and the release scope

Based on the code in `feat/m3-deals-terms` (real inventory, not assumptions). Design of the mapping: [M4-M5-AGREEMENT-OPERATIONS-MAPPING.md](M4-M5-AGREEMENT-OPERATIONS-MAPPING.md).

## What already exists (so M4/M5 are integration, not new domains)
Commitments, Planner plans/occurrences/evidence, Fulfillment submit/review/dispute, Bookkeeping ledgers with shared entries, counterpart decision and disputes, `ProposeWorkBookkeepingEntry`, and the historical `ContractServiceTerm` workflow. What is **missing** is the connection from the *structured Agreement* to them, one Deal-workspace home for it, and scenario tests across the whole chain. M3 intentionally removed the manual "new commitment" control, so a release of M1-M3 alone would lose the way to start work from a Deal. M4 minimum is therefore required before any release.

## M4 status
Engineering-complete on `feat/m4-agreement-work` (see [M4-WORK-HANDOFF.md](M4-WORK-HANDOFF.md)): set up, receiver confirmation, cycles, reports, decisions, corrections, disputes, amendment protection, all in the Work tab. Open before acceptance: browser walkthrough (RTL/mobile/dark), evidence picker, prefilling the schedule from the Agreement's typed terms, a notification when a proposal needs confirmation, principal-keyed (not Actor-keyed) commitments. M5 has not started.

## M4 (Work) in four small checkpoints (as designed; delivered together as one slice)
1. **M4-a Set up the work.** Work tab action "Plan the work": preview derived commitments/cycles from the accepted version (`quantity`, `time`, `recurrence`, chosen duties), then create Commitment + Plan through the existing actions; idempotent per `(version, term, cycle)`. Tests: single exchange, repair, recurring attendance, non-monetary, Business manager.
2. **M4-b Perform and report.** Cycle list in the Work tab from Plan occurrences; "Report done" = `SubmitFulfillment` with evidence (reuse Thread assets).
3. **M4-c Confirm.** Receiver (or both, per `completion.confirmed_by`) uses `ReviewFulfillment`; dispute path reused; guard test: Planner completion alone confirms nothing.
4. **M4-d Amendments and repeat.** Cycles after an amendment follow the new version; finished cycles keep theirs. Gate: full suite, populated-MySQL rehearsal, two-account browser walkthrough.

## M5 (Money) in three small checkpoints
1. **M5-a Owed.** From confirmed cycles and `payment.mode`, propose Bookkeeping entries (Toman) via the existing actions; Money tab shows owed/paid per cycle from Bookkeeping only (no stored balance).
2. **M5-b Paid and settled.** "Report payment" = shared entry awaiting the counterpart's decision; settled when decided; disputes reuse `RaiseDispute`. Allocation of one payment across cycles: audit what Bookkeeping already supports first; build only the gap.
3. **M5-c Complete or repeat.** Agreement completion/renewal state in the Agreement tab; recurring Agreements keep one accepted version over many cycles.

## M6-M8 (after the first release)
M6 Business Catalog and public intake: route the existing public intake into the same Deal (`originating_intent` from a catalog listing version). M7 Proven Records: read-only projection of confirmed Fulfillments with explicit per-record consent. M8 Referrals/incentives: only with funded Bookkeeping accounts, explicit authorization and a compliance review; defer anything that moves money on a recommendation alone.

## Release scope recommendation
* **Do not release M1-M3 alone.** It removes commitment creation from the Deal and offers no replacement.
* **First usable release (R1) = M1-M5**, built as the thin integration above (about 7 checkpoints), because the underlying authorities are already released and tested; the risk is integration, which the scenario suite covers. If M5-b allocation turns out to be a real gap, ship **M1-M4 with the Money tab linking to existing Bookkeeping** and add M5 as R1.1.
* **Complete roadmap release (R2) = M1-M8**, after M6-M8 acceptance. Public-facing M6 intake and M8 rewards stay disabled until accepted.

## Deployment on DirectAdmin (no SSH, no Composer)
Follow `docs/PRODUCTION_DEPLOYMENT.md`: the host only receives the ZIP from the *Build production package* GitHub Action (vendor and built assets included). Migrations are additive and reversible: M2 (2), M3 `2026_11_13` to `2026_11_16` (Terms, acceptance link, deal-party principals with backfill, contract version Terms). Per release:
1. Rehearse every new migration on a **populated disposable MySQL** (done for M3's party migration; repeat for `2026_11_16`) and run the full suite with `OPENSSL_CONF` set.
2. Before: database export from the hosting panel; `iet:inventory` (read-only) as in the release notes.
3. Upload the ZIP to a new release directory, copy `.env`, link `storage`, switch the symlink, hosting support runs the documented `migrate`/`optimize` commands.
4. Verify: login, Market, start a conversation, draft + accept an Agreement, print it, notification bell, `/contracts/{uuid}` redirects; no queue worker exists, so notifications rely on the self-heal path and the scheduled `notifications:dispatch-outbox`.
5. Rollback: switch the symlink back and, only if migrations ran, restore the pre-release export (the M3 principal migration's `down()` loses Business/Group party identity and fails if two principals share a representative, so restore the export instead of rolling back).
No production change is made without explicit approval.

## Working method
Claude integrates; Codex may take bounded backend items on separate branches (for example the idempotent derivation action and its tests, or the Bookkeeping view query), never UI, policies or migrations. Focused tests per checkpoint, full regression at each milestone gate, owner approval before any merge or deploy.
