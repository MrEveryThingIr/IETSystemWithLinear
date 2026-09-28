# IET Settlement & Exchange Baseline

## Purpose

This milestone connects the existing Personal Accounting and Financial Obligation kernels through one internal settlement unit: IET.

It does not collapse Accounting and Financial into one model. Accounting continues to own ledgers and journals; Financial continues to own obligations and bilateral settlement state; the IET rail owns valuation snapshots, exchange requests, wallet movement, and locked settlement conversion.

## Initial valuation

The baseline definition is shown in two equivalent forms:

- X = 0.000001% of 1 USD
- 1 IET = 0.00000001 USD
- therefore 3.50 USD = 350,000,000 IET

Conversion uses integer / arbitrary-precision arithmetic only. The canonical valuation stores USD pico-units per IET.

IET uses two decimal places in the accounting ledger. This preserves fractional IET while keeping large balances safely inside integer ledger range.

## Immutable valuation history

Every IET valuation is an immutable snapshot with an effective time, source, optional factors/evidence, note, and publishing Actor when applicable.

An Exchange request or IET Settlement keeps the exact valuation snapshot used when it was created. Publishing a later valuation never reprices old economic history.

## Valuation policy boundary

The architecture permits a future policy engine to publish valuations from explicit metrics such as successful accepted flows, fulfilled obligations, settlement activity, or other reviewed factors.

This baseline deliberately does not invent the formula and does not guarantee appreciation. A future policy must publish through the same audited snapshot action.

## Finance workspace

The baseline sidebar has one financial entry: Finance.

The Finance workspace keeps major blocks collapsible and includes:

- available IET;
- reserved IET;
- current valuation;
- deposit/cashout Exchange;
- Financial Obligations;
- Personal Accounting link;
- Exchange review for authorized users;
- valuation publication for authorized users.

Personal Accounting remains a focused subpage rather than being duplicated inside Finance.

## Exchange lifecycle

Deposit:

1. User submits a USD deposit request.
2. Current IET valuation is locked into the request.
3. External payment remains a manual placeholder.
4. Another authorized reviewer confirms.
5. Only then is IET credited to the user's ledger-backed wallet.

Cashout:

1. User requests USD cashout.
2. Current valuation is locked.
3. Required IET is removed from available balance immediately.
4. Confirmation consumes that reservation.
5. Rejection returns the IET to available balance.

The requester cannot review their own Exchange request.

## Wallet truth

There is no mutable users.balance field. IET truth is derived from immutable Journal Entries / Journal Lines in the existing Accounting kernel.

The IET personal Ledger contains dedicated system accounts for available cash, Exchange source/sink, Settlement reserve, transfer-in, and transfer-out.

Opening Finance is read-only and does not create wallet infrastructure. The wallet is provisioned only when a real IET action occurs.

The ordinary Personal Accounting UI excludes IET so users cannot manually mint or manipulate it through income/expense forms.

## USD obligation to IET settlement

Financial Obligations preserve their original economic denomination.

The first internal settlement bridge supports USD obligations only. It does not invent EUR/IRR/other FX rates.

Flow:

1. Accepted USD Financial Obligation exists.
2. Debtor proposes an amount to settle.
3. Current IET valuation snapshot is locked.
4. Required IET is reserved from debtor.
5. Creditor confirms.
6. Reserved IET leaves debtor and enters creditor wallet.
7. Existing Settlement becomes confirmed.

If the creditor rejects, the reserved IET returns to the debtor.

Pending settlement claims reduce the amount available for another proposal, preventing over-commitment of the same obligation.

## Future system-flow seam

IetRequirement is the reusable boundary for future internal actions that require a USD-equivalent amount:

- quote current required IET;
- read available IET;
- reject when insufficient.

Future capabilities should call this boundary instead of duplicating valuation or wallet rules.

## Accounting boundary

IET wallet entries are posted automatically by the internal rail.

The historical external-cash settlement-accounting action is blocked for IET Settlements so an internal transfer is not falsely represented as an external cash payment.

## Security and audit

- valuation snapshots are immutable;
- Exchange request quote/provenance is immutable;
- Exchange lifecycle uses dedicated Actions;
- Exchange review requires ManageExchange;
- self-review is forbidden;
- wallet journal writes are idempotent;
- Settlement reserve/finalize/release is transactional;
- existing bilateral Financial Obligation visibility remains authoritative;
- no bank credentials or real payment provider are required.

## Acceptance scenarios

1. Open Finance with no IET activity: zero balance, and no ledger created by viewing.
2. Confirm bootstrap display X = 0.000001% and 1 IET = 0.00000001 USD.
3. Submit USD 3.50 deposit; request locks 350,000,000 IET.
4. Before review, wallet is unchanged.
5. A different authorized reviewer confirms; wallet receives the locked amount.
6. Submit cashout; available IET drops immediately.
7. Reject cashout; available IET returns.
8. Publish a later X value; old requests keep old quote while new requests use new snapshot.
9. Propose an IET settlement for an accepted USD obligation; debtor IET is reserved.
10. Creditor confirms; reserve clears and creditor receives IET.
11. Reject another proposal; debtor gets reserved IET back.
12. Verify non-USD obligations are not assigned an invented FX conversion.
13. Verify Personal Accounting does not expose manual IET manipulation.