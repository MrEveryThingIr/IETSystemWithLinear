# M3 handoff — Deals and Agreed Terms

Branch `feat/m3-deals-terms` (base `integration/v2` `a90cb92d`). Design authority: [M3-C1-ARCHITECTURE.md](M3-C1-ARCHITECTURE.md). Plan: [M3-DEALS-TERMS-PLAN.md](M3-DEALS-TERMS-PLAN.md).
Rules: one agent per branch; additive reversible migrations; tests on in-memory SQLite or a disposable DB only (never the Laragon database); no merge/deploy without the owner.

## Status

| CP | State |
|---|---|
| C1 audit + architecture + contracts | **Done** (docs + `tests/Feature/M3DealAuthorityBaselineTest.php`, 3 passing) |
| C2–C5 | Not started |

## C2–C5 execution plan (Claude, this branch, in order)

- **C2 — Structured Terms.** Migration `proposal_versions.terms_data` + `terms_hash`; `ProposeTermsVersion` accepts/validates schema v1 and renders the sealed text from it; Deal page shows prefilled Terms from the pinned intent version (uses T-A) and the change summary between versions (uses T-B). Tests: I1, I10, I11.
- **C3 — One agreement step + authority.** `expectedVersion` on `RespondToProposal` (I2); `FinalizeProposalAgreement` creating Contract + derived acceptances in the same transaction (migration `contract_acceptances.source_proposal_decision_id`); `business_id/group_id` on both party tables; policies switch to `PartyAuthority`. Tests: I3–I9 (T-C supplies the race tests).
- **C4 — Natural Deal UX.** One Deal page (Terms, who agreed, what is open, what happens next); respond from a published Need/Offer as self/Business/Group; `/proposals/{id}` and `/contracts/{id}` link/redirect to it; four languages, RTL, mobile, dark. No new models.
- **C5 — Acceptance and hardening.** Scenarios S1–S6, regression suites (`CanonicalDealPipelineTest`, `Proposal*`, `Contract*`, `Relationship*`), MariaDB/MySQL rehearsal on a disposable DB, handoff + tracker update.

## Codex assignments (independent files; no shared code ownership)

Common rules: branch from `feat/m3-deals-terms` at the commit named in the final C1 note; do **not** edit existing files, migrations, policies, lang files or this docs folder; add only the files listed; open no PR to `integration/v2`; report back with the diff and test output. Schema v1 in C1 §2.1 is the contract. Run only your own test file.

### T-A — Pinned Need/Offer version → proposed Terms
- Branch `codex/m3-terms-from-intent`. Owns: `app/Support/Deals/DealTermsFromIntent.php`, `tests/Unit/Deals/DealTermsFromIntentTest.php` (or Feature if DB needed).
- Contract: `build(IntentVersion $version, ?User $viewer): array` returning `terms_data` v1. Reuse `IntentPresentation::build($version->snapshot, $viewer, …)` for the privacy-safe reading (title→notes seed, `price/basis`, `location`→`place`, `quantity/unit`, `starts_on/ends_on`→`time`, `route`, `topic`/category, `details`→`fields`); fill `origin` (intent uuid, version id, kind); `effective.mode = on_acceptance`. Decimal strings only; omit empty keys; deterministic key order.
- Tests: Offer and Need fixtures map price/place/time/quantity/route/category; private detail never appears for a non-owner viewer; Persian-calendar viewer does not change stored values; empty intent yields only `schema` + `origin`; result is stable (same input ⇒ same JSON).

### T-B — Terms version comparison
- Branch `codex/m3-terms-diff`. Owns: `app/Support/Deals/DealTermsDiff.php`, `tests/Unit/Deals/DealTermsDiffTest.php`.
- Contract: `compare(array $from, array $to): array{rows, summary, unchanged}` (shape in C1 §3.2). Pure; flattens nested keys to dotted paths (`price.min`), `fields` compared by `key`; ignores `schema`/`origin`; labels via `__('deals.terms.'.$path)` falling back to the path (lang strings are added by C2); summary is a short readable sentence built from row counts and first changed labels; order is stable (price, place, time, quantity, route, category, fields, effective, notes).
- Tests: added/changed/removed per key; identical ⇒ no rows and "no changes"; field list reorder is not a change; decimal strings compare as strings (no float coercion); null `from` (first version) ⇒ all rows added.

### T-C — Concurrency scenarios (tests only, report failures)
- Branch `codex/m3-concurrency-tests`. Owns: `tests/Feature/Deals/ProposalRaceTest.php`.
- Targets existing actions only: `RespondToProposal`, `ProposeTermsVersion`, `CancelProposal`, `CreateContractFromProposal`, `AcceptContractVersion`. SQLite in-memory cannot run true parallel transactions, so use deterministic interleavings (repeat calls, stale-state calls after the competing action committed) and, if `DB_CONNECTION=mysql` points at a *disposable* database, an optional real-parallel case that is skipped otherwise.
- Scenarios: both parties accept ⇒ one Accepted transition and one event; accept after cancel ⇒ 422, no Contract; accept after new version (expect today's behavior = decision on latest; mark the stale-version case `markTestIncomplete('I2 lands in C3')`); reject after accepted ⇒ 422; double `CreateContractFromProposal` ⇒ second fails, exactly one Contract; double `AcceptContractVersion` by one party ⇒ one acceptance; two parties accept ContractVersion ⇒ one `VersionAccepted` event and one activation.
- Must not change any app code; real failures are reported, not fixed.

### Ownership matrix

| Area | Claude | T-A | T-B | T-C |
|---|---|---|---|---|
| `app/Support/Deals/DealTermsFromIntent.php` | consume | **own** | – | – |
| `app/Support/Deals/DealTermsDiff.php` | consume | – | **own** | – |
| `tests/Feature/Deals/ProposalRaceTest.php` | – | – | – | **own** |
| Migrations, actions, policies, Livewire, lang | **own** | never | never | never |

Merge order for integration: T-A, T-B, T-C branches are merged into `feat/m3-deals-terms` by the owner/Claude after review (no conflicts expected: disjoint files).
