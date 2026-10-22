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

## 5b. Implemented in 1.9 (Needs & Offers)

* Publisher party on `actor_profile_intents` (`publisher_actor_id` /
  `publisher_business_id` / `publisher_group_id`, immutable) beside the profile
  of the person who acted. Backfill: every existing row → the profile's person;
  rows whose metadata names a business listing or client need → that business,
  plus explicit `business_listing_id` / `business_listing_version_id`.
* One visibility rule: `ActorProfileIntent::scopeVisibleTo()` (creator and
  managers of the publisher always; others only while active, publisher active,
  and visibility allows; new `members` visibility for business/group members).
  Used by the policy, the directory and matching.
* Sources: `capability_id` (offer started from a skill), listing + exact version.
* `RateBasis` (13 bases), `negotiable`, `free` exchange preference.
* Wizard: publisher choice (only parties the user may act for), category picker
  with typed-name fallback, start-from-skill chips, members visibility for
  organisations only.
* Matching: same category, same label, **category family** (one inside the
  other) and **related** (same subcategory; siblings directly under an area do
  not count). Excludes the same publisher and the same creating profile.
* Directory: area filter (shared descendants + private sub-categories),
  *mine and my organisations*, publisher name, category path, price basis,
  negotiable, cover photo (own photo or the exact catalog version's public cover).
* Photos: `actor_profile_intent_media` → shared Asset pipeline (publisher's
  business context, else the acting person's), max 6, served by
  `/intents/{intent}/media/{media}` only to viewers of the Need/Offer.
* Business categories link to a shared category by exact name (on creation and
  in `iet:taxonomy:sync`); business offers then carry the shared category.

## 5c. Implemented in 1.10 (Work, Planner, Bookkeeping)

* **Plan choice** for work. Contract form: *create the shared plan* / *use a
  template* (fills the times) / *decide later* / *no plan*; it maps onto the
  existing `auto_create_plan` switch, and every scheduling field stays (they
  are the agreed period and due dates even without a plan). Work page:
  *new plan* / *from template* / *a plan already in this deal* (only plans in
  the deal's own context, never someone's private plan) / *no plan*.
* **Plan templates** (`plan_templates`, party-owned): frequency, timing mode,
  interval, start time, duration, weekdays, month day, occurrence limit,
  windows, reminders. No people, dates or history. Saved from a plan's active
  schedule; applied with a new start date.
* `auto_recognize_obligation` no longer appears in the form; the column and its
  value are kept.
* Fixed: automatic plan creation on contract activation referenced a relation
  removed with the old finance system and failed.
* **Bookkeeping provenance**: `bk_transactions.origin` (manual / iet_workflow /
  import / correction) + `source_type`, `source_uuid`, `source_version`;
  existing entries become *manual*, adjustments *correction*.
* **Work → books**: an accepted fulfillment can be *proposed* as a debt
  (employer → worker) in a shared/project book where both are active, amount =
  accepted quantity × agreed rate in toman (IRR ÷ 10; other currencies are not
  proposed). It counts only after the other side confirms; one live entry per
  fulfillment across all books (either side asking again gets the same entry; a
  rejected one can be proposed again). IET never writes to personal books.
* Book page: source filter (all / manual / IET / imported / corrections),
  origin badge and a link back to the work.
* Work hub: *Work in progress* (role, accepted progress, plan, waiting reviews).

## 5d. Implemented in 1.11 (Businesses, Groups, coherence)

* Internal business pages (overview, list, create, catalog, listing editor,
  clients) rebuilt on the app's components: no page-level CSS that restyled
  the whole app (body, inputs with `!important`), dark mode works, phones get
  16px inputs. Public business pages keep their own branding.
* Shared Blade pieces for classic forms: `x-app.panel`, `x-form.input`,
  `x-form.select`, `x-form.textarea`, `x-form.checkbox` (old input only where
  the page opts in, so several forms on one page never share values).
* Business overview shows *What the team can do* (members' skills practised
  there + the business's own) and the business's Needs & Offers, with
  *Publish for this business* for owners/managers only.
* Catalog shows which local categories are linked to a shared category.
* Group home shows the group's Needs & Offers; group managers publish for it.
* Contact center and real-estate workspace were hard-coded Persian; now in all
  four languages (`contact_center.php`).
* Fixed: archiving a client required the market feature; it no longer does.
* Navigation is already intention-based (Needs & Offers, Work, Organizations,
  Money, Content); no routes moved, so no redirects were needed.

## 5e. Implemented in 1.12 (Messaging)

* **One engine.** Private conversations are a Context of kind `direct`
  (`direct_contexts`: exactly two people, lower id first, one per pair) with
  the same `conversations` / `conversation_messages` tables used by groups,
  businesses, deals, agreements and books. Authorization stays in
  `ContextPolicy` for every kind.
* **Privacy.** `users.message_privacy` (everyone / contacts / nobody, default
  contacts) decides who may *start*; anyone may answer someone who wrote to
  them; blocking (`actor_blocks`) stops messages and requests both ways and is
  not announced. Contacts (`actor_contacts`) grant messaging only.
* **Per-person state** (`conversation_participants`): last read, last seen,
  mute, archive, pin; created when someone opens or writes in any chat, so
  group/business/deal chats appear in the same inbox; access is re-checked on
  every read.
* **Messages:** text, replies, edit/delete with the existing revision trail
  (deleted text is hidden, a record is kept), reactions
  (`conversation_message_reactions`, fixed emoji set), photos/videos/files and
  voice notes through the shared Asset pipeline (served only via
  `/messages/{conversation}/files/…` after a view check), static location
  (explicit tap, rounded to 5 decimals, OpenStreetMap link), contact cards
  (own accepted contacts only). `client_uuid` makes repeated sends safe.
* **Delivery without WebSockets:** the open thread asks
  `/messages/{c}/state` for a short version fingerprint (3 s while active,
  10 s idle, paused when hidden, backing off after errors) and refreshes only
  when it changed; the sidebar badge asks `/messages/unread` every 30 s.
* **Notifications** through the existing outbox (in-app + device push):
  never contain message text; not while muted, not while the person is looking
  at the conversation, at most one per conversation per 10 minutes.
* **Reports** (`message_reports`) go to *Administration → Message reports*
  (platform user managers); only the reported message is shown there.
* Search covers the person's visible conversations, current text after edits.
* Honesty: HTTPS in transit and access control at rest — no end-to-end
  encryption claim (stated in the interface).
* New facility **Messages** (`messages`) must be published to people in
  Publication control, like every other facility.

## 6. Release checklist

| Release | Additive schema | Data steps | Acceptance |
|---|---|---|---|
| 1.8 | concept_kinds, concept_suggestions, capabilities, professions.concept_id, relation types narrower/broader, business vocabulary scope | `iet:inventory` (read-only) before; `iet:taxonomy:sync` after (idempotent; also runs on first use) | picker at 320–1920 in 4 languages; suggestion → approve/merge/reject; legacy professions visible as capabilities; legacy rows unchanged |
| 1.9 | publisher party on intents, capability/listing-version/content links, rate/unit decimals | backfill publisher = existing profile actor | person/business/group publish; matches with reasons |
| 1.10 | plan_templates, bk_transactions origin/source | existing entries → manual, adjustments → correction | four-way plan choice; template apply; proposal → confirmation; no duplicate entries |
| 1.11 | none expected | redirects for moved pages | full journeys A–H |
| 1.12 | direct_contexts, actor_contacts, actor_blocks, message_reports, conversation_participants, conversation_message_reactions; conversation_messages kind/meta/client_uuid; users.message_privacy | none | messaging on phones and desktop; publish *Messages* |

Rollback for each release: the previous package plus its database; every
migration is additive, so the previous code runs against the newer schema.
