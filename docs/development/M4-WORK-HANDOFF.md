# M4 handoff — from the accepted Agreement to real work

Starts only after `feat/m3-deals-terms` is accepted and merged. Design authority: [M4-M5-AGREEMENT-OPERATIONS-MAPPING.md](M4-M5-AGREEMENT-OPERATIONS-MAPPING.md); path: [M4-M8-PLAN-AND-RELEASE.md](M4-M8-PLAN-AND-RELEASE.md). M5 (money) is **out of scope**: M4 creates no ledger entry, owed amount or payment state.

## Objective
Let a Deal's accepted Agreement duties become the existing **Commitment** (what is due), **Plan/PlanOccurrence** (when) and **Fulfillment** (what was delivered and confirmed), after the person who receives the work has explicitly confirmed. Recurring work keeps its cycles. A personal Planner "done" is never contractual fulfillment.

## The whole idea in one flow
1. **Propose the work** (Work tab, either party): a preview of the duties and cycles read from the accepted version (`quantity`, `fields`, `time`, `recurrence`, `completion`). The person ticks which duties become commitments; narrative clauses are only copied as a typed title, never parsed. Stored as a private draft on the Deal (same place as `agreement_draft`), no new table.
2. **The recipient confirms** (beneficiary of each duty, acting through `PartyAuthority`). Nothing exists as a Commitment before this click; declining or ignoring leaves no records.
3. **On confirm, one transaction** calls the existing `CreateCommitment` (+ `CreateCommitmentPlan` for recurring work). No new commitment code path.
4. **Perform and report**: the obligor opens a cycle and uses `SubmitFulfillment` (evidence from the Deal thread assets).
5. **Confirm**: `ReviewFulfillment` by the party named in `completion.confirmed_by` (beneficiary | both | none = closes on report). Dispute path reused as is.

## Reused, not rebuilt
`Commitment`, `CommitmentPlanBinding`, `CreateCommitment`, `CreateCommitmentPlan`, `BindExistingPlan`, Planner `CreatePlan`/`MaterializePlanOccurrences`, `SubmitFulfillment`, `ReviewFulfillment`, `OpenFulfillmentDispute`, `ResolveFulfillmentDispute`, the Deal Work tab (currently read-only), `DealLinks`.

## The only new data (additive, reversible)
- One nullable **unique** `derivation_key` column on `commitments`, written at creation only (the model is immutable): `contract_version_id : term path : cycle`. It makes re-confirming a no-op (guard rail 3). Cycles are Plan occurrences, so no per-cycle Commitment rows are needed; one Commitment covers the whole recurring duty and each cycle's Fulfillment points at its occurrence.
- Party columns on commitments/fulfillments are **not** part of this first slice: obligor/beneficiary stay Actors (the signer representing the principal), as `CreateCommitment` already requires. Moving to principals is a separate, later change (note C3b limit) and must not block M4.

## Hard rules (each needs a test)
1. Plan occurrence completion/transition never creates, edits or confirms a Fulfillment (`PlannerPrivacyBoundaryTest` style: Deals read only occurrence id, status and times; no reason, note, difficulty, habit or urge crosses over).
2. No Commitment exists without a recipient confirmation recorded for that exact accepted version.
3. Derivation is idempotent on `derivation_key`; double click or double confirm creates nothing new.
4. Recurring: one Plan, one occurrence per cycle; an amendment (new ContractVersion) changes only cycles that start after its effective time; finished cycles keep their original version reference.
5. Business/Group: acting for a principal is checked with `PartyAuthority` at action time; the signer is recorded.
6. `completion.confirmed_by = none` closes a cycle on report; it never implies payment. No money records, balances or "owed" text anywhere in M4.
7. Narrative clauses never produce work.

## Checkpoints (small, in order; each ends green)
- **M4-a Propose and confirm.** Work-tab preview, draft, recipient Confirm → Commitment (+Plan for recurring). Tests: single exchange, repair, recurring attendance, nonmonetary, Business manager, unconfirmed = nothing, double confirm, stranger refused.
- **M4-b Report.** Cycle list from occurrences; *Report done* = `SubmitFulfillment` with evidence. Test: planner-complete alone does nothing.
- **M4-c Confirm delivery.** `ReviewFulfillment` per `completion.confirmed_by`; dispute reused.
- **M4-d Amendments and repeat.** Cycle/version rule above. Gate: focused Deal/Commitment/Fulfillment/Planner suites, populated-MySQL rehearsal of the one migration (reuse `tools/m3-mysql-rehearsal.php` pattern), two-account browser walkthrough (fa/RTL + mobile), Pint, PHPStan.

## Do not
Add a second work/task/delivery system; touch Bookkeeping; parse clause text; auto-create anything on Agreement acceptance; widen currency; change Planner behavior outside a Deal.

## Open question for the owner (default in bold)
Who is "the recipient" for a duty both sides perform? **Each duty names its beneficiary in the preview and that party confirms it; a mutual duty needs both.**
