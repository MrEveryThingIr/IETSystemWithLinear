# Phase 8 — Progressive Intent Journey v2

## Status

Remote implementation complete and green on `feat/ideal-v1-08-intent-journey`.

Runtime checkpoint:

~~~text
SHA: eb82f8af1bac3c75e7bd5550db6d2a4dc6badfce
GitHub Actions: 35926251822
428 tests / 2378 assertions
Pint: 368 files
PHPStan: clean
Vite/migrations/scheduler/database-queue/backup: green
npm audit: 0 vulnerabilities
Composer audit: no advisories
~~~

Baseline:

`0134a94aa0d82953614877e80b4b3f50f64a4790`

## Purpose

Make the existing Need/Offer authoring experience start from ordinary human goals while preserving the generic `ActorProfileIntent` kernel.

A user should not need to decide “Need + ownership_transfer + Property” before saying “I want to buy a house”.

## Architecture

Phase 8 deliberately adds **no migration and no generic wizard engine**.

`IntentJourneyPreset` is an application/UI orchestration enum only. It maps a friendly route into existing authoritative fields:

~~~text
friendly journey
→ Need or Offer
→ subject kind
→ arrangement kind
→ existing ActorProfileIntent Action
~~~

The preset itself is not persisted. Later behavior must depend on authoritative Intent fields, not on which button the creator originally clicked.

## Journeys

- Buy/acquire → Need + Ownership transfer
- Sell/transfer → Offer + Ownership transfer
- Rent/use → Need + Temporary use
- Rent out → Offer + Temporary use
- Need service → Need + Service
- Offer service → Offer + Service
- Hire → Need + Service
- Find work → Offer + Service
- Seek capital → Need + Capital + Financing
- Offer capital → Offer + Capital + Financing
- Seek collaboration → Need + Collaboration
- Offer collaboration → Offer + Collaboration
- Other → manual Need/Offer + subject + arrangement

Buy/sell/rent paths may refine the broad subject among Property, Good and Other.

## Negative guarantees

- Hire/Find work does not create an Employment domain.
- Capital intent creates no equity, debt or loan.
- Collaboration intent creates no partnership.
- Buy/sell/rent creates no Contract or title transfer.
- Journey preset is not a permanent hidden type.
- No Match or recommendation is created.
- Existing privacy/visibility policy remains authoritative.

## UX rule

Each step should use previous answers to hide irrelevant controls.

Step 1 asks the general real-world goal.
Step 2 shows only the subject refinement needed by that goal; fixed service/capital/collaboration paths do not show irrelevant subject choices. Manual “Something else” exposes the generic controls.
Steps 3–6 reuse location/value, exchange preference, human details/visibility and review/save.

## Story proof

- Alice: Sell → Property → Riverside Lot.
- Alice: Need service → Residential construction.
- Alice: Seek capital.
- Bob: Offer service / Find work.
- Carol: Offer capital.
- Simple product: Alice sells Used desk; Bob buys Used desk.
- Rental: Rent/Rent out preserves temporary-use rather than ownership-transfer semantics.

## Remote validation

The final run proves:

- every preset mapping regression-covered;
- legacy Intent wizard coverage adapted to enter through the friendly journey;
- no migration added;
- full suite green;
- static analysis/formatting/ops/audits green.

## Exit gate

- every preset mapping has regression coverage;
- simple sale/refinement creates exactly one ordinary Intent;
- Hire/Find work share Service semantics rather than spawning a parallel engine;
- manual Other route still allows generic composition;
- step-two subject options are meaningfully narrowed;
- System Manual and deferred acceptance worksheet document exact controls;
- full remote CI is green.
