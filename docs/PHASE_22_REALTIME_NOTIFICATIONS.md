# Phase 22 — Realtime + Notifications

## Status

Implementation candidate in progress on `feat/ideal-v1-22-realtime-notifications`.

## Purpose

Add durable user attention state and optional realtime delivery without making WebSockets, broadcasts, browser state, or notification rows authoritative for domain truth.

## Architecture

```text
authoritative domain transaction
→ immutable domain event / explicit lifecycle change
→ recipient-specific notification_outbox row in the same transaction
→ post-commit queued delivery
→ Laravel database notification (durable inbox/read state)
→ UserInboxChanged broadcast
→ authorized private users.{id} channel
→ browser refreshes the inbox
```

A reload always reconstructs the inbox from the database. Broadcast failure does not erase or roll back committed domain state.

## Implemented scope

- durable `notification_outbox` with idempotency key, attempts, delivery/failure timestamps and recipient/context/subject provenance;
- Laravel database notifications as durable recipient inbox/read state;
- queue-backed `DeliverNotificationOutbox` job;
- post-commit dispatch;
- private per-user broadcast channel authorization;
- Reverb server connection and Echo/Pusher browser client seam;
- Notifications page with unread/all filters, mark-read, mark-all-read and safe relative destinations;
- inbox auto-refresh on `inbox.changed`;
- due Planner reminder emitter;
- lifecycle producers for Relationship, Proposal, Contract, Commitment/Fulfillment, Financial Obligation/Settlement, Conversation, submitted interactions and finalized evaluations;
- bounded Context recipient discovery with policy rechecks;
- English/Persian/Arabic/Simplified-Chinese UI message catalogs.

## Correctness rules

1. Domain Actions and domain tables remain authoritative.
2. Notification projection participates in the authoritative transaction; rollback removes projected outbox work.
3. Queue/broadcast delivery occurs after commit.
4. Idempotency keys prevent duplicate logical notification requests.
5. Recipient authorization is derived from authoritative participation/context and rechecked where the target is policy-sensitive.
6. Client events trigger refresh only; they do not mutate domain truth.
7. Realtime is optional. With `BROADCAST_CONNECTION=log` or `null`, durable inbox behavior still works.

## Local realtime operation

Keep the database queue worker running and, when realtime browser delivery is desired, set `BROADCAST_CONNECTION=reverb` and run:

```bash
php artisan reverb:start
php artisan queue:work --queue=notifications,default
npm run dev
```

Production should supervise Reverb and queue workers. Credentials/host/TLS belong in environment configuration.

## Negative guarantees

- chat text cannot accept a Contract or perform another authoritative transition;
- notification delivery cannot create Membership, Proposal, Contract, Fulfillment, Settlement, payment or ledger truth;
- private channels do not authorize resource access;
- possession of a broadcast payload does not bypass Context/subject policies;
- failure to receive a WebSocket event does not lose the durable notification.

## Exit gate

- focused notification/projection/reminder tests green;
- full PHPUnit, PHPStan, Pint, Vite, migration/queue/scheduler/backup/security gates green;
- Reverb dependency lock is committed and reproducible;
- manual worksheet records durable inbox, reconnect/reload and private-channel behavior;
- canonical state/handoff/report are synchronized before integration.
