# Eight-milestone implementation tracker

Authority: owner-supplied October 24 architecture and Adaptive Composer proposal,
plus October 25 handoff. Engineering evidence is distinct from owner acceptance.

Lifecycle: Planned → In progress → Engineering-complete → Awaiting owner acceptance
→ Accepted → Merged → Released.

| Milestone | Scope | Current evidence-backed status | Dependency |
|---|---|---|---|
| M1 | Navigation, Interests, Abilities, My Items | Engineering-complete reported; PR #85 open. Owner acceptance not established | None |
| M2 | Adaptive Composer, Needs and Offers | **Engineering:** complete on `feat/m2-adaptive-intention-composer` (PR #86) plus the refinement branch `feat/m2-category-aware-composer` (field library, typed constraints, suggestions, taxonomy, groups/templates, published cards, Composer as the only Need/Offer editor). **Owner acceptance:** in progress — both M2 migrations executed on the owner's Laragon `EveryThing-system` and saved templates verified present (Oct 25). **Merge:** none; refinement PR not yet open. **Release:** none | Unmerged M1 |
| M3 | Deals, negotiation, Terms and agreement | Planned. Domain audit done ([M3 plan](M3-DEALS-TERMS-PLAN.md)); implementation waits for explicit M2 acceptance and integration | M2 prefilling |
| M4 | Deal-linked Planner execution and Deliveries | Planned | M3; preserve independent Planner workstream |
| M5 | Deal accounts, obligations, settlement | Planned | M4 |
| M6 | Unified Business integration | Planned | M5 |
| M7 | Evidence-based Proven Records | Planned | M5 |
| M8 | Introductions/referrals/incentives | Planned; monetary incentives need legal approval | M5 and later approvals |

Living Planner v2 remains independent: draft PR #80; its presence is not inferred
from M1's navigation. Production releases are separate owner decisions.

Each milestone tracks four separate statuses: engineering, owner acceptance, merge, release.

M2 C1–C6 and exact continuation instructions live in
[the shared handoff](M2-ADAPTIVE-COMPOSER-HANDOFF.md).
