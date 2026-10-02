# IET Internal Settlement + Exchange Baseline

## Purpose

This milestone merges the already-developed Personal Accounting and Financial Obligation/Settlement kernels into one coherent Money experience and introduces **IET** as the system-controlled internal settlement unit.

It does not create a second balance table. IET lives inside the same immutable Ledger / Account / JournalEntry kernel as every other monetary unit.

## Initial valuation

The initial requirement is interpreted literally as **X = 0.000001% of one USD**.

```text
X = 0.000001%
1 IET = 0.000001% × 1 USD
1 IET = 0.00000001 USD
```

Therefore:

```text
3.50 USD / 0.00000001 USD per IET = 350,000,000 IET
```

The stored quote is exact decimal data with up to 18 fractional digits. Pricing uses integer/rational arithmetic rather than floating-point financial math. The UI shows both the USD-per-IET quote and its equivalent X-percent form so the economic meaning is explicit.

## IET monetary identity

- code: `IET`
- name: IET Internal Settlement Unit
- exponent: 0 in this baseline
- wallet: the Personal IET Ledger's system cash account

A user cannot manually mint or mutate IET by recording Income, Opening Balance, arbitrary Expense/Transfer corrections, generic reversals, or by calling the generic journal kernel with an unapproved entry kind/source. The journal kernel allow-lists the dedicated IET posting paths.

Dedicated posting paths are:

1. confirmed Exchange deposit/cash-out;
2. internal USD-priced flow charge;
3. confirmed IET Financial Obligation settlement.

## Versioned valuation

`iet_valuation_quotes` is append-only.

Each quote stores:

- exact `usd_per_iet` at up to 18 decimal places;
- effective timestamp;
- policy version;
- optional factor snapshot;
- rationale;
- publisher.

A non-empty rationale is mandatory for every newly published quote. Exchange requests, direct internal charges, and USD-priced IET obligations pin the quote used at creation. A later valuation change never changes old economic facts.

### Successful-flow signals

The baseline snapshots signals such as:

- successful direct internal charges;
- confirmed IET obligation settlements;
- confirmed exchange deposits;
- combined successful-flow count.

These are **inputs/evidence**, not an automatic appreciation formula.

The baseline intentionally does not encode “IET must always rise.” A future valuation policy may derive a candidate quote from transparent factors, but publishing a new quote remains an explicit, versioned economic decision. This avoids silently turning activity metrics into a guaranteed-return mechanism.

## Exchange capability

`/exchange` is the future boundary for real payment-provider integration.

Current modes:

### Deposit → IET

1. user records a USD amount and optional external reference;
2. current IET quote is snapshotted;
3. request remains pending;
4. an authorized Exchange reviewer checks the external evidence manually;
5. confirmation posts IET into the user's IET wallet.

### IET → cash-out

1. user requests a USD external amount;
2. quote is snapshotted;
3. required IET is reserved from the user's available internal-spend balance while the cash-out is pending;
4. internal charges and new IET settlements respect that reservation;
5. confirmation rechecks actual wallet funding;
6. confirmed cash-out posts IET out of the wallet.

No external money is actually moved by this baseline.

## Internal USD-priced flows

Any capability with a USD reference price can call:

`ChargeIetForUsdFlow`

Inputs:

- authenticated User;
- USD amount in cents;
- stable source type;
- stable source UUID;
- optional description.

The action:

1. snapshots the current IET valuation;
2. converts the USD requirement into whole IET, rounding upward;
3. locks the user's IET Ledger;
4. rejects an underfunded wallet;
5. posts a balanced immutable IET JournalEntry;
6. stores an immutable `IetInternalCharge` provenance record;
7. is idempotent by User + source type + source UUID.

This is the seam for product flows that must be pre-funded before execution.

## Financial Obligations and IET

Deferred economic relationships remain Financial Obligations rather than direct charges.

A USD-priced accepted Fulfillment may be converted with:

`RecognizeUsdPricedFulfillmentInIet`

The resulting Financial Obligation is denominated in IET and has a separate immutable pricing snapshot linking:

- reference USD amount;
- exact IET quote;
- resulting IET amount.

### IET settlement confirmation

For non-IET currencies, the existing explicit per-party accounting workflow remains unchanged.

For IET:

1. Settlement is still proposed and counterparty-confirmed;
2. confirmation checks the debtor's real IET wallet balance;
3. both Personal IET Ledgers are locked in stable order;
4. obligation-accounting entries are idempotently ensured for both parties;
5. Settlement accounting is posted for both parties atomically;
6. debtor wallet decreases and creditor wallet increases in one transaction.

Thus a confirmed internal IET Settlement cannot create value from an unfunded debtor.

## Human-facing IET net position

The baseline intentionally distinguishes **economic position** from **funded wallet**.

For a user:

```text
IET net position
= funded IET wallet
+ outstanding accepted IET receivables
- outstanding accepted IET payables
```

This means an accepted 100 IET service may immediately produce:

- receiver: wallet 0, payable 100, net position **−100 IET**;
- provider: wallet 0, receivable 100, net position **+100 IET**.

No negative-wallet record is created. The Financial Obligation remains the immutable
truth about who owes whom.

A debtor may recover economically by providing value rather than depositing cash. If
they owe 100 IET and later earn an accepted 150 IET receivable, their net internal
position is +50 IET even before either obligation is settled.

### Internal capacity versus external cash-out

A positive net position is useful for understanding internal capacity and future
agreements, but an unsettled receivable is not treated as externally funded money.

Cash-out eligibility is therefore capped by actually funded, available IET wallet
value. This avoids implicitly financing external withdrawals from another user's
unfunded debt.

The Money hub exposes this distinction directly and points a negative-position user to
two recovery paths:

1. placeholder Exchange deposit;
2. publish skills/professions/services/goods and earn IET through accepted work.

## Money UI

Money remains the only top-level financial entry point in the clean baseline.

It now contains:

- personal paper-like accounting notebooks;
- IET wallet balance and current quote;
- link to IET Exchange;
- Financial Obligations alongside transactions;
- links to obligation/settlement detail.

The old Financial kernel is reused; it is not duplicated.

## Safety and product boundaries

The baseline deliberately avoids:

- real bank connectivity;
- payment-provider credentials;
- automatic cash movement;
- exchange-rate guarantees;
- guaranteed appreciation;
- investment-return language;
- algorithmic repricing;
- floating-point accounting;
- manually editable IET balances.

Future real deposit/cash-out providers should implement adapters behind the Exchange request lifecycle instead of changing Ledger truth.

## Browser acceptance

1. Open Money and confirm IET wallet + quote appear without crowding the personal notebook.
2. Open Exchange.
3. Submit a USD deposit request.
4. As an Exchange-capable administrator, confirm it.
5. Confirm the user's IET wallet increases.
6. Submit a cash-out request and confirm it; wallet decreases.
7. Confirm a normal user cannot publish valuation or review another user's request.
8. Publish a second quote as administrator and confirm old requests still show their old quote.
9. Open an IET Financial Obligation and confirm its USD reference/quote provenance.
10. Attempt an underfunded IET Settlement and confirm it is rejected.
11. Fund the debtor and confirm the Settlement; debtor and creditor wallets update atomically.
12. Confirm ordinary Money forms cannot manually create or mutate an IET balance.
