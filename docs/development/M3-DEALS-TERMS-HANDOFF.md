# M3 handoff — Deals and Agreed Terms

Branch `feat/m3-deals-terms` (base `integration/v2` `a90cb92d`). Design authority: [M3-C1-ARCHITECTURE.md](M3-C1-ARCHITECTURE.md). Plan: [M3-DEALS-TERMS-PLAN.md](M3-DEALS-TERMS-PLAN.md).
Rules: one agent per branch; additive reversible migrations; tests on in-memory SQLite or a disposable DB only (never the Laragon database); no merge/deploy without the owner.

## Status

| CP | State |
|---|---|
| C1 audit + architecture + contracts | **Done** (docs + `tests/Feature/M3DealAuthorityBaselineTest.php`, 3 passing) |
| C2 structured Terms + G1 | **Engineering-complete** (see below) |
| C3a single Agree (structured Terms) | **Engineering-complete** |
| C3b Business/Group principals | **Engineering-complete** (see below) |
| C4–C5 | Not started |

### C2 delivered
- `proposal_versions.terms_data` (JSON) + `terms_hash` (sha256 of canonical JSON), migration `2026_11_13_000000`; written at creation only, immutable by the model guard; `ProposalVersion::termsIntegrityOk()`.
- `App\Support\Deals\DealTerms`: `normalize` (schema v1, unknown keys rejected, decimals as strings, fields sorted), `canonicalJson`, `hash`, `render` (locale text sealed into the existing content revision, so ContractVersion keeps the same revision). `deals.terms.*` strings in en/ar/fa/zh_CN.
- `ProposeTermsVersion` / `CreateProposal` accept `?array $termsData`; origin must equal the Deal's pinned `originating_intent_version_id`.
- **G1 fixed:** `RespondToProposal(..., ?int $expectedVersion)` checks the version inside the locked transaction (409 on mismatch); structured versions *require* it (422 if omitted). Only caller audited: `Livewire\Proposals\Show` (always sends the rendered version; shows a message on 409). Pre-M3 free-text versions keep the old null behaviour. C3's single-Agree must call with the version it rendered.
- **C3a:** when the last required party accepts a *structured* version, `RespondToProposal` calls `CreateContractFromProposal(..., deriveAcceptances: true)` in the same transaction: Contract + ContractVersion on the same sealed revision, one `ContractAcceptance` per accepting party with `source_proposal_decision_id` (signer and time copied from the decision), then the existing idempotent finalize/activate. Effective = `terms.effective` (`at` is clamped to now if already past). Free-text Proposals and pre-M3 accepted ones keep the explicit Contract form. Accepted Terms create no commitments (tested). Migration `2026_11_14_000000`. No UI authors structured Terms yet (C4).
- Party identity corrected in C1 doc §2.4a (principal key, signer ≠ principal); implemented in C3.
- Not duplicated for Codex: `DealTermsFromIntent` (T-A) and `DealTermsDiff` (T-B) are still unassigned/unstarted; they depend only on `DealTerms::normalize` output (T-A should return a value that passes `DealTerms::normalize`).

### C3b delivered (principals)
- Migration `2026_11_15_000000`: `business_id`, `group_id`, `principal_key` on `proposal_parties` and `contract_version_parties`; legacy rows backfilled `actor:<actor_id>`; `unique(scope, principal_key)` replaces `unique(scope, actor_id)` (plain index kept on `actor_id`). `actor_id` is now only the representative/contact.
- `App\Models\Concerns\IsDealParty` (both party models): `principal(): Party`, sets `principal_key` on create, refuses Business+Group together.
- `App\Support\Deals\DealParties`: `representedBy` (current `PartyAuthority::canActFor` on the principal), `sole` (fresh read via `RequestMemo::flush()`; 403 if none, 422 if the person could sign for 2 parties), `assertSingleSide` (creation guard).
- `CreateProposal(..., ?Party $creatorParty)`; invitee spec accepts `party` (representative `actor` must be able to act for it). `ProposeTermsVersion`, `RespondToProposal`, `AcceptContractVersion` resolve the party by principal authority **inside the locked transaction, before any state-dependent answer** (a revoked or outside user gets a bare 403). `CreateContractVersion`/`CreateContractFromProposal`/`ProposeContractAmendment` carry the principal; derived acceptances keep the real signer (`accepted_by_user_id`) and the decision link.
- `ProposalPolicy`/`ContractPolicy` use principal authority; `cancel` = creator or anyone who may act for the creator's party. `Livewire\Proposals\Show` finds the viewer's party by authority.
- Tests: `tests/Feature/DealPartyPrincipalsTest.php` (11): owner/manager/Group editor sign, two managers → one acceptance, both-sides refusal at creation and response, outsiders/non-managers/privacy (no title/terms in 403), revoked authority with a warm memo, duplicate principals (app + DB unique, same representative for two principals), Business+Group refused, explicit Contract acceptance by principal, legacy Actor-only compatibility. Baseline G3 test flipped.
- MySQL 8.4 rehearsal on disposable `c3b_rehearsal` (dropped): full migrate, rollback with rows, re-migrate backfills `actor:<id>`, duplicate principal rejected with 1062.

### C3b known limits (carry into C4/C5)
- Platform admins pass `BusinessAccess::canManage`, so they can sign for a Business (shared M2 rule, not changed).
- Business/Group rows still show the *representative's* name in `proposals/show`, and Commitments/service terms still key on `actor_id`; both are C4/M4 work.
- `down()` re-adds `unique(scope, actor_id)`: it fails if two principals share a representative, and Business/Group identity is lost on rollback (rows revert to the representative's `actor:`).
- DB does not forbid Business+Group together (model guard only); the unique key still protects identity.
- Two different principals represented by one person are blocked in code (both sides), not by a DB constraint.
- Amendments need the stored representative's account to stay active+verified (existing party validation).

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

### C3b evidence
- Full suite (sqlite in-memory): 1194 tests; the only failures after the fix are 4 `PlannerKernelTest`/`PlannerRemindersTest` cases (Windows OpenSSL key / date) that fail identically with C3b stashed (pre-existing, unrelated).
- C3a migration `down()` failed on sqlite (`dropConstrainedForeignId` with a unique index), which broke `RegistrationTest`/`ComposerMigrationTest` rollbacks; fixed (drop FK, unique, column) and those pass.
- Deal suites (`DealPartyPrincipals`, `ProposalNegotiationKernel`, `ProposalExperience`, `ContractKernel`, `ContractExperience`, `CanonicalDealPipeline`, `StructuredDealTerms`, `M3DealAuthorityBaseline`): 43/43.

### C4 starting point
Deal page: show party display name by principal (`business`/`group` name, representative as "signed by"), let the responder pick "as myself / as Business / as Group" from `PartyAuthority::partiesFor` and pass `creatorParty` / invitee `party`; prefill the counterparty from the intent's `publisher_business_id/group_id`; surface the 403/409/422 messages from `DealParties` and `RespondToProposal` in the four languages.
