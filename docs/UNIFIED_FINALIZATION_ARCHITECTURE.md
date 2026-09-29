## 2026-09-29 convergence update

The unified branch now also includes:

- the mature paid-service financial workflow from `codex/ideal-v1-service-financial-workflow`, reconciled semantically from common ancestor `ec1ae38859e2e4e00fe41da3a886181f9a7e0626` rather than force-merged;
- structured `ContractServiceTerm` economics bound to an exact ContractVersion;
- Contract activation -> service Commitment -> optional shared Planner Plan;
- accepted Fulfillment -> deterministic Financial Obligation pricing;
- contract cash settlement batches with pending-allocation protection and per-work-day traceability;
- coexistence of those cash/service semantics with the newer IET settlement balance checks;
- the restored audited AI Content Assistant from `feat/context-ai-assistance-provenance`;
- a new authenticated AI Chat Lab using the OpenAI Responses API with server-side credentials and `store=false`;
- immutable `AiAssistanceRun` provenance and stale-proposal protection;
- restored `DevelopmentOrigin` records for deliberately reviewed ChatGPT/design-session -> branch/commit/docs traceability.

The AI key is never stored in repository data or sent to the browser. Configure it only through the runtime environment.

Service-backed IET credit remains intentionally downstream of verified Contract/Fulfillment evidence. A self-declared skill or manually registered service-rate reference is not mint authority.

---

# Unified Ideal-v1 Finalization Architecture

## Purpose

This branch is the convergence line for the mature Ideal-v1 product and the strongest later reconstruction work.

Active branch:

`integration/ideal-v1-unified-finalization`

Starting point:

`codex/iet-internal-settlement-exchange@2b19fccfe50a6b351a345e030a37d40808e54700`

The branch starts from the newer Planner / Calendar / Money / Exchange / Vault candidate because that line already contains `release/ideal-v1-rc-6` in its ancestry and preserves the newer accepted interaction work. The mature application is restored by running the `full` release profile instead of discarding the newer work and checking out an older release.

## Repository audit result

The apparent "rebuild from scratch" is primarily a release-surface decision, not deletion of the mature system.

The current candidate still contains the mature routes and domain kernels for:

- identity / Actor / Profile;
- Groups, invitations, admissions, agreements and spaces;
- Contexts, Content, Blueprints, Reader/Studio, Assets and evidence;
- Submissions and Evaluations;
- Need / Offer, Relationships, Proposals and Contracts;
- Commitments and Fulfillments;
- Planner and temporal/calendar infrastructure;
- Personal Accounting;
- Financial Obligations and Settlements;
- notifications/realtime;
- System Map.

The previous default `planning_baseline` profile intentionally hid most of those routes. The unified branch therefore defaults to `full`.

The newer compact Money workspace is retained independently at `/money`; mature Accounting remains at `/accounting`; Exchange and Vault remain first-class surfaces.

## Historical branch reconciliation status

The major sibling lines have now been reviewed and selectively converged. Historical branches remain useful as implementation evidence, but they are **not** alternate release candidates and must not be wholesale-merged over this branch.

Authoritative reconciliation details are recorded in:

`docs/UNIFIED_FINALIZATION_CONVERGENCE.md`

Current status:

- `codex/planner-readiness-execution-calendar-density`, `codex/personal-money-vault-calendar-tools` and `codex/review-f1-shared-temporal-fabric` are subsumed ancestors;
- `codex/ideal-v1-service-financial-workflow` is semantically reconciled into the current Contract -> Commitment -> Planner -> Fulfillment -> Financial Obligation -> Settlement flow;
- `integration/ideal-v1-temporal-calendar-reconcile` is selectively reconciled, preserving the newer temporal core while restoring missing evidence/execution/calendar behavior and regression tests;
- `release/ideal-v1-rc-7-hardening` and `codex/release-first-publication-hardening` are selectively reconciled for verified-user provisioning, monetary defaults, first-publication gates and compatible presentation hardening;
- `feat/context-ai-assistance-provenance` is restored and extended with authenticated AI Chat and System Manual integration;
- older parallel IET exchange/settlement kernels were capability-audited; useful behaviors were retained in the canonical Treasury/quote/Obligation architecture while duplicate model families and bypass rails were rejected.

The rule for any remaining historical branch is unchanged: admit a behavior only when it improves the current authoritative model without creating a second source of truth.

## Integration order

1. Restore and browser-test the full runtime.
2. Preserve the current Planner / fractal Calendar / Tools rail / Plan types and attention semantics.
3. Reconcile RC-7 presentation/default-provisioning hardening.
4. Reconcile missing temporal/execution/evidence tests and behavior.
5. Reconcile the mature service-financial workflow.
6. Generalize valuation/exchange around instruments and immutable quotes.
7. Add trusted service-unit economics on top of existing Need/Offer -> Proposal -> Contract -> Commitment -> Fulfillment truth.
8. Only then add external quote providers and automated valuation-policy proposals.

No existing authoritative domain truth should be replaced simply to make the UI look unified.

---

# Economic architecture

## Core rule: not everything with a price is money

The system must distinguish:

1. **Monetary unit** — unit of account used by a ledger or obligation, such as USD, EUR, IRR or IET.
2. **Economic instrument** — something whose value may be quoted: BTC, gold ounce, a house-price index, a vehicle class, IET, or a versioned service unit.
3. **Quote** — an immutable observed/executable relationship between two instruments at a time.
4. **Owned asset / offer / contract right** — a specific user's house, vehicle, gold lot, service promise, receivable, etc.
5. **Ledger account** — accounting location that records economic facts in one monetary unit.
6. **Obligation** — an enforceable/economic claim between actors, separate from a quote and separate from bookkeeping.

This prevents a house-price index, Bitcoin and USD from being incorrectly modeled as the same kind of object.

## Generalized valuation tables

### economic_instruments

Suggested columns:

- id / uuid;
- code;
- name;
- kind;
- optional monetary_unit_id;
- settlement_enabled;
- active;
- metadata.

Suggested kinds:

- fiat_currency;
- internal_unit;
- crypto_asset;
- commodity;
- market_index;
- property_index;
- physical_asset_class;
- service_unit.

Examples:

- USD — fiat_currency + linked MonetaryUnit;
- IRR — fiat_currency + linked MonetaryUnit;
- IET — internal_unit + linked MonetaryUnit;
- BTC — crypto_asset;
- XAU_OZ — commodity;
- TEHRAN_HOUSING_INDEX — property_index;
- TORONTO_HOME_PRICE_INDEX — property_index.

A specific house must **not** be represented by a generic property index. A concrete house is an owned/domain asset with valuation evidence that may reference one or more indexes/appraisals.

### quote_sources

Suggested columns:

- id;
- key;
- display name;
- source type: manual / provider / policy;
- trust tier;
- active;
- provider metadata.

Examples:

- system-admin;
- central-bank feed;
- approved exchange feed;
- approved crypto feed;
- property-index provider;
- IET valuation policy.

### market_quotes

Immutable append-only rows:

- uuid;
- base_instrument_id;
- quote_instrument_id;
- exact decimal price;
- optional bid/ask;
- source_id;
- source_reference;
- observed_at;
- effective_at;
- expires_at;
- confidence / quality metadata;
- published_by_user_id when manual;
- raw/provider metadata;
- created_at.

Example semantics:

`BTC / USD = 67,250.15`

means one BTC is valued at 67,250.15 USD at the recorded observation.

`IET / USD = 0.00000001`

preserves the initial IET reference definition.

Historical transactions never re-price themselves when a later quote arrives.

### valuation_snapshots

Any Contract, exchange request, obligation or internal charge that depends on a rate should preserve a snapshot:

- direct quote IDs used;
- conversion path;
- original amount/instrument;
- resulting amount/instrument;
- rounding rule;
- timestamp.

This is preferable to re-running today's conversion against historical facts.

## Cross rates

Prefer direct approved quotes when available.

If no direct quote exists, a conversion service may derive a cross rate through an approved reference path, commonly USD:

`A -> USD -> B`

The system must snapshot the exact component quotes used.

No PHP floating-point arithmetic should be used for financial conversion.

---

# IET architecture

## IET remains the internal settlement unit

IET should remain a real `MonetaryUnit` for internal ledger settlement.

The existing dedicated IET posting guard is a good boundary: ordinary manual Accounting must not mint or manipulate IET.

The current IET-only `iet_valuation_quotes` implementation should become a compatibility layer over the generalized quote system rather than remain the permanent valuation architecture.

## IET value should be policy-driven, versioned and auditable

Do not encode a rule that IET must always appreciate.

A professional policy engine should publish or propose a new quote from versioned inputs and governance rules.

Possible system-wide evidence inputs include:

- completed economic cycles;
- confirmed settlements;
- default/dispute rate;
- active demand versus fulfilled supply;
- IET redemption/deposit liquidity;
- internal paid-flow volume;
- reserve/treasury policy;
- concentration/risk indicators;
- aggregate reputation/quality signals.

Individual reputation should change the economics/trust of that Actor or service offering, not directly mutate the global IET price.

A policy version should preserve:

- formula/configuration;
- input snapshot;
- previous quote;
- proposed quote;
- limits/circuit breakers;
- rationale;
- approver/publisher.

## No guaranteed-growth semantics

The UI and code should never promise that holding IET produces guaranteed appreciation or investment return.

It can be a dynamic internal settlement/reference unit whose published value may move according to transparent policy and market/system conditions.

---

# System accounts

## Do not implement one magical "sea" balance

Use explicit system accounts with narrow meaning.

Recommended system-side account purposes:

- IET issuance / retirement;
- exchange clearing;
- settlement clearing;
- escrow / reserved funds;
- service-advance credit pool;
- fees/revenue where applicable;
- correction/adjustment with privileged audited paths.

User wallets remain user/context ledgers.

System and user postings should share a correlation/economic-event UUID and be posted atomically when one economic event affects multiple ledgers.

The current user-ledger `iet_exchange_funding` equity account is useful as a local accounting mirror, but a professional supply model also needs system-side supply/clearing truth so total issuance, retirement and reserved IET can be audited globally.

## Supply invariant

At any checkpoint the system should be able to explain:

`issued IET - retired IET = circulating + reserved + system-held IET`

without summing mutable "balance" columns.

Balances remain derived from immutable journal lines.

---

# Trusted skills as economic assets

## A skill is not directly a fungible coin

The reusable economic object should be a **versioned service unit offering**.

A service unit can describe:

- provider Actor;
- canonical skill Concept;
- exact task/package definition;
- quantity/unit;
- expected duration;
- scheduling preferences;
- location/remote constraints;
- evidence requirements;
- asking price and quote instrument;
- validity period;
- capacity;
- service terms;
- reputation/evidence snapshot.

Example:

> 1 unit = inspect one machined part against drawing dimensions and publish a signed inspection result; expected 35 minutes; offered at 18 USD-reference / unit.

That service unit can participate in:

`Offer -> Match/Relationship -> Proposal -> Contract -> Commitment -> Planner -> Fulfillment -> Financial Obligation -> Settlement`

The system already contains most of that lifecycle. The service-unit layer should connect it, not replace it.

## Trusted skill / reputation

Reputation should be evidence-derived and scoped.

Useful dimensions may include:

- service/skill Concept;
- completion count;
- accepted/rejected/disputed fulfillment;
- counterparties;
- timeliness;
- quality evaluation;
- evidence strength;
- recency.

Do not collapse all reputation into one universal score.

A high-confidence service unit may qualify for better terms or a larger advance, but the underwriting decision must be explicit and auditable.

---

# Advance IET against a trusted service obligation

The proposed "initial value against obligation to perform a trusted skill" should be modeled as credit/advance, not arbitrary minting.

Suggested flow:

`verified service unit`
-> `advance request`
-> underwriting / policy decision
-> accepted advance agreement
-> IET funded from a defined system credit-pool account
-> provider receives spendable or partially reserved IET
-> future Fulfillment/Settlement repays or closes the advance
-> default/dispute path remains explicit.

The advance must preserve:

- service-unit version;
- valuation snapshot;
- maximum exposure;
- maturity/due rules;
- repayment source;
- collateral/evidence if any;
- policy version;
- approver;
- status/events.

This provides the behavior desired by the project without silently creating value from a self-declared skill.

---

# Need pool and supply pool

Reuse the existing Need/Offer Intent system.

The system can project:

- demand pool by Concept/location/time/value range;
- supply pool by service unit / asset class;
- market-clearing observations;
- completed-cycle prices.

The pool is discovery and evidence.

It must not create Contracts, obligations or transfers automatically.

For a trusted service:

`Need Intent + Service Offer`
-> candidate match
-> Proposal
-> accepted Contract
-> Commitment
-> Planner occurrence(s)
-> accepted Fulfillment
-> Obligation
-> IET Settlement.

This produces the successful-cycle data that can later inform reputation and aggregate IET valuation policy.

---

# Toman / Rial handling

IRR should remain the canonical Iranian fiat monetary unit.

"Toman" can be represented as:

- a display denomination/alias with a factor of 10 IRR; or
- an explicitly configured non-ISO display unit if required.

The system must never silently mix IRR and Toman.

Every quote and amount must carry its unit explicitly.

---

# External provider integration

Provider adapters belong behind a stable interface.

A provider adapter should return normalized observations, not write business truth directly.

Pipeline:

`provider fetch`
-> validate/authenticate
-> normalize instrument pair
-> save raw/provider provenance
-> publish immutable quote observation
-> optional policy/approval
-> make quote eligible for conversion.

Examples later:

- fiat FX;
- crypto;
- gold/commodity;
- property-price indexes.

A property provider should normally publish an index/benchmark. Individual property valuation needs property-specific evidence/appraisal and should not be inferred blindly from an index.

---

# Acceptance gates for this branch

Before expanding the economy, locally prove:

1. full navigation and previously mature modules are reachable;
2. current Planner/Calendar/Tools behavior remains intact;
3. Money, advanced Accounting, Exchange and Vault all work;
4. locale/timezone/calendar presentation remains consistent;
5. existing migrations run from clean database and rollback/reapply;
6. full PHPUnit/PHPStan/Pint/Vite/npm/Composer gates stay green.

Then reconcile the three sibling sources above in small commits with regression tests.

