# System Form Experience Inventory

Initial inventory for M2. This is a scope map, not a claim of completed UX acceptance.

| Area | Current authority/interface | Classification | M2 action / later milestone |
|---|---|---|---|
| Need/Offer create | `Intents/Create`, `CreateActorProfileIntent` | Duplicate entry/edit experience; rejected wizard | C2 canonical adaptive composer; preserve deep link |
| Need/Offer edit | `Profile/Intents`, `UpdateActorProfileIntent` | Duplicate editor | C2 adapter to same composer after equivalence |
| Market media | `Intents/Photos`, `ManageIntentMedia`, shared Assets | Reusable pipeline; targeted UX needed | C3 progress/retry/docs/privacy; preserve Asset authority |
| Category selection | `CategoryCatalog`, shared picker | Reuse | Suggestions without mandatory category selection |
| Typed Content | `SpaceContentSchema`, Content studio | Reusable validation, specialized authoring | Reuse typed validation/hash; do not redesign studio |
| Interests/Abilities | M1 My Items, `SaveInterest`, `SaveCapability` | Preserve M1; focused small forms | Regression only; no second skills form |
| Personal Planner | Existing Planner create/schedule/evidence forms | Specialized temporal forms | Preserve; independent Living Planner branch is not merged here |
| Deal/Terms | Relationship, Proposal, Contract views/actions | Targeted terminology and composition needed | M3; immutable terms must not become arbitrary custom fields |
| Delivery/evidence | Fulfillment + Asset controls | Specialized operational forms | M4; reuse upload components, preserve review authority |
| Money/payment | Bookkeeping transactions, Toman validation | Specialized consequential forms | Preserve now; M5 targeted settlement experience |
| Business Catalog | Listing/version/price forms | Canonical specialized authoring | C5 read-only source card/publication adapter; broader M6 |
| Business intake/property | Existing portals and real-estate structured data | Specialized operational forms | C5 fixed attribute mapping; preserve portals |
| Authentication/account | Existing auth/profile forms | Preserve | No M2 redesign |
| Sharing/records | Disclosure grants and sharing forms | Specialized privacy boundary | M7; reuse audience preview patterns only |

Reusable components to extract within M2: typed detail renderer, suggested fields,
inline validation, expandable sections, media rows/progress/retry, audience-aware
presentation and publication controls. Shared components do not mean identical
forms or shared domain write authority.
