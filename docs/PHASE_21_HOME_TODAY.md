# Phase 21 — Home / Today personal operating view

## Status

Runtime-green on `feat/ideal-v1-21-home-today`.

Runtime checkpoint:

- SHA: `d0834e5a72545d558d074deb495fd7009daa96ff`
- CI: `36128745672`
- **524 tests / 3244 assertions**
- Pint: 660 PHP files green
- PHPStan: green
- Vite: green
- migration rollback/reapply: green
- scheduler/database queue smoke: green
- SQLite backup/restore smoke: green
- npm audit: 0 vulnerabilities
- Composer audit: no advisories

## Purpose

Make the connected system feel like one application through a personal derived operating view.

The Dashboard route now renders **Today**.

## Derived sections

### What should I do today?

PlanOccurrences scheduled for the user's current local day, filtered through Plan participation authority.

The Planner remains authoritative.

### Waiting on me

Only real pending actions:

- proposed Relationship response;
- current Proposal-version decision;
- pending ContractVersion acceptance;
- submitted Fulfillment review;
- pending Settlement confirmation/rejection;
- submitted interaction in a Context the user may review.

No arbitrary-text task inference is used.

### Waiting on others

Items where the current user has already acted and another required party still has not:

- Relationship invitation;
- Proposal decision;
- Contract acceptance;
- Fulfillment review;
- Settlement confirmation.

### Current operating context

- the user's active Needs/Offers;
- active Relationships;
- active Group memberships.

### Today's accounting

Actual Personal Accounting ledger effects for today, grouped by MonetaryUnit.

No Contract, Settlement or Financial Obligation is treated as accounting truth unless it has been posted to the ledger.

### Financial obligations

Recognized receivable/payable totals plus confirmed paid/outstanding values, grouped by MonetaryUnit.

Obligations remain separate from Personal Accounting.

### Recent activity

A bounded merge of authorized `ContextTimeline` entries from personal, visible GroupSpace and collaboration Contexts.

This is recent history. It does **not** create notification read/unread state.

## Currency rule

Amounts from different MonetaryUnits are never silently summed.

Each currency/unit receives its own accounting and obligation bucket.

## Authorization

Home/Today is a projection, never an authorization shortcut.

Every section is backed by the policy of the source domain.

The office-alpha release profile remains respected; advanced links are not smuggled into the restricted experience through Today.

Verified accounts that do not yet have a primary Actor receive a safe reduced experience rather than actor-owned queries failing.

## Persistence

Phase 21 introduces **no migration** and no Home/Today table.

Opening Today creates no business-domain record.

## Tests

Phase 21 proves:

1. today's owned Plan occurrence and active Intent appear;
2. rendering Today does not create Relationship or financial truth;
3. waiting-on-me and waiting-on-others are distinguished from explicit Relationship lifecycle state;
4. receivable/payable amounts remain separated by direction and currency;
5. Dashboard is the Today operating view;
6. accounts without Actor remain safe;
7. office-alpha release boundaries remain intact;
8. locale preference continues to render Today in the selected language.

## Next

Phase 22 — **Realtime + Notifications**.

Notification state must be durable and explicit. Do not infer unread status from recent timeline history.
