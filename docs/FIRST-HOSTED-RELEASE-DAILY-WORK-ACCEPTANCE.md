# First hosted release — daily work and bookkeeping acceptance

This is the release-gate scenario for ordinary paid work.

## Scenario

Father asks the worker to reserve three selected future workdays.

- work time: 08:00–17:00;
- unit: one accepted day;
- human/reference wage: 1,500,000 toman per day;
- internal authoritative obligation: IET;
- selected days: X, Y and Z;
- after all three accepted days, father later gives 1,000,000 toman cash.

The expected workflow is:

```text
Need / agreement
→ Proposal
→ Contract
→ selected future Plan occurrences
→ workday execution
→ Fulfillment
→ employer acceptance
→ IET FinancialObligation
→ immediate two-sided bookkeeping
→ partial external-cash payment claim
→ counterparty confirmation
→ two-sided settlement bookkeeping
```

## Exchange prerequisite

The hosted system must have:

1. a current IET/USD valuation quote;
2. an active IRT/USD market quote in Exchange.

IRT means Iranian Toman and has exponent 0.

The contract stores the exact IRT amount, the exact market quote used, the exact IET
valuation used and the resulting IET unit rate. Later exchange-rate changes do not
rewrite the accepted Contract version.

## Contract entry

Create the agreement through the normal Need/Offer → Deal → Proposal → Contract path.

Enable the structured paid-service workflow and use:

- kind: Work;
- total quantity: 3;
- quantity per occurrence: 1;
- unit: day;
- enable **price agreed in external money but internal obligation is IET**;
- reference unit rate: 1,500,000;
- reference money: IRT;
- settlement cycle: per fulfillment (or weekly if the parties prefer);
- schedule frequency: Selected dates;
- selected dates: X, Y, Z;
- start time: 08:00;
- duration: 540 minutes;
- auto-create Plan: enabled;
- auto-recognize obligation: enabled.

Before anyone accepts the Contract, the Contract screen must show both the reference
Toman rate and its pinned IET rate.

## Working each day

The generated Plan has one occurrence per selected day.

For each day:

1. start at the allowed start time;
2. complete the occurrence;
3. worker submits one day of Fulfillment;
4. father/employer reviews and accepts it.

Acceptance creates one immutable IET FinancialObligation for that day.

For an IET structured service, recognition also immediately posts:

Father:
- debit Contract expense;
- credit Payable to worker.

Worker:
- debit Receivable from father;
- credit Contract income.

Therefore after three accepted days, both books already contain all three workdays even
when nothing has been paid yet.

## Recording 1,000,000 toman received

On the Contract page, the worker selects:

- Cash statement: **I received cash**;
- outstanding unit: IET;
- enable **enter this cash payment in external money**;
- external amount: 1,000,000;
- cash money: IRT;
- paid time;
- optional reference/note.

The system uses the current IRT/USD MarketQuote plus current IET/USD valuation and pins
both on the settlement batch.

After father confirms the payment claim:

- the converted IET amount is allocated oldest-first across accepted unpaid days;
- outstanding IET debt decreases;
- both parties receive immutable Settlement accounting entries;
- the worker's IET wallet does **not** increase merely because physical Toman cash was
  handed over;
- the external-cash-equivalent account records the received/paid economic value.

This is deliberately different from an internal IET wallet settlement.

## Internal IET versus external cash

**Internal IET payment**

- requires debtor IET wallet funding;
- transfers IET wallet value;
- changes both wallet balances.

**External Toman cash payment**

- does not require debtor IET wallet funding;
- converts the cash amount to IET for debt reduction using pinned quotes;
- posts both parties' bookkeeping;
- does not fabricate an IET wallet transfer.

If the worker later deposits the physical/external money through Exchange, that is a
separate reviewed deposit event.

## Automated release gate

Run:

```bash
php artisan test --compact tests/Feature/DailyWorkIetReferenceSettlementTest.php
```

The test verifies:

- three selected future days;
- 08:00–17:00 Plan occurrences;
- 1,500,000 IRT/day → pinned IET rate;
- three accepted daily obligations;
- immediate two-sided bookkeeping;
- 1,000,000 IRT partial cash payment;
- oldest-first allocation;
- confirmed two-sided settlement bookkeeping;
- zero fabricated IET wallet movement;
- correct remaining IET payable/receivable position.
