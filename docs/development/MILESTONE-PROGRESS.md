# Eight-milestone implementation tracker

Authority: owner-supplied October 24 architecture and Adaptive Composer proposal,
plus October 25 handoff. Engineering evidence is distinct from owner acceptance.

Lifecycle: Planned → In progress → Engineering-complete → Awaiting owner acceptance
→ Accepted → Merged → Released.

| Milestone | Scope | Current evidence-backed status | Dependency |
|---|---|---|---|
| M1 | Navigation, Interests, Abilities, My Items | **Engineering:** complete. **Owner acceptance:** accepted. **Merge:** merged to `integration/v2` via PR #85 (merge commit `2b438857`). **Release:** not released | None |
| M2 | Adaptive Composer, Needs and Offers | **Engineering:** complete (foundation + category-aware refinement). **Owner acceptance:** UX-approved by the owner; both M2 migrations ran on the owner's Laragon database. **Merge:** merged to `integration/v2` via PR #86 (`c5ea9afe`) and PR #87 (`a90cb92d`); integration verified (see handoff). **Release:** not released | M1 |
| M3 | Deals, negotiation, Terms and agreement | **Engineering:** complete on `feat/m3-deals-terms`, final gate passed: one Deal workspace (Conversation | Agreement | Work | Money), Contract and Proposal addresses open it, Toman-only new Terms, printable executed Agreement, voice fix proven in a real Chrome; full suite 1219/1219 with the Windows OpenSSL setting, Pint and PHPStan clean, build clean, amendments now live in the Agreement tab (no separate contract tools; one new migration `2026_11_16` for the owner to run), commitment creation removed from the ordinary Deal (records kept), clause-to-work/money mapping in [M4-M5 mapping](M4-M5-AGREEMENT-OPERATIONS-MAPPING.md), path and release scope in [M4-M8 plan](M4-M8-PLAN-AND-RELEASE.md). See [handoff](M3-DEALS-TERMS-HANDOFF.md). **Owner acceptance:** awaiting. **Merge / release:** none | M2 prefilling |
| M4 | Deal-linked Planner execution and Deliveries | **Engineering:** complete: propose, receiver confirms, cycles, reports, decisions, corrections, disputes in the Deal's Work tab; CI passed after the calendar-component fix. **Owner acceptance:** core Work experience accepted after browser testing; nonessential improvements deferred. **Merge:** merged to `integration/v2` via PR #89 (`8d7d838c`). **Release:** not released. See [handoff](M4-WORK-HANDOFF.md) | M3; preserve independent Planner workstream |
| M5 | Deal accounts, obligations, settlement | **Engineering:** bounded accounting correction implemented on `feat/m5-deal-money`: authoritative accepted debt / advance credit / source-linked returns; lifecycle audit maps and ledger-serialized idempotency; no migration or historical correction. Verification and rollback: [correction](M5-ACCOUNTING-CORRECTION.md), [handoff](M5-MONEY-HANDOFF.md). Money layout simplification remains deferred. **Owner acceptance:** awaiting corrected accounting review. **Merge / release:** none | M4 |
| M6 | Unified Business integration | Planned | M5 |
| M7 | Evidence-based Proven Records | Planned | M5 |
| M8 | Introductions/referrals/incentives | Planned; monetary incentives need legal approval | M5 and later approvals |

Living Planner v2 remains independent: draft PR #80 (not merged here); its presence is not inferred
from M1's navigation. Production releases are separate owner decisions.

Each milestone tracks four separate statuses: engineering, owner acceptance, merge, release.

M2 C1–C6 and exact continuation instructions live in
[the shared handoff](M2-ADAPTIVE-COMPOSER-HANDOFF.md).
