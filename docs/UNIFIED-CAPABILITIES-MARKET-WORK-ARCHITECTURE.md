# Unified categories, capabilities, market, work and communication

Authoritative architecture for releases 1.8–1.12. It supersedes the domain
parts of `CAPABILITY_MESH_ARCHITECTURE.md`, `PHASE_19_NEED_OFFER_MATCHING.md`
and `BUSINESS-PLATFORM-ROADMAP.md` where they disagree. The retired finance
documents (`FINANCIAL_ARCHITECTURE.md`, `PHASE_17_*`, `IET_INTERNAL_SETTLEMENT_BASELINE.md`)
describe removed machinery and are historical only.

Baseline: `release/formal-v1-production` at `f08324a` (v1.7.0) plus PR #72 (v1.7.1).

## 1. Audit (what exists, verified in code)

| Area | Source of truth today | Finding | Decision |
|---|---|---|---|
| Categories | Concept kernel: vocabularies (platform / actor / group scope), concepts, multilingual labels with synonyms, schemes, hierarchy edges + closure (cycle-safe, multi-parent), relations, assertions, merge chain | Complete engine, almost unused: no platform vocabulary; every Need/Offer label becomes a private concept in the person's `profile-concepts` vocabulary | Reuse. Seed a platform vocabulary with a *Life domains* scheme. Add a business scope. |
| Professions | `professions` (own tree, `name` + `name_fa` only), `actor_professions` (level, years, visibility), `business_membership_profession` | Second, poorer taxonomy beside the kernel; same ability modelled twice (profile and business team) | Map every profession to a concept; copy records into **capabilities**; keep legacy tables untouched until production counts are reviewed. |
| Needs & Offers | `actor_profile_intents` (kind, concept, quantity, unit, location, origin/destination, price range, currency, arrangement, urgency, availability/recurrence) | Rich, but only a person can publish; carries its own recurrence fields | Evolve in place (1.9). Keep availability as availability; Planner only for real scheduling. |
| Businesses | `businesses`, memberships (owner/manager/member), categories (local tree), listings with versions and price versions, media, public pages | Good. Internal pages use hand-written CSS outside the app's components | Keep; local categories get an optional concept link; internal UI unified in 1.11. |
| Groups | groups, memberships, roles/permissions, agreements, spaces | Good governance | Keep distinct from businesses; share capabilities, categories, conversations. |
| Work | relationships (deals) → proposals → contracts/versions → service terms → commitments → fulfillments | Service terms embed ~10 plan fields + `auto_create_plan`; `auto_recognize_obligation` is written but read by nothing (retired finance) | 1.10: Work page over these records; four-way Planner choice; hide the dead switch, keep the column. |
| Planner link | `commitment_plan_bindings` | Exists, unused | Becomes the "connect a plan" mechanism. |
| Plan templates | none | Missing | Add in 1.10 (schedule shape only). |
| Conversations | `conversations` bound to a context, messages with replies, revisions, assets, evidence references; group space chat already uses it; `ContactDirectoryService` exists | One engine already — good | Extend for direct messages and contacts (1.12). |
| Realtime | Reverb configured locally; production `BROADCAST_CONNECTION=log`, `QUEUE_CONNECTION=sync`, no long-running processes | WebSockets not available on the host | Authenticated polling with adaptive intervals; Reverb used automatically where configured. |
| Bookkeeping | ledgers, transactions, confirmations, disputes, audit log | Independent and trustworthy | Add provenance (origin + source reference) in 1.10; never auto-record. |

## 2. Shared rules

### Owner and actor (one pattern everywhere)

Every record that can belong to a person, a business or a group uses the same
**party** columns: `{role}_actor_id`, `{role}_business_id`, `{role}_group_id`
(exactly one set), plus `created_by_actor_id` for the person who acted.
`App\Support\Parties\Party` reads and writes them; `PartyAuthority` decides
whether a user may act for a party (person: themself; business: owner or
manager; group: the matching group permission). Owner and acting person are
never the same field.

### Categories

* One platform vocabulary `iet-categories`; scheme `life-domains` holds the
  16 domains and their subcategories as hierarchy edges. Other schemes
  (industries, occupations…) can be added later without changing data.
* **Kinds** (skill, service, product, property, activity) are a separate
  many-to-many facet (`concept_kinds`), not a tree position.
* **Private categories** live in the owner's vocabulary (person, business or
  group) and hang under any visible category through a `narrower_than`
  relation. They are usable immediately and visible only to that owner.
* **Suggestions**: a private category can be proposed for everyone →
  `concept_suggestions` (pending → approved / merged / rejected). Approval
  creates a platform concept under the chosen parent and merges the private
  concept into it, so every reference follows automatically. Rejection leaves
  it private. Only platform administrators review.
* The picker shows: search across all visible labels and synonyms → the 16
  domains → one level below → "add my own". Depth is unlimited in data but
  never needs to be browsed.

### Capabilities

`capabilities`: holder (party), concept, optional title/description, level,
years of experience, **self-declared** confidence (0–100, always labelled as
self-declared), visibility, primary flag, optional business membership
("practised as a member of business X"), legacy ids. Commercial terms
(rate, unit, availability, location) never live here — they belong to Offers.
No reputation score; completed-work counts are shown from real records later.

### Layout and speed rules (from the 1.7.1 audit — apply to every new screen)

* Grids: shared guard makes implicit columns `minmax(0,1fr)`; never use fixed
  `minmax(Npx, …)` in auto-fill grids — use `minmax(min(100%,N),1fr)`.
* Rows of buttons or chips must `flex-wrap`; nothing `shrink-0` that can be
  wider than a phone.
* Never `@js()` inside `<flux:…>`/`<x-…>` attributes; use `Js::from()`.
* Avatars and fixed-size images keep `max-w-none shrink-0`.
* No large negative offsets (RTL makes them scrollable).
* Long user text: `min-w-0` and `[overflow-wrap:anywhere]` in flex rows.
* Every new page is checked by the responsive audit at 320–1920px in fa/en/ar/zh.
* Heavy, secondary parts of a page load lazily; permission checks repeated per
  row use `RequestMemo`; no per-row queries (eager load).
* All user-visible text in the four language files; the guard test fails on
  missing keys for enumerations.

## 3. Communication (1.12, small PRs)

One conversation engine, many authorized contexts. A direct-message
conversation is its own context kind, separate from a deal conversation with
the same people.

1. Contacts: requests, accept/decline/remove, block, report; who-may-message-me
   preference (everyone / contacts / nobody). Contact ≠ access to any other data.
2. Direct conversations: history with cursor pagination, replies, reactions,
   edit/delete with revision trail, pin, mute, archive, unread counts, search.
3. Delivery: authenticated polling (fast while open, slow in background,
   paused when hidden), client message ids against duplicate sends, read
   markers; Reverb when available. Push notifications reuse the 1.7 device push.
4. Media: images, video, files, voice notes recorded in the browser, static
   location (explicit action), contact cards; private assets served only
   through message authorization.
5. Group chats and channels on group spaces with roles; business team and
   work conversations bind the same engine.

No end-to-end encryption claim: messages are protected in transit (HTTPS) and
by access control at rest. Calls and live location are later phases.

## 4. Bookkeeping provenance (1.10)

Transactions gain `origin` (manual / iet_workflow / import / correction) and an
optional typed source reference (kind, uuid, version) plus the acting person.
Work only **proposes** an entry (amount from accepted terms × quantity); the
existing shared-book confirmation turns it into a confirmed record. Idempotency
key per source prevents duplicates; each participant's personal book records
their own side only. Filters: all / manual / IET / imported / corrections.

## 5. Implemented in 1.8

* `App\Support\Categories\LifeDomainCatalog` — the seeded tree (16 areas,
  182 subcategories, 4 languages) and the old-profession map.
* `EnsureSharedCategories` — fingerprinted, bulk, idempotent install; never
  revives a retired category. `iet:taxonomy:sync` runs it and the copy.
* `CategoryCatalog` — areas, children, search (all languages, synonyms),
  paths; shared parts cached by version; private parts per owner only.
* `CreatePrivateCategory`, `ReviewCategorySuggestions` — private categories
  (max 6 private levels), suggestions, approve / merge / reject; merges move
  capabilities, Needs/Offers and the owner's subcategories.
* `Party`, `PartyAuthority` — the one owner pattern.
* `Capability`, `SaveCapability`, `CopyLegacyProfessions` — capabilities,
  membership skills (member or the business's managers), legacy copy keyed by
  old row ids; old tables frozen (old forms redirect without writing).
* UI: `categories.picker`, *What I can do* (`/profile/professions`), business
  team skills, *Administration → Category suggestions*.
* `iet:inventory` — read-only report; works on the pre-1.8 schema too.

## 6. Release checklist

| Release | Additive schema | Data steps | Acceptance |
|---|---|---|---|
| 1.8 | concept_kinds, concept_suggestions, capabilities, professions.concept_id, relation types narrower/broader, business vocabulary scope | `iet:inventory` (read-only) before; `iet:taxonomy:sync` after (idempotent; also runs on first use) | picker at 320–1920 in 4 languages; suggestion → approve/merge/reject; legacy professions visible as capabilities; legacy rows unchanged |
| 1.9 | publisher party on intents, capability/listing-version/content links, rate/unit decimals | backfill publisher = existing profile actor | person/business/group publish; matches with reasons |
| 1.10 | plan templates, bookkeeping origin/source | map service-term plan fields to bindings before hiding them | four-way plan choice; proposal → confirmation; no duplicate entries |
| 1.11 | none expected | redirects for moved pages | full journeys A–H |
| 1.12 | contacts, conversation kinds/read state | none | messaging on phones and desktop |

Rollback for each release: the previous package plus its database; every
migration is additive, so the previous code runs against the newer schema.
