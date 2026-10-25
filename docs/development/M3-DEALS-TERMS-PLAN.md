# M3 — Deals and Agreed Terms: evidence-based plan

Status: **planned, not started.** Code starts only after the owner explicitly accepts M2 and the integration chain is merged
(see the M2 handoff). Roadmap, not code, is changed by this file.

Target journey: Need/Offer → Discussion → Deal → Negotiated Terms → acceptance by all required parties → Active Agreement.

## 1. What already exists (read from the code, Oct 25)

The canonical pipeline is already documented in `docs/CANONICAL_TRANSACTION_PIPELINE.md` and implemented:

| Layer | Models / actions | Verified behavior |
|---|---|---|
| Deal room | `Relationship` (+ participants, context, events); `CreateRelationship`, `RespondToRelationship`, `Cancel/EndRelationship` | Columns `originating_intent_id`, `originating_intent_version_id`, `matched_intent_id`: a Deal already pins the Need/Offer *version*. Routes `deals.index` (alias of relationships), `relationships.show/create` |
| Negotiation | `Proposal`, `ProposalParty`, `ProposalVersion`, `ProposalDecision`, `ProposalEvent`; `CreateProposal`, `ProposeTermsVersion`, `RespondToProposal`, `CancelProposal` | Terms version is immutable (a new row each time, text kept as a content revision in the Deal's context). `RespondToProposal` locks the proposal and latest version (`lockForUpdate`, 3 attempts), one decision per party per version, reject ends it, accepted only when every *required* party accepted **the same latest version**. Proposer's version carries their acceptance |
| Agreement | `Contract`, `ContractVersion`, `ContractVersionParty`, `ContractAcceptance`, `ContractEvent`; `CreateContractFromProposal`, `AcceptContractVersion`, `FinalizeContractVersionAcceptance`, `ActivateContractVersion`, `ActivateDueContractVersions` (scheduled), `ProposeContractAmendment` | A Contract is created explicitly from an accepted ProposalVersion (unique per version), with effective date/time zone; each party accepts the ContractVersion (unique per party, locked); finalization and activation at `effective_from` |
| Governance agreements | `GroupAgreement*`, `MembershipAgreement*` | Different concept (Group rules); copy must not mix with commercial Contracts |
| After agreement | Commitments, Fulfillments, financial obligations | M4/M5 territory; must not start merely because Terms were accepted |

## 2. Findings that shape M3 (gaps, not duplicates)

1. **Two acceptance rounds.** Parties accept the Proposal version, then accept the ContractVersion again. Domain-correct (Contract
   adds effective dates and service terms) but it reads as asking twice. Consolidate in the *experience* first: one Deal page,
   one "Agree" step, with the Contract created automatically and its acceptance recorded from the same decision, only if
   C1 proves it keeps the two immutable records and their invariants. Do not drop either model.
2. **Parties are Actors only** (`proposal_parties.actor_id`, `contract_version_parties.actor_id`, `relationship_participants.actor_id`),
   while Needs/Offers can be published by a Business or Group (M2 `publisher_*`). Business/Group authority (who may bind the
   Business, several required signers, delegation) is the main authority gap. Reuse `PartyAuthority` / the M2 publisher parties;
   add the party reference to existing party tables additively instead of creating new party models.
3. **Terms are free text.** Negotiated Terms should start from the pinned Offer/Need's canonical Deal terms (price/budget, place,
   time, quantity, route, category) and the typed field answers, shown as structured, diffable terms plus free text. The M2
   `IntentPresentation` and `IntentTerms` already provide the canonical, privacy-safe reading of those terms.
4. **Entry points:** answering a Need/Offer should lead to a Deal without the person learning the words Relationship, Proposal
   or Contract. Existing routes (`/deals`, `/relationships/*`, `/proposals/*`, `/contracts/*`) stay and redirect or link as needed.
5. **Visibility of state** (what is proposed, who agreed, what is open, what happens next) is not yet one screen.

## 3. Principles

Prefer consolidation over new models; keep proposed/accepted Terms immutable; acceptance means *the same version* by every required
party; correct Actor/Business/Group authority; safe under concurrency (locks + unique constraints + idempotent finalize);
explicit withdrawal, rejection and renegotiation; historical link to the originating Need/Offer version; accepted Terms never
create delivery, payment or obligations (M4/M5). Additive, reversible migrations; tests on disposable databases only.

## 4. Proposed checkpoints (adjustable after C1)

- **C1 — Authority audit and compatibility plan.** Map every entry point and policy; list tests that already cover the pipeline
  (`CanonicalDealPipelineTest`, `ContractKernelTest`, `ContractExperienceTest`, relationship/proposal tests); verify the claims in
  section 1 with targeted reads/tests; write the exact design for party references (Actor/Business/Group), automatic Contract
  creation, and withdrawal/renegotiation states. Output: updated plan, no behavior change.
- **C2 — Unified negotiation and immutable Terms.** Structured Terms prefilled from the pinned intent version, with free text;
  immutable versions and a readable diff between versions; withdrawal and renegotiation as explicit events.
- **C3 — Required-party acceptance, permissions, concurrency.** Business/Group parties and signers; same-version acceptance;
  concurrent accept/withdraw/new-version races; idempotent finalize/activation; policy tests per actor type.
- **C4 — Natural Deal UX and navigation.** One Deal page: proposed Terms, who agreed, what is open, what happens next; responding
  from a published Need/Offer; My Items/Deals entry points; four languages, RTL, mobile, dark.
- **C5 — Acceptance and hardening.** Journeys from the owner's list, regression suites, MariaDB/MySQL rehearsal, handoff, tracker.

## 5. Efficiency

Sonnet/medium for normal work; higher effort only for authority and concurrency design (C1 design, C3). Local search and targeted
tests; full suite only at checkpoint gates. One agent per branch; isolated backend tasks (for example the party-reference migration)
may go to Codex on a separate branch once interfaces and acceptance tests are written. Start a fresh session at each checkpoint.

## 6. Out of scope for M3

Work reporting and delivery confirmation (M4), Deal subledgers and settlement (M5), broader Business integration (M6).
