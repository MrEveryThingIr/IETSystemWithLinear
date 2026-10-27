# M4 handoff — Reciprocal work, evidence and confirmation

Branch `feat/m4-agreement-work`, cut from `integration/v2` at the M3 merge (`b140a380`). Design authority: [M4-M5-AGREEMENT-OPERATIONS-MAPPING.md](M4-M5-AGREEMENT-OPERATIONS-MAPPING.md). Path and release scope: [M4-M8-PLAN-AND-RELEASE.md](M4-M8-PLAN-AND-RELEASE.md).
Rules kept: additive reversible migration; no new work/task/delivery system; money untouched (M5); no merge or deploy without the owner.

## What a person does (Deal › Work)

1. **Set up a duty.** Either party writes what needs doing, who does it, who receives it, the amount each time, how many times, and (for repeated work) the schedule. This is only a proposal, kept privately on the Deal; it creates no obligation.
2. **The receiver confirms.** The party that *receives* the duty presses Confirm (or declines; either party can withdraw). Only now are the existing records created, in one transaction: a `Commitment` and, when a schedule was given, its Planner `Plan` with one occurrence per cycle.
3. **The doer reports.** *Report done* on a cycle (or on the whole duty when it is one-time) submits a `Fulfillment` with an amount (less than the cycle's amount = partly done), a note and the occurrence's own evidence.
4. **The receiver decides.** Confirm it was done, ask a question, or not accept. After a question or a refusal the doer sends a correction for the same cycle. After a decision either side may open a dispute; the other side resolves it.

Reciprocal agreements are simply two duties pointing in opposite directions. Nobody is a permanent "worker" or "employer": every duty names its own doer and receiver.

## The model (and what each role means)

| Role | Source | Authority |
|---|---|---|
| Responsible party (doer) | `Commitment.obligor` | reports (`SubmitFulfillment`) |
| Beneficiary (receiver) | `Commitment.beneficiary` | confirms the duty; decides reports (`ReviewFulfillment`) |
| Authorized verifier | the accepted Agreement's `completion.confirmed_by`, read through `AgreementTerms::of(commitment.contractVersion)` | `beneficiary` or `both` = the report, then the receiver's decision; `none` = the report stands, no decision is invented (the cycle shows "Reported", the receiver may still ask or dispute) |

Narrative clauses are never confirmable. The checkpoints are only the duties people choose to set up, the cycles of a recurring duty, and the reported amounts. Defaults: receiver is verifier; one cycle per duty; no schedule unless asked.

## Reused, not rebuilt
`CreateCommitment`, `CreateCommitmentPlan` (both gained two optional parameters, `derivationKey` and `asDealParty`), `SubmitFulfillment`, `ReviewFulfillment`, `OpenFulfillmentDispute`, `ResolveFulfillmentDispute`, `TransitionPlanOccurrence`, `CommitmentProgress`, `PartyAuthority` through `DealParties`, `AgreementTerms`, `DealLinks`.

## New code (small)
- `App\Actions\Deals\ManageDealWork`: `propose`, `withdraw`, `confirm`, `pending`, `mayWithdraw`. Pending proposals live in `relationships.metadata.work_setup` (no table). Confirm authorizes the *receiving party* by current `PartyAuthority`, inside a locked transaction.
- `App\Livewire\Deals\WorkPanel` + `deals/work-panel.blade.php`: the Work tab. Strings under `deals.work.*` in en/fa/ar/zh_CN.
- `CommitmentPolicy::createAsParty` / `manageAsParty`: a person representing a party of the Agreement in force may create the Deal's Commitment/Plan; `ManageDealWork` is what decides *who confirms what*. The original creator-only abilities are unchanged for Deal-less contracts.
- Migration `2026_11_17_000000`: nullable unique `commitments.derivation_key` (`<agreement version id>:<proposal id>`), written at creation only. Re-confirming creates nothing.
- `/commitments/{id}` and `/contracts/{id}/commitments/create` open the Deal's Work tab when the contract belongs to a Deal; Deal-less (historical) contracts keep the old pages.

## Guard rails and where they are tested (`tests/Feature/DealWorkTest.php`)
| Rule | Test |
|---|---|
| A proposal is not an obligation; only the receiving party's confirmation creates records; the doer, the proposer and strangers cannot confirm | `test_a_duty_becomes_work_only_after_the_receiving_party_confirms`, `test_reciprocal_work_runs_end_to_end…` |
| Confirming twice creates nothing | same (second confirm 404, one Commitment; unique key proven on MySQL) |
| Planner completion alone creates no Fulfillment; only the doer can report; only the receiver can decide | `test_reciprocal_work_runs_end_to_end…` |
| Cycles, partial completion, question → correction → refusal → dispute → resolution | same |
| Reciprocal duties (A→B and B→A) | same |
| An amendment never rewrites existing work; a proposal made under an earlier version cannot be confirmed under a later one; new work binds to the new version; old work still runs | `test_an_amendment_never_rewrites_existing_work…` |
| Money kind refused; same person both sides refused; repeated work needs a start; strangers cannot propose; no Agreement in force = no work | `test_work_rules_are_enforced_before_anything_is_created` |
| `completion: none` closes on report without inventing a decision | `test_when_the_agreement_needs_no_confirmation…` |
| No ledger rows are created anywhere in M4 | asserted in the end-to-end and `none` tests |
| Old Commitment URL opens the Work tab | end-to-end test |

## Known limits (carry forward)
- Obligor/beneficiary are still **Actors** (the party's representative), as `CreateCommitment` requires. A Business manager who is not the stored representative can confirm for the Business but cannot report/decide as the representative. Moving commitments to principals is a separate change.
- Evidence beyond the Planner occurrence's own assets/evidence is not attached from the Work tab yet (the existing Fulfillment actions already accept assets and evidence references; the picker is the next UI step).
- Recurrence in the Work form supports day, week and month; `year` is not offered. Open-ended recurrence is not supported: a duty names its number of cycles.
- Work is created only for the Agreement **in force**; a duty proposed before an amendment must be proposed again (the Work tab says so).
- Hours/time rules of the Agreement (`time`, `recurrence`) are shown above the panel and prefill nothing yet; the proposer types the schedule. Prefilling from the typed terms is a small follow-up.
- Notification of "your confirmation is needed" relies on the Deal page/bell not yet knowing about work proposals (no event is emitted). Out of scope for this slice.

## Evidence
- **Focused:** `DealWorkTest` 5 tests / 98 assertions passed.
- **Regression (Deal, Commitment/Fulfillment, Contract, Proposal pipeline, Work/Planner/Bookkeeping, localization parity, Surfaces):** 128 tests, 127 passed on the first run; the one failure was `DealWorkspaceTest` asserting the retired "Nothing is tracked in this Deal yet" text, updated on purpose to the new Work panel; that file then passes (13 tests, 168 assertions). The full suite was not repeated.
- **Static:** Pint clean; PHPStan 0 errors.
- **Populated MySQL 8.4.3 rehearsal of `2026_11_17`** (`tools/m4-mysql-rehearsal.php`, disposable `iet_m4_rehearsal_*` database, guarded like the M3 tool, dropped afterwards): 127 migrations; 3 historical Commitments unchanged after `up()` and their keys null; a duplicate `derivation_key` is refused by the database (error 1062); `down()` with a keyed row present drops only the column and preserves every other value; `up()` re-applies; 0 non-InnoDB tables. Only the application database (and two older `iet_m2_rehearsal_*` leftovers from M2, not touched) exist in MySQL.
- **Not done:** two-account browser walkthrough (fa/RTL, mobile, dark) of the Work tab; run it in the acceptance pass before merge. Nothing was run against the Laragon application database or production.

## M5 starts here (not begun)
Read `Fulfillment` rows with status `accepted` (and `submitted` where the Agreement's completion is `none`) per Commitment/occurrence, and `terms_data.price` / `payment` of the Commitment's own `contract_version_id`. No M4 code touches Bookkeeping.
