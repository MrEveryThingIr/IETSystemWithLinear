# Eight-milestone implementation tracker

Authority: owner-supplied October 24 architecture and Adaptive Composer proposal,
plus October 25 handoff. Engineering evidence is distinct from owner acceptance.

Lifecycle: Planned → In progress → Engineering-complete → Awaiting owner acceptance
→ Accepted → Merged → Released.

| Milestone | Scope | Current evidence-backed status | Dependency |
|---|---|---|---|
| M1 | Navigation, Interests, Abilities, My Items | **Engineering:** complete. **Owner acceptance:** accepted. **Merge:** merged to `integration/v2` via PR #85 (merge commit `2b438857`). **Release:** not released | None |
| M2 | Adaptive Composer, Needs and Offers | **Engineering:** complete (foundation + category-aware refinement). **Owner acceptance:** UX-approved by the owner; both M2 migrations ran on the owner's Laragon database. **Merge:** merged to `integration/v2` via PR #86 (`c5ea9afe`) and PR #87 (`a90cb92d`); integration verified (see handoff). **Release:** not released | M1 |
| M3 | Deals, negotiation, Terms and agreement | **Engineering:** C1-C4 complete and C5 hardening in progress on `feat/m3-deals-terms` (one shared conversation, voice-note fix proven in a real Chrome, old Proposal address redirects to the Deal, printable executed Agreement, Agreement composer with clause library, numbered Agreement document, explicit exact-version acceptance, Business/Group principals; Work and Money are connection points only). See [handoff](M3-DEALS-TERMS-HANDOFF.md), [architecture](M3-C1-ARCHITECTURE.md), [workspace](M3-C4-DEAL-WORKSPACE.md). **Acceptance / merge / release:** none | M2 prefilling |
| M4 | Deal-linked Planner execution and Deliveries | Planned | M3; preserve independent Planner workstream |
| M5 | Deal accounts, obligations, settlement | Planned | M4 |
| M6 | Unified Business integration | Planned | M5 |
| M7 | Evidence-based Proven Records | Planned | M5 |
| M8 | Introductions/referrals/incentives | Planned; monetary incentives need legal approval | M5 and later approvals |

Living Planner v2 remains independent: draft PR #80 (not merged here); its presence is not inferred
from M1's navigation. Production releases are separate owner decisions.

Each milestone tracks four separate statuses: engineering, owner acceptance, merge, release.

M2 C1–C6 and exact continuation instructions live in
[the shared handoff](M2-ADAPTIVE-COMPOSER-HANDOFF.md).
