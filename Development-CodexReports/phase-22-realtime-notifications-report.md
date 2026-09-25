# Phase 22 Closure — Realtime + Notifications

## Result

Phase 22 adds durable recipient attention state and optional realtime transport while preserving authoritative domain truth in the existing kernels.

Runtime proof:

- branch: `feat/ideal-v1-22-realtime-notifications`
- base integration SHA: `0b605a19643f2b37dce7c88332e1baf284a93a33`
- runtime SHA: `cea1cb4d6775b855e94146dfc75029ec1264ac07`
- CI: `36134998196`
- **537 tests / 3297 assertions**
- Pint **686 PHP files**
- PHPStan/Vite/migration rollback-reapply/scheduler/database-queue/backup/security gates green
- Composer audit: no security advisories

## Product result

Implemented:

- durable notification outbox with recipient/context/subject provenance and idempotency;
- queued post-commit delivery into Laravel database notifications;
- persistent unread/read state and Notifications UI;
- due Planner reminder projection;
- notifications for important Relationship, Proposal, Contract, Commitment/Fulfillment, Financial Obligation/Settlement, Conversation, Submission/review and Evaluation transitions;
- authorized private `users.{id}` broadcast channel;
- Laravel Reverb server configuration;
- Echo/Pusher browser subscription;
- live inbox and navigation unread refresh;
- graceful non-realtime operation when broadcasting is disabled.

## Authority result

The transport is deliberately non-authoritative:

```text
authoritative transaction
→ transactional notification outbox
→ post-commit queue
→ durable database inbox
→ authorized broadcast
→ client refresh
```

A WebSocket message cannot create or accept domain truth. Realtime failure does not lose the durable inbox. Reload reconstructs state from the database.

## Documentation / acceptance

Added or updated:

- `docs/PHASE_22_REALTIME_NOTIFICATIONS.md`;
- `docs/LOCAL_ACCEPTANCE_WORKSHEET.md` Checkpoint 22;
- Reverb environment seam in `.env.example`;
- automated realtime configuration/live badge proof.

Owner-local browser acceptance remains intentionally deferred to the cumulative acceptance pass.

## Cross-roadmap audit debt discovered during closure

A Codex audit originally produced against the pre-release office branch was rechecked against this Phase 22 head. Several findings are **still real** and must not be lost merely because later phases are green:

1. `Intents/Directory` still takes the latest 200 rows and policy-filters in PHP, so authorization is applied after the hard limit and real pagination is absent.
2. reserved-email Access Invitations still accept `maxUses > 1` server-side even though the reserved email can register only one account.
3. Intent creation still redirects with `highlight=<uuid>`, while the Directory component does not consume that parameter.
4. the Profile Intent editor still does not expose the newer subject/arrangement/cash/exchange facets created by the guided Intent journey.
5. the canonical state/handoff documentation contains stale milestone wording.

The localization caveat from that audit remains a documentation truth requirement: English is canonical; layout/RTL coverage must not be described as native-reviewed translation.

These are pre-existing cross-roadmap debts, not Phase 22 notification regressions. Correctness/security/performance items must be closed before Phase 23 extraction so the generic Workflow layer is not built over known faulty product seams.

## Next

1. integrate Phase 22 after closure documentation is synchronized;
2. create a focused **Ideal-v1 hardening checkpoint** from the integration trunk for the still-valid Codex findings above;
3. prove the hardening checkpoint green;
4. then begin Phase 23 — Generic Workflow extraction.
