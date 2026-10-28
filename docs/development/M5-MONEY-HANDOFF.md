# M5 handoff — Money inside the Deal

Branch `feat/m5-deal-money`, cut from `integration/v2` at the M4 merge (`8d7d838c`). Design: [M5-MONEY-DESIGN.md](M5-MONEY-DESIGN.md). Mapping: [M4-M5-AGREEMENT-OPERATIONS-MAPPING.md](M4-M5-AGREEMENT-OPERATIONS-MAPPING.md). Previous slice: [M4-WORK-HANDOFF.md](M4-WORK-HANDOFF.md).
Kept: no parallel financial system, no migration, no real money moves, no confirmation on anyone's behalf.

## What two people do (Deal › Money)

1. **Work is confirmed** (M4). Money exists only for Agreements whose accepted terms name a payment rule (`on_acceptance`, `after_confirmed_delivery`, `per_period`). Otherwise the tab says there is no money in this Deal and nothing is recorded.
2. **Eligible amount (computed, nothing written).** For each confirmed piece of work the tab shows what the accepted terms entitle the doer to, read from the Agreement version *the work was set up under*.
3. **Ask for payment.** The doer ticks the work they want paid (one cycle, several, or all) and presses *Ask for payment*. This records a Bookkeeping *obligation* in the Deal's shared book. It does **not** change what is owed.
4. **Report a payment.** The payer enters how much they really paid against each request (partial, or across several cycles), optionally attaches a receipt and presses *Report payment*. That records a Bookkeeping *payment* with allocations. It is *reported*, never *received*.
5. **Confirm receipt.** The recipient presses *I received it* (counts) or *Not received / wrong amount* with a reason (kept in history, counts for nothing). Either side may later dispute a confirmed entry.
6. **Settled** per piece of work when confirmed allocations equal its amount. Money never completes the Agreement.

The summary shows: Owed under the Agreement · Not yet asked for · Asked for · Reported paid · Confirmed received · Still to pay, plus how many items need attention. *Bookkeeping* (collapsed) opens the shared book.

## Which amount counts (never guessed)

`DealMoney::items` derives an amount only when sure: Toman, `price.min == price.max`, and either the price basis equals the duty's own unit (hour, day, item…) → price × confirmed quantity, or the basis is `total` and the version has exactly one duty → pro rata to the quantity confirmed. `on_acceptance` makes the duty payable once it exists. Anything else (a range, several duties on one total, another unit, no price): **no amount is invented**; the doer types it, it is only *Asked for*, and it counts as owed after the payer agrees (*Agree to this amount*). A payment cannot be reported against an amount nobody has established.
The duty form now offers the price units as suggestions so "per day" prices and "day" duties match.

## Reused (no new money code)
`CreateLedger`, `AddLedgerParty`, `RecordSharedEntry` (obligation, payment, allocations, idempotency, ledger lock), `DecideOnEntry`, `RaiseDispute`, `WithdrawEntry`, `AttachEvidence`, `BalanceCalculator`, `EntrySource`, `ConfirmationRules`, `TransactionPolicy`. The legacy `ProposeWorkBookkeepingEntry` source type (`fulfillment`) is reused, so one piece of work never gets two live entries even across the old and new paths.

## New code (small)
- `App\Support\Deals\DealMoney`: read model (eligible items, per-item state, summary). Holds nothing.
- `App\Actions\Deals\ManageDealMoney`: `ledger`, `request`, `report`, `approve`, `reject`, `dispute`, `takeBack`, each delegating to the actions above. One shared Bookkeeping book per Deal, created with the other person on first use, its uuid in `relationships.metadata.money_ledger`.
- `App\Livewire\Deals\MoneyPanel` + `deals/money-panel.blade.php`; strings `deals.money.*` in en/fa/ar/zh_CN; the Money tab in `relationships/show.blade.php` now hosts the panel.

## Guard rails and where they are tested (`tests/Feature/DealMoneyTest.php`, 7 tests, 86 assertions)
| Rule | Test |
|---|---|
| Nothing owed before work is confirmed; asking changes neither owed nor paid; only the doer asks; asking twice is one entry | one-time sale |
| Reported ≠ received; the payer cannot confirm their own report; a double submit (same form token) is one payment | one-time sale |
| Recipient confirms → settled; entries are not duplicated | one-time sale, panel test |
| Recurring: ask for 2 of 4 days; instalments covering several cycles; cannot exceed what each request leaves open; reported-but-unconfirmed money cannot be reused | recurring test |
| Rejected payment keeps its history and settles nothing; a dispute stops "settled"; strangers refused | disputed test |
| Terms give no amount → nothing owed, an amount must be typed, payment refused until the payer agrees | typed-amount test |
| No payment term → no money, requests refused, nothing in Bookkeeping | nonmonetary test |
| Amendment changes future work, never what is owed for earlier work | amendment test |
| Two accounts complete a sale through the Money tab UI; stranger cannot open the Deal | panel test |

## Evidence
- **Full suite** (Windows OpenSSL config set): **1235 tests, 1235 passed**, 14,705 assertions.
- **Focused:** `DealMoneyTest` 7 tests / 86 assertions; with `DealWorkspaceTest`, `LocalizationParityTest`, `TemporalPresentationConsistencyTest` 34 tests / 1,298 assertions.
- **Static / build:** Pint clean; PHPStan 0 errors; `npm run build` clean. No migration, so no database rehearsal was needed.
- **Browser (disposable SQLite, throwaway server, Laragon database untouched):** Alice (English) asked for payment of a 3,000,000 Toman delivery; Bob (Persian RTL) reported 1,000,000, Alice confirmed receipt ("Partly paid", 2,000,000 still to pay); Bob reported 2,000,000, Alice confirmed ("Settled", 0 to pay). Mobile 375px dark mode checked: no horizontal scroll. Fixes found by the walkthrough: Take back hidden once money moved, no localized default payment description stored, amount box cleared after reporting, translated sentences follow page direction.
- **Owner-reported MySQL bug, fixed:** reporting a payment failed on the owner's MySQL with `Data too long for column 'idempotency_key'` (the column holds 80 characters; the payment key was 82 and SQLite ignores column length). The key is now `deal-pay:` + 40 hex characters of a SHA-256 of (deal, form token) = 49; the test asserts no key exceeds 80. `DealMoneyTest` and `DealWorkTest` were then run against a disposable MySQL database (12 tests, 185 assertions, passed; database dropped). Request keys were already within the limit (about 58).
- **Not done:** a recurring-work and a disputed-payment walkthrough in the browser (both covered by automated tests); the receipt upload in the browser; a parallel (truly concurrent) MySQL run.

## Known limits (carry forward)
- Book people are **Actors** (the party's stored representative), as in M4. A Business/Group manager who is not that representative cannot record in the Deal's book.
- `per_period` is treated like per confirmed work: each confirmed report is payable; no bundling by week/month yet, and there are no due dates (the Agreement carries only a payment *note*).
- Real concurrency (parallel requests) is covered by Bookkeeping's ledger lock, the source/idempotency keys and deterministic interleavings in tests; SQLite cannot run true parallel transactions, and no parallel MySQL run was made.
- Reciprocal duties with different prices on one Agreement need typed amounts (a single `price` term cannot be split).
- A payer who disagrees with an Agreement-derived request can only record *Disagree* (shown as needing attention); the amount stays owed under the Agreement and the doer must ask again.
- Adjustments (correcting an amount) and refunds exist in Bookkeeping but are not offered in the Deal's Money tab.
- Receipt attachments use `AttachEvidence` (book people only, up to 10 files).

## Consolidation pass (four-tab refinement, after the owner's browser testing)

What changed, in the owner's own terms (Agreement → Work → Money, one conversation):

- **Agreement clarity.** One price by default (a range only when chosen); Toman amounts accepted in any digits/separators and shown as "3,000,000 Toman / square metre" (composer, document, sealed text); area/length/weight units; each duty/deliverable/evidence/**materials** clause can name its **responsible party** (shown in the composer, the document and the print page); `DealTermsWarnings` points out, without changing or blocking anything, price ranges, attendance days that do not earn measured-work wages, unit mismatches, unassigned duties/materials and conflicting payment/confirmation rules. Accepted versions stay immutable.
- **Validation.** Every Agreement, Work and Money message is localized and appears under its own field (`DealDraftValidator`, `DealErrors`, `deals.errors.*`); the English tail that mixed languages is gone.
- **Tasks (Work).** The person who **receives** the work defines a task: **title, description, optional images/video/voice**. The other party is the doer; the agreed quantity/unit come from the Agreement (no fields). A task is a Commitment, so reports, inspections and money are unchanged. Scheduling reuses the **Planner**: link one of the Deal's existing plans (`BindExistingPlan`); there is no bespoke schedule form any more. Older pending proposals remain confirmable.
- **Reports.** The doer keeps a **work log**: date, start and end time, what happened (problems, why work stopped, what was missing), a *rough* progress figure, evidence; stored as immutable `CommitmentEvent`s (`work_logged`), no new table. Exact quantities are agreed at an **inspection** (milestone or end): the doer says how much is ready, the receiver confirms **all, part (partly accepted), asks or refuses**; only the confirmed quantity counts (migration `2026_11_18`: `fulfillment_reviews.accepted_quantity`, null = everything).
- **Money.** `reported (inspection)`, `estimated (rough log figure, a guide, never owed)`, `earned (confirmed quantity at the accepted price)`, `payable`, `outstanding due`, `not yet due`, `requested`, `reported paid`, `received`, `settled`; earned = received + still due + not yet due (tested). Due day comes from the Agreement version's rule (on acceptance; day of confirmation; end of the calendar week Mon-Sun or month of confirmation); requests only once due. Each item shows version, work, due rule and allocations; the book is named after the Deal; advance/instalment/final payments are separate events.
- **One conversation.** Work and Money steps are labelled, colour-coded **cards** in the same Deal conversation (linking to their tab), with chips All / Messages / Agreement / Work / Money.

### Evidence for this pass
- New suites: `DealAgreementValidationTest`, `DealAgreementClarityTest`, `DealMeasuredWorkTest`, `DealTaskTest`, `DealConstructionScenarioTest` (builder paid per square metre: irregular daily log, partial acceptance, rejected piece, weekly due, instalment + final, request refused before due, earned = received + due + not yet due), `DealConversationAreasTest`, `DealPerformanceBudgetTest` (Work screen grows by about one query per report, Money screen is flat).
- MySQL 8.4 rehearsal of `2026_11_18` (`tools/m5-mysql-rehearsal.php`, disposable database, dropped): historical review rows unchanged, rollback keeps rows, re-apply works.

### Gate results for this pass
- Full suite (SQLite, Windows OpenSSL setting): **1248 / 1248 passed**, 14,968 assertions. Pint clean, PHPStan 0 errors, `npm run build` clean.
- Money, work, task, construction, conversation and validation suites on a **disposable MySQL 8.4 database** (dropped): 22 tests, 21 passed on the first run; the one failure was a test comparing JSON keys in a fixed order (MySQL reorders JSON keys), made order-independent and re-run green. Idempotency keys are asserted to be at most 80 characters.

### Not done / open (owner to decide)
- **Payments on account against the rough estimate** are not offered: the estimate is shown as a guide, and requests exist only for confirmed (inspected) work. If wanted, the safest route is a typed "advance" request the payer must agree, reconciled at the inspection.
- Recording a **voice note** inside the task form uses the phone's file picker (audio/video capture); the Conversation's own recorder is not embedded.
- Slow paths were audited at query level only (no N+1 left; evidence uploads are still processed inside the request: hashing, storing, MP4 faststart). Real-device timings and a progress bar for very large videos are not done; no queue/async was added (the shared host has no worker).
- Several tasks under one measured agreement each carry the agreement quantity as their cap; the cap is not shared between them.
- A weekly/monthly due day follows the confirmation date, not the working days (no attendance-to-wage inference). Week boundaries are Monday-Sunday.
- The legacy Commitment page is still reachable for planning (`commitments.show` redirects Deal work to the Work tab).

## Follow-up after the owner's second browser test: money on account, flexible progress

- **Traditional flow without inspections.** Money now exists for any priced Agreement (no payment rule needed). The doer can **ask for money on account** (a note both see, not a debt); the payer **pays on account** (advance / instalment / final, optional receipt, may fulfil an ask); the recipient **confirms receiving it**. These are ordinary Bookkeeping payments with no work attached; they offset what is earned (due first) and never count twice. The Money tab leads with four figures (rough work so far, earned by inspection, paid on account, received) and one position line: **Roughly owed so far** (or **Paid ahead**), explained as a guide; every other figure is under "More figures". Inspections remain for exact settlement at a milestone or the end.
- **Progress is not a rigid number.** A task now takes the Agreement's own measure: a price per unit with no total agreed leaves the quantity **open-ended** (no meaningless cap); a whole-job task is reported as a **share of the job (%)**; a measured task shows its own unit and what is still open. The form label, hint and error name the unit and what is open (e.g. "more than what is still open for this task (120 square metre)") in all four languages.
- Tests: `DealOnAccountTest` (father/Majid scenario: logs with rough figures, ask, partial on-account payment, confirmation, paid ahead, later inspection offsetting what was paid, identity earned = received + credit applied + due + not yet due; whole-job share). Re-run on a disposable MySQL database with the construction scenario (4 tests, green; dropped). Focused regression (59 tests) green except the one assertion updated for the clearer error text.
- Open: the legacy per-item "request / agree / report against requested work" flow still exists for inspected work and is unchanged; allocating on-account money to specific pieces of work is not offered (it offsets at summary level), so Bookkeeping's own per-obligation view shows on-account payments as unallocated advances.

## Accounting correctness checkpoint — 2026-10-29: blocked, not fixed

The owner-reported reversed-debt bug is reproduced on disposable MySQL 8.4.3. A confirmed 1,000,000 Toman advance becomes a reverse debt; accepting a later 600,000 obligation leaves it fully unpaid in Bookkeeping while the pair view shows a 400,000 reverse debt. Returning 400,000 makes the pair net zero but leaves the obligation unpaid. The disposable database was dropped.

Read-only aggregate inspection of owner records found one confirmed unallocated on-account payment of 1,000,000 and one void on-account payment of 1,000,000. No owner database writes or migrations occurred. Original transactions, evidence, confirmations and audit history remain intact.

**M5 is not engineering-complete.** The shared accounting domain requires explicit credit/application/return semantics and consistent allocation lifecycle handling before the three-figure Money UI or estimation work can proceed. Implementation stopped under the owner's instruction to report if accounting invariants require a larger model change. This is not a completed correctness fix. See [the checkpoint report](M5-ACCOUNTING-CHECKPOINT.md) for source diagnosis, reproduction results, required invariants and a proposed reconciliation procedure that has not been executed. Prior green tests above predate these newly required invariants.

## Approved bounded accounting correction — 2026-10-29

The owner accepted the diagnosis and authorized a correction of the shared Bookkeeping domain. Implementation: [financial invariants, lifecycle, compatibility and rollback](M5-ACCOUNTING-CORRECTION.md).

- `CreditAccounting` is now the shared source of effective applications, accepted obligation debt, available advances and identified returns. Obligation progress, directed pairs, party balances, Deal ledger figures and payment history use it. Confirmed credit applies only in the same payer/payee direction and never becomes an automatic reverse debt. Noncash settlement is kept separate from received cash.
- Original transactions and allocation instructions remain immutable. New entries opt into deterministic residual credit application; effective maps are appended to the audit log for entry changes. Historical credits are not backfilled or automatically applied. Refunds require an identified source payment, reason and unused/applied purpose; applied returns name the obligation they reopen. Return capacity and competing decreases are revalidated when confirmed.
- Ledger locks precede entry/source/dispute locks. Current reads protect nested repeatable-read transactions; unique actor/request keys and locked duplicate checks prevent repeated cash. The MySQL harness proves simultaneous lock contention rather than relying on a timing-only interleaving.
- Confirmed work earnings remain visible, while unrecorded/pending amounts are kept separate from accepted Bookkeeping debt. The existing approval step is now required before a requested obligation consumes credit. Summary-only advance subtraction is removed. Money layout simplification remains deferred.
- Read-only owner report: `storage/release-acceptance/m5/credit-dry-run.json`, produced by `php tools/m5-credit-dry-run.php`. The confirmed 1,000,000 Toman advance remains entirely available and unapplied; its automatic historical reconciliation is disabled. The void payment contributes zero. Hypothetical applications are labelled proposals requiring explicit review/authorization. No normal Laragon database writes, migrations or historical corrections occurred.
- No schema migration is needed. Safe rollback retains the corrected reader and audit/source history while disabling new writes; reverting to the old debt formula would reintroduce the reported defect.

Final verification evidence is appended below after the disposable MySQL run. No merge, deployment, PR management or M6 work is included. Bilingual component tests cover the new Bookkeeping source controls and history; browser/mobile visual acceptance remains separate from this accounting checkpoint.

### Final accounting gate evidence

- **Disposable MySQL 8.4.3:** `php tools/m5-credit-mysql.php` — **54/54 tests passed, 862 assertions**. Includes Bookkeeping rules/reference scenarios plus 22 credit-accounting cases, Deal Money/on-account, construction, measured work, Work and query-budget suites. MySQL, unlike SQLite, enforces the tested 80-character idempotency column and actor/key uniqueness.
- **Real concurrency:** three independent worker pairs, each with **two simultaneous MySQL ledger-lock waiters verified** before release. Duplicate payment requests return one transaction; competing 600,000 returns against 1,000,000 credit reserve only one request; simultaneous 600,000 obligation confirmations apply 1,000,000 once and leave 200,000 outstanding. Workers deliberately begin with stale outer repeatable-read snapshots. All three passed.
- **Additional focused regression:** noncash Deal remaining-position regression passed (1 test / 6 assertions); duplicate/access path regression passed (1 / 9); final corrected accounting-help rendering passed (1 / 17); localization parity passed (7 / 360). The noncash guide correction was committed after the full MySQL matrix; its backend cash/noncash partition is included in that matrix, and its additional Deal summary regression ran on SQLite.
- **Quality:** full Pint check passed; PHPStan **0 errors**; `npm run build` passed. Vite reported the existing optional `fontaine` optimization notice. No dependency changes were made to address that unrelated notice.
- **Performance:** Work query counts 36 → 44 and Money 35 → 33 with eight additional confirmed inspections, within the existing budget.
- **Owner-data dry run:** explicitly read-only transaction; report saved locally, not committed. Confirmed 1,000,000 advance remains unapplied historical credit; void 1,000,000 remains excluded. No normal database migration, backfill, reconciliation write or audit mutation.
- **Scope limits:** focused suites, not the entire application suite; bilingual component rendering, not a browser/mobile visual walkthrough. Money redesign/estimation, PR management, merge, deployment and M6 remain deferred.
