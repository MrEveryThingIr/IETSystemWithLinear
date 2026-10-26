# Agreement clauses → operational work and money (design record for M4/M5)

Status: design only. Nothing here is implemented by M3. It exists so M4/M5 extend the Deal workspace without creating a second authority.

## Principle

The **Agreement** (accepted ProposalVersion, later ContractVersions) is the only authority for *what was promised, when, and for how much*. Commitments, Plan occurrences, Fulfillments and Bookkeeping entries are **derived operational records**. Each one points back to `(contract_version_id, term path, cycle)` and never restates the term. Changing the Agreement never rewrites finished work or money; it only changes what is derived afterwards.

## Existing authorities that are reused (real code, not new systems)

| Need | Existing authority | Notes |
|---|---|---|
| Who owes what work | `Commitment` (obligor, beneficiary, quantity, unit, due window), `CreateCommitment`, `CreateCommitmentPlan`, `BindExistingPlan` | Immutable rows; M3 only removed the *manual creation control* from the ordinary Deal. Backend and historical records are untouched. |
| Planning and occurrences | Planner (`CreatePlan`, schedule rules, `MaterializePlanOccurrences`, `AttachPlanOccurrenceEvidence`) | Personal Planner completion is **not** contractual performance. |
| Report / confirm / dispute performance | `SubmitFulfillment`, `ReviewFulfillment`, `OpenFulfillmentDispute`, `ResolveFulfillmentDispute` | The only place performance is confirmed. |
| Money records | Bookkeeping (`CreateLedger`, `RecordSharedEntry`, `DecideOnEntry`, `RaiseDispute`, `ProposeWorkBookkeepingEntry`) | Shared entries need the counterpart's decision; derived balances are views. |
| Historical service contracts | `ContractServiceTerm` (employer, worker, unit rate, settlement cycle, plan binding) | Stays valid; shown read-only in the Agreement tab. |

## Mapping of typed terms (the only terms that can drive operations)

| Agreement term | Derived record | Rule |
|---|---|---|
| `fields[]` (typed details), `quantity`, `duties`/`deliverables` clauses | Commitment titles, quantity, unit | A person **chooses** which duties become commitments in a *Set up the work* step with a preview. Narrative clause text is copied as a human-written title, never parsed or inferred. |
| `time` (dates, daily hours, zone), `recurrence` (every, unit, count) | Plan schedule rule and occurrences (one Plan per Agreement, one occurrence per cycle) | Cycles are generated from the term; the Agreement is not duplicated per cycle. |
| `completion.confirmed_by` (beneficiary / both / none) | Who must `ReviewFulfillment` for a cycle | `none` means the cycle closes on report; it never means "paid". |
| `price`, `payment.mode` (none, on_acceptance, after_confirmed_delivery, per_period) | Bookkeeping *proposed* entry per cycle: owed amount in Toman | `after_confirmed_delivery` creates the owed entry only after the Fulfillment is accepted; `per_period` creates one per cycle; `on_acceptance` creates it when the Agreement is accepted. Currency is Toman for new Terms (`DealTerms::SUPPORTED_CURRENCIES`). |
| narrative clauses (cancellation, disputes, confidentiality, special, custom, notes) | nothing | Never executable. |

## Guard rails (tested at each milestone gate)

1. Planner completion never creates or confirms a Fulfillment.
2. An accepted Fulfillment creates an *owed* proposal, never a payment; a reported payment is *reported* until the counterpart decides; *settled* needs both sides.
3. Derivation is explicit and idempotent: key = `(contract_version_id, term path, cycle index)`; running it twice creates nothing new.
4. An amendment (new ContractVersion) affects only cycles that start after its effective time; earlier cycles keep their original version reference and amounts.
5. Business/Group principals act through `PartyAuthority` at action time; the signer is recorded, as in M3.
6. Privacy: Work and Money show only what the viewer's role in the Deal allows; no balances appear unless a Bookkeeping record exists.
