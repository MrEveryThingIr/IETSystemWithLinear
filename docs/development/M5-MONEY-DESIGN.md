# M5 design — Money inside the Deal (draft, not started)

Status: **implemented** on `feat/m5-deal-money`; see [M5-MONEY-HANDOFF.md](M5-MONEY-HANDOFF.md) for what was built and where it differs from this draft (no "Pay" shortcut; derived amounts are owed per the Agreement, typed amounts only after the payer agrees). Inputs read: [M4-M5-AGREEMENT-OPERATIONS-MAPPING.md](M4-M5-AGREEMENT-OPERATIONS-MAPPING.md), [M4-WORK-HANDOFF.md](M4-WORK-HANDOFF.md), [BOOKKEEPING.md](../BOOKKEEPING.md), `ProposeWorkBookkeepingEntry`, `RecordSharedEntry` (allocations), `Transaction` (source columns, optimistic `version`, idempotency), `EntrySource`.

## The circuit, in the Deal's words

Agreement → Work → Evidence → Confirmation → **Amount owed → Payment → Receipt → Settlement**.

Two ordinary people see one Money tab. Bookkeeping stays the only place where money facts are stored; the Deal only reads it and asks it to record.

## What already exists and is reused (no new money system)

| Need | Existing authority |
|---|---|
| A shared book of two people | `Ledger` (kind `shared`), `CreateLedger`, `AddLedgerParty` |
| "A owes B" | `Transaction` type `obligation` (from = debtor, to = creditor) |
| "A paid B", split over debts | type `payment` + `Allocation` (`RecordSharedEntry` allocations, oldest-first or explicit) |
| The other side confirms / rejects / disputes | `DecideOnEntry`, `RaiseDispute`, `ResolveDispute`; status `pending → confirmed | rejected | disputed | void` |
| Receipt / proof | `AttachEvidence` (up to 10 files, book people only) |
| No double entries | unique `(recorded_by, idempotency_key)`; one live entry per `(source_type, source_uuid, source_version)` |
| Traceability | `EntrySource` (`origin`, `source_type`, `source_uuid`, `source_version`) |
| Balances | `BalanceCalculator` (derived, only confirmed counts; pending/disputed reported beside) |

## Core separation: entitlement is computed, money is recorded

1. **Eligible (computed, no row).** For each accepted `Fulfillment` (and, where the Agreement says `on_acceptance`, the Agreement itself), `DealMoney` computes the amount the *accepted terms* entitle the doer to, using the Commitment's **own** `contract_version_id` (so an amendment never changes past amounts). Nothing is written to Bookkeeping.
2. **Requested (Bookkeeping `obligation`, pending).** *Request payment* by the entitled party records one obligation per eligible item (`EntrySource(IetWorkflow, 'fulfillment', fulfillment uuid, agreement version)`; for `on_acceptance`, source type `agreement`). For an amount derived from the accepted terms it is shown as owed on the strength of the Agreement and the accepted work; for a typed amount it is only *requested* until the payer confirms it.
3. **Reported paid (Bookkeeping `payment`, pending).** *Report payment* by the payer records a payment with an amount, date, optional receipt and **explicit allocations** to the open obligations it covers (one or several cycles; partial allowed). Not proof of receipt.
4. **Confirmed received.** The payee confirms (allocations then count), rejects with a note ("received less / not received"), or disputes. A rejection keeps the claim in history; the payer may record a corrected payment.
5. **Settled.** An obligation is settled when its confirmed allocations equal its amount. The Agreement is **never** marked complete by money alone.

## Which amount counts (never guess)

Derivable only when unambiguous: the duty's Commitment is priced by the version's typed `price` when `min == max`, currency IRT, and either (a) `basis = total` and the Agreement has exactly one duty, or (b) `basis` equals the Commitment's unit (hour/day/item/…), giving `accepted quantity × price`. `payment.mode` decides *when*: `after_confirmed_delivery` per accepted report; `per_period` bundles accepted reports of the week/month; `on_acceptance` is eligible at agreement acceptance; `none` shows no Money workflow.
Otherwise (range price, several duties, unit mismatch, free text): **no suggested amount**; the entitled party types the amount and the payer must confirm it as an ordinary pending obligation (explicit review, never a silent debt). Nonmonetary Agreements show "No money in this Deal" and create nothing.
Small M4 follow-up that makes (b) reliable: offer the price units as a pick-list for a duty's unit.

## The Money tab

Per Deal, one summary and a list, in plain words: **Owed** (eligible + requested, with basis and source link), **Requested**, **Reported paid**, **Confirmed received**, **Outstanding**, **Needs attention** (rejected/disputed). Every figure links to its Bookkeeping entry or to the accepted Fulfillment; "View in bookkeeping" is the progressive-disclosure door. Buttons by role: *Request payment* (entitled), *Pay / Report payment* (payer), *Confirm received / Not received* (payee), *Dispute* (either), nothing else.

## Where it lives

- `App\Support\Deals\DealMoney` (read model: eligible, requested, paid, confirmed, outstanding, per Commitment/cycle; pure, query-only).
- `App\Actions\Deals\ManageDealMoney` (`request`, `report`, `confirm`, `reject`) delegating to the existing Bookkeeping actions; the old `ProposeWorkBookkeepingEntry` amount logic (service-term based) is generalised, not duplicated.
- `App\Livewire\Deals\MoneyPanel` replaces the placeholder Money tab.
- One shared Bookkeeping book per Deal, found or created on first use; its id is kept in `relationships.metadata.money_ledger`. **No migration is expected**; if one proves necessary it is additive and rehearsed on a populated disposable MySQL first.

## Decisions taken (change only if the owner objects)

1. **No new book kinds, Party-owned ledgers or statements yet.** The earlier roadmap listed `bk_ledgers` Party columns, a `deal` ledger kind and `bk_statements`; those are heavier and not needed for two people to complete a transaction. Deferred.
2. **Reporting is not proving (owner correction).** There is no "Pay" button that settles anything. The payer *reports an actual payment* (amount, date, optional receipt, allocations); the payee separately *confirms receipt*. A payment request is a request, not a debt: an amount counts as owed only when the accepted Agreement version and its conditions make it eligible (derivable amounts), or when the payer has confirmed the typed amount. A payer who disagrees with a request disputes it.
3. **Book people are Actors** (as in M4): a Business/Group manager who is not the stored representative cannot record in the Deal's book. Same documented limitation; authority is re-checked with `PartyAuthority` at action time.

## Checkpoints (when M4 is merged)

- **M5-a** `DealMoney` read model + Money tab read-only; tests for derivation rules and amendments. 
- **M5-b** Request payment (obligations, idempotent per source version) + payer acknowledgement.
- **M5-c** Report payment with allocations and receipt; confirm / reject / dispute; no double counting.
- **M5-d** Scenarios A–E (one-time sale; recurring wages with two of four days requested; instalments over several cycles; disputed payment; nonmonetary), stale version, amendment, unauthorised actor, duplicate submit, concurrent confirm; populated-MySQL rehearsal if any migration; full regression, Pint, PHPStan, build; two-account browser walkthrough (en/fa, mobile, dark).

## Guard rails (each gets a test)
Requesting never creates a payment; a reported payment never settles anything; only confirmed entries count; the same fulfillment/version yields one live obligation; the same payment form submitted twice records once; allocations never exceed an obligation's open amount; rejection and dispute keep history; an amendment never rewrites amounts of earlier Commitments; real money never moves.
