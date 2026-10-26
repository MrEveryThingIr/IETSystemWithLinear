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
| C4 conversation-first Deal workspace | **Engineering-complete** ([M3-C4-DEAL-WORKSPACE.md](M3-C4-DEAL-WORKSPACE.md)); not visually reviewed in a browser (RTL/mobile/dark) |
| C5 acceptance + hardening | Not started |

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

### C4 delivered
- Start: one message box on the existing `relationships.create?intent=` URL; roles inferred; optional Business/Group speaker; repeated starts reuse the open Deal; blank intro allowed.
- Workspace (`relationships.show`): pinned Need/Offer card, conversation (existing messaging), Terms card with per-party status, what-changed list, Agree / Ask for changes / Decline on the viewed version, propose/revise form, Agreement card; participants, earlier versions, history, full conversation, old forms are under *Details*.
- Tests: `tests/Feature/DealWorkspaceTest.php` (two-account journey incl. stale-agree refusal, reuse + privacy, direct proposal/decline/withdraw, Business publisher + Business responder); the Deal page no longer uses the explanatory workflow-shell (contract test amended on purpose).
- C5 should: full regression, populated MySQL rehearsal (no new migration in C4), browser review in fa/ar RTL + mobile + dark, and a decision on letting all managers of a Business/Group open the Deal page.

### C4 refinement (after owner browser test)
- **Submit error root cause:** the owner's Laragon database had never run the three M3 migrations (`2026_11_13`, `_14`, `_15`), so inserting a `proposal_parties` row failed with "Unknown column business_id" (see `storage/logs/laravel.log`). Tests could not catch it because they always migrate from scratch. **Action for the owner: run `php artisan migrate` on the Laragon database** (additive, rehearsed on populated MySQL). I did not run it on the owner's database.
- **Details auto-collapse root cause:** the whole Deal page carried `wire:poll.8s`; every poll re-rendered and Livewire morphed `<details>` back to the server HTML (closed). Fix: no page-wide poll (the thread polls itself), and the open state of every `<details>` lives in Alpine (`x-data` + `x-bind:open`), so any re-render keeps it. Verified in the browser pane (open 22 s later and after a forced re-render).
- **Terms composer** (`Livewire\Deals\TermsComposer`): starts from the latest negotiated version, or from the pinned Need/Offer (core terms plus its typed fields, private details excluded). Core terms (price, place, dates, quantity, route, repeats) switch on like Composer blocks; fields come from `IntentDetailCatalog` (typed inputs: text, long text, number/amount with unit, yes/no, choice, date) or are added as a custom text detail; each changed field shows "was ...". Schema v1 stays valid: `fields[]` gained optional `type`/`unit`, and a new optional `recurrence {every, unit, count}` supports recurring cycles later. `DealTermsDiff::rows/compare` feed the Terms card and the what-changed list.
- **Conversation** is now the existing `Messages\Thread` component (`embedded`) on the Deal's shared conversation: attachments, voice notes, location, contact cards, replies, reactions, edit/delete, mute, read state, adaptive polling and push. Steps (terms proposed, agreed, ...) are `kind=deal` lines in the same thread; the introduction still waits for consent in `relationships.metadata.intro`.
- **Downstream shape:** the Agreement's Terms are `terms_data` on the accepted ProposalVersion (typed fields, price basis, recurrence) plus the sealed revision; M4 planning, M5 bookkeeping can read them without parsing text.
- **Gaps before C5:** RTL (fa/ar), mobile and dark mode not browser-checked; the intro uses the old "Participant username" label; non-owner link to the exact origin version; other Business/Group managers cannot open the Deal page; no multi-currency unit picker; Terms diff compares text values (not typed ranges).
- **Tests:** `DealWorkspaceTest` (8): journey with shared thread, composer typed fields and diff, all-types submit, details state, rich thread; full suite 1202 tests, only the 4 pre-existing Planner failures.

### C5 (conversation, Agreement and workspace) - what changed
- **One conversation.** `Livewire\Messages\Thread` is the only composer. `/contexts/{ctx}/conversation` redirects to the Deal for Relationship contexts and otherwise embeds the same Thread; `ThreadFor::conversation()` is the one place a Context's `main` thread is opened. Evidence references render in the Thread; old per-page composer removed.
- **Voice-note root cause (browser-proven).** The Thread's Alpine root carried `version` inside its `x-data` expression, so every arriving message rebuilt the component and dropped a recording in progress (recorder lost, send did nothing, no feedback). The expression is now constant (`x-data="messageThread"`, data-* attributes read once); the upload and send path also reports failure. Verified with a real MediaRecorder blob and an incoming message mid-recording: recording survives, voice message sends.
- **Images shown as files / accordions resetting.** Both came from the second chat surface (`contexts/conversation`) with a page-wide `wire:poll` and file-card rendering; that surface is gone. The Thread renders images, audio, video, files and evidence the same everywhere.
- **Agreement composer** (`Livewire\Deals\AgreementComposer`): title, summary, inferred parties, ordered terms (move up/down, remove, edit), `+ Add term` from a categorized library (subject, duties, money, time and place, completion, ending, other). Typed terms: price, payment rule (none | on acceptance | after confirmed delivery | per period), dates, place, quantity, route, repeats (every/period/count), completion rule (confirmed by receiver | both | none), typed details from the M2 field library. Narrative clauses (scope, deliverables, duties, evidence, cancellation, disputes, confidentiality, special, custom) are text and never become automation. Drafts are saved privately in `relationships.metadata.agreement_draft`; **Send for review** creates the immutable ProposalVersion.
- **Schema v1 stays valid** (additive optional keys): `title`, `summary`, `clauses[]`, `payment`, `completion`, `order`, `fields[].type/unit`, `recurrence`.
- **Who drafts.** Default: the originating post's owner or anyone authorized for the publishing Business/Group (`AgreementDrafter`). The owner side can delegate to an active participant and take it back; others ask for an agreement (`requestAgreement`) or ask for changes. Enforced in `ProposeDealTerms` (403 otherwise).
- **Document.** `AgreementDocument::sections()` builds the numbered document from stored terms; the final summary shows signers (signer, principal, time), legal-record links, and earlier versions. Accepting a new version never inherits acceptances.
- **Workspace.** Conversation | Agreement | Work | Money tabs (`dealTabs`, state in the browser). Work and Money only describe the agreed rules and say plainly that nothing is tracked yet; no balances or statuses are invented.
- **Notifications.** Delivery depended on a queue worker (`QUEUE_CONNECTION=database`, queue `notifications`) that is not running locally, so invitations never reached the bell. `NotificationOutboxDispatcher::deliverPendingFor()` now delivers a person's own pending rows inline when they open any page (queue stays the normal path). The post owner sees "Conversations about this post" on the intent page.
- **Planner suite.** `PlannerRemindersTest` (3): Windows PHP cannot find `openssl.cnf`, so `openssl_pkey_new` fails; passes with `OPENSSL_CONF=C:/laragon/bin/php/<php>/extras/ssl/openssl.cnf` (machine setting, not code). `PlannerKernelTest` (1): a fixed 2026-09-25 rule start with no frozen clock; fixed by freezing time. Not related to M3 code.

### Connection points left for later milestones (not implemented)
- **M4 Work:** read `terms_data.completion`, `recurrence`, `time`, `fields` of the *accepted* ProposalVersion (via the Contract's `source_proposal_version_id`). One accepted version covers many periods; each period's performance and its settlement are separate records. A Planner completion must never count as delivery proof by itself.
- **M5 Money:** read `terms_data.price` and `payment` (mode, period). Settlement rows reference the accepted version; nothing in a Deal creates a ledger entry today.
- **M6/M7/M8:** unchanged.

### Real-world arrangements covered by the schema
Single exchange (price, payment on acceptance or after confirmed delivery, completion by receiver); repair with payment after confirmed delivery; recurring collaboration (recurrence, per-period payment); nonmonetary collaboration (no price/payment terms, narrative clauses); Business acting through an authorized manager (principals from C3b, drafter from the Business authority).

### C5 evidence
- `DealWorkspaceTest` (12) and `DealNotificationsTest` (1) cover: two-account journey with exact-version integrity and signers, saved draft vs agreement, requests, delegation, numbered document, typed validation and prose-stays-prose, reuse of open conversation, privacy, withdrawal, Business parties, one thread for legacy links, images/files/voice in the shared Thread, the voice root cause, tab/details stability, legacy links, inline notification delivery.
- Browser (disposable DB, since dropped): login, start a conversation, Details stays open, composer, saved draft, send, legacy URL redirect, voice root-cause reproduction and fix, final Agreement summary, Money/Work text, mobile 375px dark (no horizontal scroll), Persian RTL. The in-app browser does not fire `requestAnimationFrame`, which stalls Alpine `x-show`; checks used a rAF shim. Not checked in a real desktop browser: voice with a physical microphone, push delivery.
- `public/build` is git-ignored: run `npm run build` after pulling.

### C5 final consolidation (after owner browser test)
- **Voice (root cause, reproduced in a real Chrome 155 with a fake microphone driven over DevTools).** Pressing *Send* removed the `<template x-if="recording">` subtree; the upload had been started through that button's `$wire`, so Livewire dropped it and no request was ever sent (no error, no network). Direct JS calls worked, which is why earlier simulated tests passed. Fix: the thread keeps one root-bound `wire`, shows an uploading state, and reports any failure (stage, MIME, size, UA) to the person and to `laravel.log` (`messages.voice_failed`); refused files show the failure instead of vanishing. Before/after in the same browser: voice messages in the thread stayed at 1 (click) vs rose 1 -> 2 -> 3 per run after the fix. Not reproduced on the owner's own Chrome profile (no connected browser); physical-microphone hardware not tested. `public/build` is git-ignored: run `npm run build`.
- **One Deal home.** `/proposals/{id}` redirects (after the same authorization) to `/relationships/{id}?tab=agreement&v=N` when the Proposal belongs to a Deal; a standalone legacy Proposal keeps its page. `/contracts/{id}` stays as the legal-record view with *Back to the Deal* and *Printable Agreement*. `DealLinks` is the single place that maps Proposal/Contract to the workspace (notifications use it). `?tab=` and `?v=` deep-link the Agreement tab and expand that earlier version; `?resume=1` reopens the saved draft.
- **Printable Agreement** (`/relationships/{id}/agreement/print`, participants only): exists only for an accepted Proposal with its Contract (otherwise 404); title, summary, parties with signer and time, numbered terms (typed and narrative), version history, accepted-version fingerprint, origin post version, effective date. No chat content.
- **Composer.** Currency is a select (IRT, IRR, USD, EUR, GBP, AED, TRY, CNY; any 3-5 letter code from other writers still validates). Price unit is a select (total, item, hour, day, week, month, project, milestone, session, kilometre) or a custom text kept as written. Several clauses of one kind sit together under one numbered heading (6, 6.1, 6.2...) in the composer, the Agreement and the print. Dates stay civil dates in the profile calendar; optional daily working hours (HH:MM both or none, from != to) require a time zone (IANA), stored in `terms_data.time`. Typed price/time/recurrence data stays separate from narrative clauses.
- **Drafts.** Intent drafts: My Items > Drafts lists them with *Resume editing* into the Composer. Agreement drafts: Deals shows "Agreement drafts (N)" with *Resume editing* (opens the Agreement tab with the composer open), and the Agreement tab shows a Draft badge. Templates (saved structures) are unrelated records and are not mixed in.
- **Navigation.** Market = the directory (`intents.index`); the three-column `/needs-offers` hub is retired and redirects there (permission check kept). My Items: View / Edit / Conversations (n) / optional matches, drafts resume. Directory: own cards lead with View and Edit; someone else's post leads with starting a conversation. Owner post page lists related conversations. Deals page Back goes to Deals; the print page goes back to the Agreement tab.
- **Planner baseline.** The 3 `PlannerRemindersTest` failures are the Windows OpenSSL config (`OPENSSL_CONF`), not code: `openssl_pkey_new` fails with "No such process"; the same tests pass with `OPENSSL_CONF=C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/extras/ssl/openssl.cnf`. `PlannerKernelTest` was a date time-bomb and is fixed.
- **Evidence.** `DealConsolidationTest` (7) and `DealWorkspaceTest` (13). Real-Chrome screenshots (disposable DB, dropped): printable Agreement desktop and Persian RTL mobile, composer with currency/unit selects, Deal workspace. The print page is white by design (no dark mode).
- **Still open before M4:** Business/Group managers who are not Deal participants cannot open the Deal page; composer start card stays visible while the composer is open; Work/Money remain read-only connection points.
