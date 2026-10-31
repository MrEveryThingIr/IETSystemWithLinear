# Roadmap after v1.12.1 — verified against the repository

Written 2026-10-31 from the code at the deployed commit `f1e092a1` (VERSION 1.12.1), not from PR descriptions.
Companion to the project review; where the review was uncertain or wrong this document says so.

## 1. What the repository proves

| Review statement | Verified result |
|---|---|
| "M1/M2 presence in v1.12.1 cannot be assumed" | **They are in.** `feat/m1-one-home`, `feat/m2-adaptive-intention-composer`, `feat/m5-deal-money` and `integration/v2` are all ancestors of the deployed commit. |
| "M6–M8 numbering is not a verified definition" | **It is defined** in `docs/development/M4-M8-PLAN-AND-RELEASE.md`: **M6** Business Catalog and public intake; **M7** Proven Records (read-only projection of confirmed Fulfillments, per-record consent); **M8** Referrals / incentives ("only with funded Bookkeeping accounts, explicit authorization and a compliance review; defer anything that moves money on a recommendation alone"). Referrals were planned, deliberately deferred, and are missing from the review. |
| "Confirm the Planner timing engine exists" | **Partly present:** `PlanOccurrence::executionPhase()`, window states including `Missed`, per-occurrence lifecycle, reminder emitter, horizon materialization, For later. Not in prod: the **living-planner** delta (see §3). |
| `main` as reference | **`main` is stale** (last commit 12 Sep 2026; every working branch is ~2,800 commits ahead). The real line is `integration/v2` → `release/v1.12.1-postdeploy-fixes`. |
| M5 "double counting" worry | Addressed in v1.12.1: shared `CreditAccounting` authority, advances never become reverse debt, idempotent confirmations, 64-test MySQL gate. Still open: refunds/reversals across Deals and the in-kind settlement story (§5). |
| Test state | Full suite 1329 tests; 1325 pass; the 3 failures are Windows-only OpenSSL push-reminder tests; 1 skipped. |

## 2. What exists that the roadmap can build on (no new engines needed)

- **One container model:** `Context` kinds = personal, business, group_space, admission, relationship, negotiation, contract, reference, system. Content, Planner, conversation and permissions already hang off a Context.
- **Parties are principals:** a Deal party stands for a person, a Business or a Group (`DealPrincipals`). A business can already be a party.
- **Deal = Relationship → Proposal → Contract versions → Commitments → Fulfillments**, with Work and Money tabs reading those authorities.
- **Bookkeeping ledger kinds:** personal, shared, **project**; transaction types include obligation, payment, refund, write-off, **settlement_in_kind**, adjustment, transfer. The in-kind settlement and project ledger are the natural homes for "pay with goods/services" and project expenses.
- **Invitations:** `access_invitations` (inviter actor, token, uses, expiry) plus an acceptance table link every new user to their inviter. This is the referral graph's root, already recorded.
- **Not present:** any parent/child link between Deals (no subcontracting), any platform-fee concept, any reward ledger. The old IET exchange/valuation tables were **retired** in v1.4.0 (`2026_10_19_000000_retire_legacy_financial_system`).

## 3. Work that exists on branches but not in production (triage needed)

| Branch (commits not in prod) | What it holds | Action |
|---|---|---|
| `feat/living-planner` (10 patch-unique) | private purposes, cycle-safe routines, participant execution/elapsed-window reconciliation, commitment factors, optional experiments, explicit schedule revisions | Diff against prod, then adopt or retire deliberately (R2) |
| `fix/rc-planner-*`, `fix/planner-*` (9–27 each) | planner execution/temporal/calendar/evidence hardening | Most likely overlapped by prod; confirm by test, then archive |
| `codex/iet-internal-economy-exchange` (31), `codex/iet-settlement-exchange-kernel` (60) | earlier IET unit / exchange design | **Prior art for the economics workshop only**; it targets the retired finance system |
| `codex/ideal-v1-service-financial-workflow` (129), `codex/vision-personal-operating-lab` (130), `codex/release-first-publication-hardening` (122) | large older lines | Triage once; archive if superseded |
| `codex/phase-01-invitation-onboarding` (17), `feat/global-administration` (5), `feat/pre-release-office-intent-registry` (29) | onboarding, admin, intent registry | Check overlap with Access invitations and admin before R5/R8 |

The camera/crop/placement commits are in prod (patch-equivalent), so those branches can be archived.

## 4. Prioritized roadmap

**R0 — Close v1.12.1 acceptance (days).** Run the browser checklists for Messages mobile, Content crop/camera/multi-upload/per-image answers, For later, Planner localized dates. Keep v1.12.0 rollback and quarantined media until signed off. Verify the server's `openssl_pkey_new` (push reminders).

**R1 — Canonical line and branch hygiene (1–2 days).** Declare `release/v1.12.1…` → a new `integration/v1.13` the only line; fast-forward or retire `main`; archive branches proven in prod (use `git cherry`); write the triage in §3 into a table with owner decisions. No feature work.

**R2 — Planner acceptance and the living-planner decision (1 sprint).** Diff `feat/living-planner`; decide each capability (purposes, routines, participant execution, factors, experiments, schedule revisions) as adopt / defer / drop; acceptance tests for early-start, `Missed`/expiry, flexible windows, habits, Today. One release: **1.13**.

**R3 — Structure design session, then implementation: Deals ⇄ Businesses ⇄ Groups, and Projects with subcontracting (design 2–3 days, build 2 sprints).** See §6 and §7. This is the highest-leverage change because referrals, fees and tokens all attach to the same graph of parties and transactions.

**R4 — M6 Business catalog and public intake.** Route catalog listings and the public intake into the same Deal via `originating_intent`; business public identity; permissions per member.

**R5 — M7 Proven Records.** Read-only per-record-consent projection of confirmed Fulfillments (portfolio / reputation input for the economics).

**R6 — Economics design workshop (no code; 2–3 focused sessions).** Referral rewards, platform fee, value accrual, token. Agenda and principles in §8. Output: a signed-off specification and a compliance note.

**R7 — Economics build on top of the chosen model (after R3–R6).** In this order, each independently deployable and switchable: (1) fee engine in dark mode at 0 with full ledger entries and a transparent fee schedule; (2) referral attribution and reward accrual (read-only dashboards first); (3) in-kind / voucher settlement; (4) any token or tradable unit only after a legal review.

**R8 — Release hardening.** End-to-end user journeys (register by invitation → business → listing → request → agreement → subcontract → schedule → evidence → payment → statement), full localization/RTL/mobile/dark pass, performance budgets, backup/rollback drills.

## 5. M3–M5 gaps to close inside R3

- Refunds/reversals across Deals, and "applied" vs "unused" returns in the Deal UI.
- In-kind settlement (goods/services instead of cash) wired to the Deal Money tab; valuation of the goods at the agreed moment (a recorded unit-of-account value, not a floating price).
- Expense reimbursement: expenses recorded in a project ledger and claimable through the parent agreement.
- One authoritative statement per party across all Deals.

## 6. Contractor chains ("an employee of an employee")

**Principle:** keep every Deal pairwise; build the network from links between Deals, not a multi-party contract.

- *Project* = the root Deal (employer ⇄ contractor). A **sub-Deal** (contractor ⇄ worker/supplier) points to its parent Deal and optionally to the parent Commitment it helps fulfil. One additive link (parent Deal, optional parent Commitment). No second contract engine.
- Each sub-Deal has its own Agreement, Work, Money and ledger. The worker sees only their Deal. The employer sees only theirs, unless the contractor chooses to disclose a sub-Deal (explicit per-item consent). Margins stay private to the contractor.
- The contractor gets one **Project view**: money in from the employer, money out to workers, expenses, and the margin; plus all child Deals under it. This is a read model, not a new ledger.
- Expenses (tools, materials) are entries in the project ledger, optionally claimable as a reimbursement obligation under the parent Agreement.
- **UI simplicity:** one action on a task, *Assign to someone*, which opens the normal Agreement composer prefilled with the task, quantity and a chosen rate; the contractor never has to understand parent/child.
- An actor can be employer in one Deal and worker in another: already supported by principals; the Project view only aggregates.

## 7. Deals vs Businesses vs Groups — how to remove redundancy without losing anything

What each really is today: **Group** = community of members with roles, spaces, messages, invitations, agreements. **Business** = members, professions, catalog, public identity, plus content/planner. **Deal** = pairwise agreement with work and money. They share membership, roles, invitations, messages and Context.

Recommendation (to decide in the R3 design session):

1. **Do not merge tables first.** Merge concepts at the user level: a **Space** (Context) with a *type*: Community (today's Group), Business (a Community plus public identity and catalog), Deal (pairwise or project space with Agreement, Work, Money).
2. Extract one **membership & roles kernel** used by Groups and Businesses (invitations, roles, role-change requests, ownership transfer). Today these are parallel implementations; removing the duplicates is where most redundancy is.
3. **One Space shell:** the same header, tabs and navigation for all three; Deal tabs stay Agreement · Work · Money · Conversation, because they map 1:1 to the authorities. The redundancy the user feels is mostly *entry points*: a Business should list its Deals (as a tab and filter), not duplicate them.
4. A Deal between businesses is launched from, and listed under, the Business; it still belongs to the parties' principals.
5. Deliverable of the design session: a concept map, a table of duplicated tables/screens with keep/merge/retire, and a migration plan that never rewrites history.

## 8. Referrals, platform fee and value accrual — agenda for the focused thinking time

These are mutually dependent (rewards are paid from fees; token value comes from accrued fees), so they should be designed together, built last (R7), and shipped dark.

### 8.1 Platform fee
- Parameters to decide: fee coefficient *f* per transaction class (cash payment, in-kind settlement, subscription), minimum/maximum per transaction, who bears it (payer, payee, split), exemptions (small amounts, personal transfers), rounding.
- Start at *f = 0* but build the full path now: each confirmed settlement already produces a ledger fact; the fee engine adds a **fee entry to a platform treasury ledger** with *f = 0*, so history exists from day one. A published, **versioned fee schedule** (effective dates) allows moving from 0 to a small *f* (the user's 0.0001 example) without code changes and without surprise.
- Never charge on unconfirmed or reversed amounts; reversals reverse the fee.

### 8.2 Referral rewards (the "invite → earn" circuit)
Design principles worth keeping from the repo's M8 note and from standard incentive design:
- **Reward only real, settled value:** pay on *confirmed* settlements of the invitee (Bookkeeping), never on sign-up or on a recommendation alone.
- **Paid from the platform's fee, not from the invitee's earnings:** inviter reward = α × (fee collected on the invitee's confirmed transactions) with a decay over time and a total budget cap (Σ rewards ≤ β × Σ fees, with β < 1). The invitee pays nothing extra, so the incentive is aligned and cannot make the platform insolvent. While *f* = 0 the reward pool is zero; a separate, explicitly funded bonus pool can bridge the early period.
- **One level only** (direct invitees) to avoid pyramid structures and legal exposure.
- **Activation, not volume:** measure an "activated invitee" = first confirmed earning above a threshold, plus continued activity; the best lever is guiding invitees into repeating flows (a recurring Agreement, a catalog listing with orders) because stable circuits create lasting fee flow for both.
- **Fraud controls:** no self-referral or reciprocal rings (graph checks on shared devices/payment counterparties), clawback on reversals/disputes, minimum age of the invitee relationship, rate limits.
- Open mathematical choices for the workshop: decay form (exponential vs fixed window, e.g. 12 months), α and β, thresholds, whether to reward by lifetime value estimate, how to display expected earnings honestly. These need real transaction data and a simulation, not guesses.
- Data already available: inviter on every account (`access_invitations`/acceptances). Missing: reward ledger, attribution rules, dashboards.

### 8.3 Value accrual, in-kind settlement and a token
- The fee treasury is an ordinary ledger first. A token or tradable unit is a later, separate decision: it brings legal questions (central-bank rules on tokens, securities-like treatment of "profit from accumulated fees"), custody, and accounting for redeemability.
- A safe staging: (1) internal **unit of account** with no trading, used to value goods/services at the settlement moment (Bookkeeping already has `settlement_in_kind`); (2) **vouchers redeemable** against intents for food, clothing, services; (3) only then consider whether treasury-backed accrual and any tradability are lawful and sensible.
- Prior art to read before designing: the `codex/iet-*` exchange branches (they target the retired finance system).

### 8.4 Decisions to bring to the workshop
1. Fee: base coefficient, schedule mechanics, who pays, exemptions.
2. Referral: pool funding, α/β/decay, activation definition, anti-fraud policy.
3. In-kind valuation: who sets the value at settlement and how disputes resolve.
4. Token: whether, when, and under what legal structure.
5. Compliance review for each before any money moves on a recommendation.

## 9. Order of operations in one line
R0 accept → R1 clean branches → R2 Planner → R3 structure (Projects, subcontracts, Space consolidation) → R4 Business catalog → R5 Proven Records → R6 economics design → R7 economics build (fee dark → referrals → in-kind → token) → R8 hardening.

## 10. Review refinements (second opinion, 31 Oct 2026)

A second review of this roadmap agreed with R0–R8 as the canonical plan and asked for four refinements. They are adopted here rather than starting another planning file.

1. **A Project is a read model, not a new authority.** It is a workspace over a root Deal and its descendant Deals plus existing project-ledger facts. Parent/child links must be acyclic; each Deal keeps its own versions, commitments, fulfillments and settlements; money is never transferred up or down the hierarchy automatically; a contractor's margin and supplier prices stay private; project expenses and reimbursable expenses are different things (reimbursement needs an agreement term).
2. **Reuse Context; add no Space table.** "Space" is a shared presentation and authorization experience over Context and the existing Business, Group and Deal records. The Deal's Agreement, Work, Money and Conversation tabs stay: they carry real responsibilities. Remove duplicate entry points, duplicate editors and inconsistent membership operations, not tabs.
3. **Proven Records (M7) is not a second fulfillment system.** It stays a read-only, per-record-consent projection of confirmed Fulfillments. Consent never replaces source authorization: a public record needs both.
4. **Separate ordinary Deal–Money correctness from new economics.** Refunds, reversals, applied/unused credit, project expenses, reimbursement, in-kind settlement and per-party statements are existing-domain requirements and belong to R3. Fees, rewards, vouchers and any token stay in R6–R7.

Two technical notes for R6/R7: the zero-rate fee must be stored as an explicit fee-assessment record unless the Bookkeeping schema is proven to accept zero-amount postings without weakening its invariants; and a growing treasury balance gives token holders nothing by itself, so any value-sharing needs specified legal rights, redemption or distribution rules, funding and risk disclosure.

Branch note: leave `main` untouched until the canonical-line strategy and any branch-protection dependencies are verified.

## 11. The end-to-end baseline (smaller milestones, B0–B5)

The goal of this slice is a baseline that works end to end and can be shown to people, with the economics experiments left as clearly labelled research. Each milestone is small enough to ship alone and reuses an authority listed above.

| # | Milestone | Scope | Done when |
|---|---|---|---|
| B0 | Public documentation and invitation to collaborate | Repository-owned bilingual (Persian/English) documentation at `/docs`, separate from Content; expert contribution intake; invite-only mode with links managed by an administrator; contextual Help points to it | Docs readable without an account in both languages; a contribution can be submitted by a guest or a member; tests green |
| B1 | Accept and align (R0 + R1) | v1.12.1 browser acceptance; one canonical integration line; branch-disposition matrix | Signed-off checklist; matrix committed |
| B2 | Subcontract link | Additive parent Deal link (and optional parent task link); one "Assign to someone" action; contractor Project overview as a read model | A contractor can employ a person under a project and see the whole chain; the client cannot see the contractor's margin |
| B3 | Money correctness for projects | Project expenses and reimbursement term; refunds and unused credit; in-kind settlement with an agreed value and date; one statement per party | Acceptance scenario with a purchase, a wage and a barter settlement balances for every party |
| B4 | Fee and referral foundations, dark | Fee schedule stored at 0 with an assessment record per confirmed settlement; invitation attribution; read-only "people you invited and what they did" view; no payouts | Switching a schedule from 0 changes nothing else; no money moves on a referral |
| B5 | Baseline journey and release (R2 decision + R8 slice) | Planner v1.13 adopt/defer decision; one scripted journey from invitation to settlement in fa and en, mobile and desktop | Journey passes in a browser; release notes and docs updated |

Order: B0 now (it is also the invitation that brings in the experts who should shape B3 and B4), then B1, B2, B3, B4, B5. R6 (economics design) runs in parallel as discussion, informed by contributions received through B0.
