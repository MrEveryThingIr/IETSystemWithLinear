# Unified Ideal-v1 Convergence Manifest

## Canonical convergence line

Branch:

`integration/ideal-v1-unified-finalization`

Purpose:

One selectively reconciled Ideal-v1 line that preserves mature domain behavior while retaining the strongest later Planner, Calendar, Money, Vault, IET, AI and usability work.

This branch is intentionally **not** a mechanical merge of every historical branch. Parallel implementations are admitted only when they improve the canonical domain model without introducing a second source of truth.

## Release surface

The default release profile is `full`.

The convergence runtime keeps:

- the mature authenticated application and domain navigation;
- Planner / fractal Calendar / Tools;
- compact personal `/money`;
- mature `/accounting`;
- `/exchange`;
- `/vault`;
- Content / Reader / Studio / evidence;
- Groups / Admissions / Agreements / Context collaboration;
- Need / Offer, Relationships, Proposals, Contracts;
- Commitments / Fulfillments;
- Financial Obligations / Settlements;
- notifications / realtime infrastructure;
- System Map;
- AI Chat / AI Content Assistance;
- Development Origin audit provenance.

## Branch reconciliation ledger

### Fully subsumed ancestors

These lines have no unique commits relative to this branch and are treated as incorporated ancestry:

- `codex/planner-readiness-execution-calendar-density`;
- `codex/personal-money-vault-calendar-tools`;
- `codex/review-f1-shared-temporal-fabric`.

### `codex/ideal-v1-service-financial-workflow`

Status: **semantically reconciled**.

A whole-branch merge probe conflicted because the branch predates hundreds of later convergence commits. Its changes were replayed from common ancestor `ec1ae38859e2e4e00fe41da3a886181f9a7e0626` onto current files.

Admitted behavior includes:

- versioned `ContractServiceTerm`;
- contract activation -> service Commitment -> optional Planner Plan;
- deterministic compensation;
- accepted Fulfillment -> Financial Obligation;
- contract settlement batches;
- pending-allocation protection;
- per-work-day financial traceability;
- financial summaries and System Map integration;
- end-to-end service workflow regression coverage.

The only substantive code conflict, `ProposeSettlement`, was resolved explicitly so contract settlement batches coexist with newer IET wallet funding/availability rules.

### `integration/ideal-v1-temporal-calendar-reconcile`

Status: **selectively reconciled; newer temporal core retained**.

Admitted:

- direct occurrence evidence upload;
- execution error feedback;
- explicit Upcoming / Ready / Late / Missed occurrence window states;
- late-start semantics;
- calendar visibility and reconciliation tests;
- execution-window and evidence-upload regression tests.

Superseded by newer convergence implementations:

- older `TemporalCalendar`;
- older temporal JavaScript;
- older ambient-status implementation.

### `release/ideal-v1-rc-7-hardening` and `codex/release-first-publication-hardening`

Status: **selectively reconciled**.

Admitted:

- verified-user default provisioning;
- default monetary-unit preference;
- non-destructive preference semantics;
- first-publication regression gate.

The newer profile temporal editor remains authoritative and now combines:

- timezone mode;
- timezone;
- calendar;
- date format;
- time format;
- Gregorian-equivalent display;
- default monetary unit.

The monetary preference is used as the default for new Planner expenses, service-contract pricing and personal ledger creation. Existing history is never converted.

Older temporal/header implementations were not restored where the convergence line already has stronger replacements.

### `feat/context-ai-assistance-provenance`

Status: **semantically restored**.

Admitted:

- server-side OpenAI configuration;
- schema-constrained Content planning;
- explicit proposal review/apply;
- stale-proposal rejection;
- immutable `AiAssistanceRun` provenance;
- Content Studio AI entry point;
- immutable `DevelopmentOrigin` audit records;
- multilingual UI and tests.

Extended on the convergence line with:

- authenticated AI Chat Lab;
- bounded in-page conversation state;
- OpenAI Responses API;
- `store=false`;
- provider/model/response/usage feedback;
- a dedicated System Manual AI chapter and contextual help routing.

AI remains an assistance layer. It has no implicit authority to publish, accept, approve, settle, transfer, or otherwise mutate protected domain truth.

### `codex/iet-internal-economy-exchange` and `codex/iet-settlement-exchange-kernel`

Status: **capabilities reviewed; parallel kernels superseded**.

Useful capabilities already preserved in the convergence architecture include:

- IET wallet and guarded posting;
- versioned IET valuation;
- USD-referenced IET flow charges;
- USD-priced Fulfillment recognition into IET with quote provenance;
- funded atomic IET Settlement;
- deposit/cash-out review;
- available-balance reservation for pending cash-out.

Rejected/superseded designs:

- duplicate IET namespace/model stacks;
- a second finance surface that competes with the mature Financial Obligation/Settlement kernel;
- “system journal” posting that actually writes inside a user's ledger;
- unrestricted free-form internal transfer as an alternative to authoritative Contract/Obligation settlement.

The convergence line instead adds a true platform IET Treasury on a System Context.

## Canonical economic model

### Monetary Unit

A unit of account for Ledger / Obligation truth, for example USD, IRR, EUR or IET.

### Economic Instrument

Something that can be identified and valued, including:

- fiat currency;
- internal unit;
- crypto asset;
- commodity;
- market index;
- property index;
- physical asset class;
- service unit.

An instrument is not automatically money, a balance, or an owned asset.

### Market Quote

Immutable append-only observation between two Economic Instruments with exact decimal precision, source/provenance, effective time and optional confidence.

The legacy IET valuation publisher is retained as a compatibility API and mirrors IET/USD observations into this generalized quote history.

### Specific owned/offered economic rights

A user's specific house, vehicle, gold lot, service promise or receivable belongs to its domain model and may reference market/index evidence. It is not represented by a generic market index row.

## IET system accounting

IET remains the internal settlement Monetary Unit.

The global IET Treasury reuses the existing immutable accounting kernel through a durable System Context.

Treasury accounts:

- external exchange reserve equivalent;
- service-advance receivable;
- settlement clearing;
- net IET issuance;
- system fee income.

Confirmed exchange deposits increase reserve backing and net issuance atomically.

Confirmed cash-outs retire IET and release reserve backing atomically, and cannot exceed available exchange reserve.

Exchange review requires another authorized reviewer; self-confirmation and self-rejection are forbidden.

User-to-user Contract/Obligation settlement transfers value without minting new IET.

## Trusted service / skill economics

A self-declared skill is **not** mint authority.

The canonical economic object is a versioned service unit / Contract service term with explicit:

- provider and counterparty;
- task/package;
- quantity/unit;
- compensation and Monetary Unit;
- schedule;
- evidence;
- fulfillment/review semantics.

The trusted lifecycle is:

`Need/Offer -> Match -> Relationship -> Proposal -> ContractVersion -> ContractServiceTerm -> Commitment -> Planner -> Fulfillment -> FinancialObligation -> Settlement`

Reputation and future service-backed advances must derive from accepted evidence in this lifecycle.

A future IET advance may debit a service-advance receivable and credit net issuance only after an explicit underwriting/agreement policy. It must never be created merely because a user says they possess a valuable skill.

## Planner / temporal convergence

The current Planner retains:

- profile-aware timezone/calendar presentation;
- fractal Year -> Month -> Day -> Hour -> minute-slot drill-down;
- compact high-density calendar cells;
- modal/detail exploration rather than unbounded cell growth;
- plan types/categories/attention semantics;
- prerequisites/readiness;
- estimated and actual expenses;
- existing evidence attachment;
- direct evidence upload;
- explicit execution windows;
- Upcoming / Ready / Late / Missed state;
- precise local datetime presentation plus configured equivalent display.

## AI boundaries

AI Chat is a connectivity/assistance surface.

AI Content Assistance follows:

`human intent -> provider proposal -> local validation -> user review -> authorization -> existing domain Action -> new durable revision`

The API key is environment-only and must never be committed.

Development Origins preserve reviewed provenance from a meaningful chat/design source to phase/version/branch/commit/docs without making raw chats architectural authority.

## Deliberate non-merges

Do not reintroduce:

- duplicate ledger/balance engines;
- mutable balance columns as financial truth;
- duplicate IET economy model families;
- legacy temporal JS over the newer temporal fabric;
- old release-profile hiding as the default;
- free-form AI database authority;
- self-declared-skill issuance;
- automatic repricing of historical obligations from today's quote;
- cross-currency totals without explicit conversion evidence.

## Acceptance gate

The convergence head is acceptable only when all repository gates pass:

1. Composer metadata;
2. MySQL migration portability;
3. npm audit;
4. frontend build;
5. Pint;
6. PHPStan;
7. migration rollback/reapply + scheduler/queue smoke;
8. SQLite backup/restore smoke;
9. full PHPUnit;
10. Composer security audit.

After CI is green, perform local browser acceptance on this exact branch before merging it into a stable release line.
