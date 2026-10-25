# M3-C1 — Authority map, architecture decision, test and implementation contracts

Status: **C1 complete (documentation + characterization tests; no behavior change).** Base: `231d3b5f` on `feat/m3-deals-terms`.
Evidence: read from code at this commit; `tests/Feature/M3DealAuthorityBaselineTest.php` pins the three gaps (G1–G3) and passes.

## 1. Authority and entry-point map

### 1.1 One source of truth per concept

| Concept | Canonical record | Immutable? | Notes |
|---|---|---|---|
| Origin | `IntentVersion` (pinned by `relationships.originating_intent_version_id`) | yes (published snapshot) | Only place the Need/Offer's terms live. Read via `IntentPresentation`, never from the mutable `ActorProfileIntent` |
| Deal (room) | `Relationship` + `RelationshipParticipant` | no (status) | Who may talk; not who may bind |
| Negotiated Terms | `ProposalVersion` → sealed `SpaceContentRevision` | yes (model guard, no delete) | The **only** place Terms are authored. New row per change |
| Consent to Terms | `ProposalDecision` (unique per version+party) | yes | Accepting = "this exact version" |
| Agreement | `Contract` (unique per source ProposalVersion) + `ContractVersion` (unique `terms_content_revision_id`, same sealed revision as the ProposalVersion) | yes | Adds effective date/zone, activation, amendments, service terms |
| Consent to Agreement | `ContractAcceptance` (unique per `ContractVersionParty`) | yes | Today a second, separate act (G2) |
| Authority to bind | `PartyAuthority::canActFor(User, Party)` (Actor self / Business owner-manager / Group `manageConcepts`) | n/a | Used by M2 publishers; **not** used by Proposal/Contract policies (G3) |
| Group rules | `GroupAgreement*`, `MembershipAgreement*` | — | Different concept; never merged with commercial Contracts |

### 1.2 Entry points (all keep working; none removed)

| Route / button | Guard | Disposition |
|---|---|---|
| `/deals` (`deals.index`) = `/relationships` | `RelationshipPolicy::viewAny` | Primary list. Keep |
| `/relationships/create?intent=` (from `intents/show`, directory, matches) | `RelationshipPolicy::create`; bare URL redirects to `intents.index` | **The** Need/Offer→Deal entry. Keep; C4 changes copy only |
| `/relationships/{id}` | `view` | Becomes the single Deal page (C4) |
| `/proposals/create?relationship=` | bare URL redirects to `deals.index` | Keep as internal step; C4 starts it inline from the Deal page |
| `/proposals/{id}` | `ProposalPolicy::view` (actor = party) | Keep URL; C4 redirects to the Deal page, same data |
| `/contracts/create?proposal=` (`CreateContractFromProposal`) | `ContractPolicy::create` + `view` proposal | **Becomes automatic** (§2.3); route stays for pre-existing accepted Proposals |
| `/contracts/{id}`, `/contracts` | `ContractPolicy::view` | Keep; presented as "Agreement" |
| `CreateDirectContract` | trusted imports only | Keep untouched (not a user entry point) |
| Notifications, Home/Today, timelines (`DomainNotificationProjector`, `HomeTodayProjection`, `ContextTimeline`) link to `relationships/proposals/contracts.show` | — | Compatibility obligation: URLs and route names stay valid |

### 1.3 Duplicated flows

1. **Double acceptance**: Proposal then Contract (`RespondToProposal` + `AcceptContractVersion`) for the same parties and, as enforced today, identical Terms.
2. Three "show" pages for one story (Relationship, Proposal, Contract).
3. Party validation re-implemented in `CreateProposal`, `CreateContractVersion::normalizeParties` and the policies (all Actor-only).
4. Cancel is the only withdrawal; it is creator-only and named "cancel".

### 1.4 Findings (pinned by the baseline test)

- **G1 (correctness).** `RespondToProposal` records the decision on the *latest* version at lock time; the caller cannot say which version they saw. If a counter-version lands between render and click, the person accepts Terms they never read. Must be fixed before any single "Agree" ships.
- **G2 (redundancy).** After a Proposal is accepted each non-proposer must accept the ContractVersion again.
- **G3 (authority).** `proposal_parties` and `contract_version_parties` are Actor-only; a Business or Group cannot be a party.
- Verified safe: accept/propose/cancel paths lock `proposals` / `contract_versions` rows inside transactions; Contract creation is unique per ProposalVersion; `FinalizeContractVersionAcceptance` is idempotent (returns early unless `Proposed`, re-checks under lock); `AcceptContractVersion` is serialized by the version lock plus unique acceptance per party. Initial ContractVersion Terms must equal the accepted ProposalVersion's revision exactly.

## 2. Architecture decision

**One Deal page, one decision ("Agree"), both immutable records kept.** Do not drop `Proposal*` or `Contract*`; change *who writes the second one and when*.

### 2.1 Structured Terms (stored once, on the ProposalVersion)

Additive, nullable: `proposal_versions.terms_data` (JSON) and `terms_hash` (sha256 of canonical JSON). Written at creation only; covered by the existing immutability guard. The sealed text revision is still produced (rendered from `terms_data` + free text) so every existing reader and the ContractVersion link keep working unchanged.

`terms_data` schema v1 (all keys optional except `schema`; unknown keys rejected):
```
schema: 1
origin: { intent_uuid, intent_version_id, kind }            // pinned, from the Deal
price:  { min, max, currency, basis }                        // decimal strings, never floats
place:  text      time: { starts_on, ends_on, timezone }
quantity: { value, unit }      route: { from, to }
category: { concept_uuid, label }
fields: [ { key, label, value } ]                            // typed answers, privacy-safe reading only
effective: { mode: "on_acceptance" | "at", at?, timezone }   // default on_acceptance
notes: text                                                  // free text, always allowed
```
Old Proposals have `terms_data = null` and render as free text only (no backfill).

### 2.2 Acceptance semantics

- Acceptance is `(party, ProposalVersion, expected_version_number)`. The action takes the version the person saw; if it is not the latest, it is rejected as stale (fixes G1). The UI sends it from the rendered page.
- A new version never inherits others' acceptances (already true). The proposer of a version carries their own acceptance (already true).
- Decisions stay: accept / request changes / reject. Withdrawal = the creator ends the negotiation (existing `CancelProposal`, relabelled "Withdraw"). No retraction of an individual acceptance: a new version supersedes. This is the simple, auditable rule.

### 2.3 Single meaningful acceptance (removes G2 without weakening anything)

When the last required party accepts the latest ProposalVersion, **in the same transaction** as `applyStatus(Accepted)`:
1. Create the `Contract` + initial `ContractVersion` through the existing `CreateContractFromProposal` internals (effective date from `terms_data.effective`; `on_acceptance` means now).
2. Create one `ContractAcceptance` per required party **derived from that party's `ProposalDecision`**: add nullable unique `contract_acceptances.source_proposal_decision_id`; `accepted_by_user_id` and `accepted_at` are copied from the decision, so signer identity and time are the real ones.
3. `FinalizeContractVersionAcceptance` runs (already idempotent) → Accepted → Active at `effective_from` (scheduled activation unchanged).

Why this is legally and transactionally equivalent: the consent is to the *same sealed revision* (enforced today), by the *same required parties*, recorded once as an immutable decision and referenced by the acceptance; the effective date the Contract used to add is now part of the Terms people saw and agreed. If a Deal needs service configuration that `terms_data` cannot express (today's `serviceTerms` form), it stays at "Terms accepted" and the explicit Contract form remains (fallback = today's behavior). Accepted Terms still create **no** commitments, delivery or payment (M4/M5).

Manual `AcceptContractVersion` stays for amendments and for older accepted Proposals.

### 2.4 Business / Group participation

- Additive nullable `business_id`, `group_id` (FK, restrict) on `proposal_parties` and `contract_version_parties`, mirroring M2 `publisher_*`; at most one of them set per row. `actor_id` stays NOT NULL and means the **representative** (for a Business/Group party: the manager who added it), so existing uniques and queries are unchanged and old rows stay valid.
- Party identity = `Party::of(...)`. Policies replace "actor equals party actor" with `PartyAuthority::canActFor(user, party)`: one rule for view/respond/propose/cancel/accept. Any Business owner/manager may decide for the Business; **one decision per party, not per person** (`decided_by_user_id` records who). Multi-signer thresholds are **deferred** (no current requirement; additive later).
- Prefill: when the Deal comes from an intent published by a Business/Group (`publisher_business_id/group_id`), that Business/Group is the counterparty party automatically; the responder chooses to act as self or as a Business/Group they manage (`PartyAuthority::partiesFor`).
- Rule kept simple: one party per principal per Proposal; the same person cannot be two principals in one Proposal (existing `unique(proposal_id, actor_id)`).

### 2.5 Version comparison

Pure function over two `terms_data` arrays (+ notes) → ordered rows `{key, label, status: added|changed|removed, from, to}` and a one-line summary. Used by the Deal page ("what changed since you last looked") and event payloads. No DB access.

### 2.6 Rejected alternatives

New `Deal`/`Terms` tables (duplicate the verified immutable models); merging Contract into Proposal (breaks amendments, activation, commitment links); hiding the second click in the UI (loses the evidence link between decision and acceptance).

## 3. Test and implementation contracts

### 3.1 Invariants (each gets a test in the named checkpoint)

| # | Invariant | CP |
|---|---|---|
| I1 | A ProposalVersion and its `terms_data`/`terms_hash` never change; `terms_hash` = hash(canonical `terms_data`) | C2 |
| I2 | A decision on version N is rejected if N is not the latest (stale) | C3 |
| I3 | Proposal becomes Accepted iff every required party accepted the same latest version | exists; keep |
| I4 | Accepted ⇒ exactly one Contract, one ContractVersion on the same revision, one derived acceptance per required party referencing its decision | C3 |
| I5 | N concurrent accepts/finalizes ⇒ one Contract, one acceptance per party, status transitions once, no duplicate events | C3 |
| I6 | accept vs new-version race ⇒ either accepted-then-superseded (Accepted + Contract exist) or stale-rejected; never an acceptance on an unseen version | C3 |
| I7 | reject/withdraw vs accept race ⇒ one terminal status wins; no Contract after Rejected/Cancelled | C3 |
| I8 | A user acts for a Business/Group party iff `PartyAuthority::canActFor`; outsiders get 403/404; one decision per party | C3 |
| I9 | Accepted Terms create no Commitment/Fulfillment/ledger rows | C3 |
| I10 | Old routes/names/URLs still resolve; old Proposals without `terms_data` still render and accept | C2/C4 |
| I11 | `terms_data.origin` equals the Deal's pinned `originating_intent_version_id` | C2 |

### 3.2 Interfaces (fixed now; implemented in C2/C3; T-A/T-B may go to Codex)

```php
// App\Support\Deals\DealTermsFromIntent (T-A): pure, no writes
public function build(IntentVersion $version, ?User $viewer): array;   // terms_data schema v1; no private details unless viewer may manage

// App\Support\Deals\DealTermsDiff (T-B): pure
/** @return array{rows: list<array{key:string,label:string,status:'added'|'changed'|'removed',from:mixed,to:mixed}>, summary:string, unchanged:int} */
public function compare(array $from, array $to): array;

// C2: ProposeTermsVersion::execute(..., ?array $termsData = null)   validates schema v1, stores terms_data + terms_hash
// C3: RespondToProposal::execute(Proposal, User, kind, ?string $note, ?int $expectedVersion = null, ?Party $actingAs = null)
// C3: FinalizeProposalAgreement::execute(Proposal $locked): ?Contract   // §2.3, called inside the same transaction
```
`expectedVersion = null` stays accepted for old callers during C3; the UI always sends it.

### 3.3 Acceptance scenarios (C5 journeys)

S1 Offer published by a Business → individual responds → Deal prefilled with the Offer's terms → counter → accept → Agreement active, one click each. S2 Group with two managers: only one decision recorded for the Group. S3 Counter-offer posted while the other party is on the page → their Agree is refused with "terms changed" and they see the diff. S4 Creator withdraws while the other party accepts → exactly one outcome. S5 A pre-M3 accepted Proposal without a Contract still reaches a Contract via the old form. S6 RTL/fa/mobile/dark render of the Deal page.

### 3.4 Boundaries

C2 touches: `proposal_versions` migration, `ProposeTermsVersion`, `Support/Deals/*`, the terms/diff section of the Deal page. C3: party-column migration, `contract_acceptances.source_proposal_decision_id`, `RespondToProposal`, `FinalizeProposalAgreement`, the three policies. C4: Livewire/Blade/lang/route redirects only. Nobody touches M4/M5 (commitments, ledger) or `CreateDirectContract`. Migrations additive and reversible; tests on in-memory SQLite or a disposable DB only.
