# M2 — Adaptive Intention Composer: proposal

Oct 24, 2026 · @MrEveryThing

## Decision in brief

**Replace both current editors with one composer page that feels like writing a post: describe it, add whatever details you like, add media, see the preview, then save a draft or publish.** Underneath, the Need/Offer record (`ActorProfileIntent`) stays the single authority. Three things are added: typed *details*, immutable *published versions*, and a *draft* state.

Three rules keep the freedom safe:

1. **Core terms are built in and appear once.** Price, quantity with unit, when, and where each have one control, stored where they are stored today. A custom detail can never act as a second price or quantity.
2. **Custom details are typed but descriptive.** People name them freely. They are validated by type, shown to readers, and found by text search. Only details the system defines can be filtered or matched.
3. **Publishing freezes a version.** Deals point at the version that was published. Later edits create new versions and never rewrite what was agreed.

Money in the composer is **Toman only**, through one formatter and one parser that already exist in the code. The currency selector disappears from the composer; old non-Toman amounts are kept and labelled, never silently converted.

The work splits into six checkpoints on one branch and ships as one usable milestone (section 13).

## What exists today

This was checked on branch `feat/m1-one-home`. The record and the media pipeline are sound; the two editors and the money handling are what need replacing.

| Area | Today | Keep, change or replace |
| --- | --- | --- |
| Record | `ActorProfileIntent`: kind (need/offer), subject and arrangement kinds, title, description, concept, publisher Party, quantity + free-text unit, cash min/max + `currency_code` + basis, location/origin/destination, schedule (once, recurring, window), visibility, status active/paused/closed, links to ability and catalog version | **Keep** as the authority. Add a draft state, versions and details |
| Editors | Two: the 6-step market wizard `Intents\Create` (373 + 261 lines) and the profile editor `Profile\Intents` (532 + 385 lines). Both write through `CreateActorProfileIntent`, `UpdateActorProfileIntent` and `NormalizeProfileIntentData` | **Replace both** with one composer; keep the actions as the write service |
| Media | `ActorProfileIntentMedia` on the shared `Asset` pipeline (`CreateContextAsset`: type and size limits, hashing, video processing). Images and video only, at most 6, ordered, shown only to those who may view the record | **Reuse**; add documents, a per-file "only me" flag and upload progress |
| Typed values | Content already validates typed fields (`SpaceContentSchema`: text, long text, number, date, yes/no, choice; canonical JSON hashing) | **Reuse its validation and hashing** for details |
| Catalog | `BusinessListing` with immutable versions (`structured_data`) and `BusinessPriceVersion` (amount in minor units, basis, monetary unit); `publishListingOffer` creates an intent tied to a listing version | **Keep authoritative**; the composer becomes the publication step |
| Money | Intents store `decimal(18,2)` amounts with free currency codes. Local data holds IET (8) and USD (2), no Toman. The books already use whole-Toman integers (`Toman::parse`, Persian digits accepted). `MonetaryUnitCatalog` offers 8 currencies | **Toman only** in the composer through one presenter; legacy values kept and labelled |
| Search | `Intents\Directory`: fixed filters (kind, subject, arrangement, exchange, area, location) plus `LIKE` text search on title, description, location and labels | **Extend** with whitelisted filters on built-in details |
| Matching | `IntentMatchFinder` compares concept, quantity, place, dates, time, recurrence and cash overlap; cash fails on currency mismatch | **Keep**; it reads the same core columns, in Toman |
| Deals | `relationships.originating_intent_id` points at the live record (4 local deals); there are no versions | **Add** `originating_intent_version_id` |

## The composer experience

**One page, one column of content, and one live preview.** *Describe → Add details → Add media → Preview → Publish* are areas of that page, not steps. Only the first area is open at the start; the others are one tap away.

**Opening.** Eight intention chips sit across the top: *I need something · I offer something · I provide a service · I want to find someone · I have an item to sell · I want to rent something · I want to exchange something · I want to arrange an activity*. One chip is preselected when the page opens from the + launcher, My items or a category. Each chip sets the existing kind, subject and arrangement fields behind the scenes, and can be changed at any time without losing what was written.

**The areas, top to bottom**

1. **Describe** (always open): a title and a description box. The topic (category) is suggested from the words typed and can be changed. That is the whole required form: intention, title or description, and who sees it.
2. **Details**: a list of the details added so far, each shown as *name: value*, plus **+ Add detail**.
   - **Suggested** chips come from the topic and intention (for an electrician: *Service area, Experience, Availability, Certificates, Work samples*). One tap adds a ready-made detail.
   - **Your own detail** asks *what kind?* (Text, Long text, Number, Quantity with unit, Date, Time, Date range, Place, Yes/No, One choice, Several choices, Amount in Toman, Link, File, Photo or video, Something on IET), then a name, then the value.
   - Each detail can be edited in place, moved up or down, grouped under a heading, marked *only me*, or removed. A set of details can be saved as *my template* for next time.
3. **Price and terms** (closed until needed): *Free · Fixed · Per unit · Range · Ask me · Exchange*. It opens by itself for *sell*, *rent* and *service*, and never blocks *need*, *find someone*, *arrange an activity* or anything marked free. It also holds quantity with unit, when, and where, each in its own small row.
4. **Media**: a drop zone for photos, video and documents. Each file shows an upload progress bar, then a thumbnail, a caption and an *only me* switch. Files can be reordered. A failed upload says why and offers *try again*.
5. **Who sees it and publish** (a sticky bar at the bottom): *Only me (draft)*, *Signed-in people*, *Everyone*, plus the two buttons **Save draft** and **Publish**. The publish button always names its audience ("Publish to signed-in people").

**Preview.** On desktop the preview sits beside the composer and updates as you type. It is drawn by the same component as the public page, with a switch *as everyone / as signed-in people* so hidden details visibly drop out. On a phone, two tabs at the top, *Write* and *Preview*, swap the views.

**Returning.** A saved draft appears in My items and Market › Mine as *Draft*. Opening it lands in the same composer. A published post with unsaved edits shows one bar: "You have changes that aren't published — Publish changes · Discard".

&#91;embedded content: composer page on desktop · phone swaps Write and Preview tabs\]

Only *Describe* and the bottom bar are required; every other area can stay closed for a simple need.

## Attribute model: core terms, built-in details, custom details

**Every piece of information has exactly one of three roles, and the role is decided by the system, never by the label a person types.**

| Role | What it is | Where it is stored | Can be filtered or matched? | Can drive a deal? |
| --- | --- | --- | --- | --- |
| **Core term** (transactional) | Price, quantity with unit, when, where | The existing columns on the record (`cash_min`/`cash_max`/`cash_basis`, `quantity`, unit, schedule, location) | Yes | Yes: deal terms copy them from the published version |
| **Built-in detail** (searchable) | A detail the system defines: *service area, years of experience, condition, brand, rooms, floor area, deposit, level, online or in person, session length*… | Detail rows with a canonical key, plus a typed index row when published | Yes, by whitelisted key | No; shown in terms only as information |
| **Custom detail** (descriptive) | Anything a person names themself | Detail rows with no canonical key | Text search only | No |

**Detail rows** (`intent_details`): owner record, canonical key (empty for custom), label, type, value in a typed column (text, decimal with scale, date, date-end, yes/no, choices as a list, reference id), unit, group, position, visibility (*same as the post* or *only me*). Values are validated by type with the same rules Content already uses. Labels are stored as typed, in the author's language, and shown with `dir=auto`.

**Built-in detail catalog.** It is defined in code first, like the category tree, with labels in four languages, type, unit dimension and whether it can be filtered. Topics and intentions map to suggested built-in details: electrical services, laptop for sale, apartment rental, private tutoring and chess partner all get the lists in your brief. Topics without a mapping inherit their area's list. Later the catalog can become admin-editable without a schema change.

**Protecting the money and quantity authority**

- Price and quantity have no *Add detail* entry. They live only in *Price and terms*.
- A custom *Amount in Toman* detail is allowed, for things like a delivery fee or a deposit note. It is shown with the tag *information* and is excluded from matching and deals. The composer says so in one line when it is added.
- A custom label that equals a core term's name in any of the four languages (price, قیمت, السعر, 价格, quantity…) is refused, with a pointer to the right control.
- A built-in detail cannot be renamed into another meaning: its label is fixed, and only a short note beside it can be edited.

**Reusable templates.** *Save as my template* stores the detail names, types, units and order, never the values, against the person. A business saves templates against the business party.

## Media, content references and preview

**No new storage: every file goes through the existing `Asset` pipeline, and media rows stay attached to the record.**

- **Types.** Photos and video are as today. Documents (PDF, images of certificates) are added through the same asset limits list (`MediaLimits`). The cap rises from 6 to 12 files per post.
- **Progress and failure.** Large videos use the resumable upload sessions Content already has (`MediaUploadSession`). Photos use Livewire's upload progress. A failure keeps the draft intact and shows *try again*.
- **Privacy.** A new `visibility` on each media row (*same as post* or *only me*). The media controller already checks the post's view permission and will also refuse *only me* files to anyone but the owner. Scan and rights status from the asset pipeline are respected as today.
- **Links** are a detail type that shows a domain preview; no remote fetching happens on the server.
- **Something on IET** is a reference detail: content, a group or a catalog item the author can see. A viewer sees the reference only if they may see that item; otherwise it drops out silently.

**Preview.** A single presentation builder turns a record into what viewers see. It reads the working copy for the preview and the published version for everyone else. The preview page and the public page share one Blade component, so the preview is exact by construction. A test renders both and compares them.

## Drafts, publishing and versions

**The record is the working copy; publishing freezes an immutable version; the public page always shows the latest published version.** This is the same model the business catalog already uses.

| State | Who sees it | How it changes |
| --- | --- | --- |
| Draft (new status) | Only the author | Every save updates the working copy; nothing is visible |
| Published, no pending edits | The chosen audience sees version *n* | — |
| Published, with pending edits | The audience still sees version *n*; the author sees a bar *Publish changes · Discard* | Publishing creates version *n+1*; discarding restores the working copy from *n* |
| Paused / Closed | Hidden from discovery, with history kept | A direct switch; no new version |

**Version rows** (`intent_versions`): version number, published time and actor. They hold a frozen snapshot of title, description, intention, topic, core terms in Toman, details (with keys, labels, types and values), the media list (asset ids, order, captions, visibility), audience, and a content hash. Rows are immutable after insert, enforced by a model guard and covered by a test.

**Safe direct edits** that need no new version are status (pause, close) and the *only me* switch on a detail or file, because it can only narrow what is shown. Everything else that changes what viewers see waits for *Publish changes*.

**Deals.** A deal opened from a post stores `originating_intent_version_id`. Terms prefill from that version. Editing or republishing the post never touches existing deals; a test proves it.

## Search and matching

**Filters only ever run on built-in keys from the catalog; custom details are searchable as text, never as columns.** That makes unsafe queries impossible by design.

- **Index table** (`intent_search_values`): published version id, built-in key, and one typed value column (decimal, date, date-end, yes/no, choice code, concept id). It is rebuilt in the same transaction that publishes a version, with indexes on *(key, value)*.
- **Directory filters** appear by topic. Choosing *Apartment rental* offers *rooms, floor area, monthly rent*. Each filter maps to a catalog key through a whitelist; request input is a value, never a column or key name, and values are bound parameters.
- **Text search** adds one `search_text` column on the version (title, description, and custom detail names and values in plain text), searched with bound `LIKE` as today. It stays portable across sqlite, MySQL and MariaDB with no engine-specific full-text index. A full-text index can come later if volume needs it.
- **Private details and files** are never written to the index or `search_text`, so they cannot leak through search results.
- **Matching** (`IntentMatchFinder`) keeps comparing core terms, now in Toman only. Two built-in details are added where they are clearly comparable: *service area* against the need's place, and *online or in person*. More can follow without schema changes.

## Toman-only money

**One setting, one parser, one presenter; no currency selector in the composer.**

- `config('market.currency') = 'IRT'` is the single place that names the market currency. A small `MarketMoney` support class wraps the existing `Toman::parse` (accepts Persian and Arabic digits and separators) and formats amounts with the locale's digits and the word *Toman* (تومان / تومان / 土曼).
- New and edited prices are whole Toman and are written with `currency_code = IRT`. The amount columns keep their current type (`decimal(18,2)`), so there is no risky column change.
- **Old amounts in other units** (local data: IET 8, USD 2) are not converted. They show as *"Originally 50 USD — not shown in Toman"* to the owner, are hidden from viewers' price lines, and are left out of matching. Editing the post asks for a Toman price. IRR is exactly 10:1 and is converted on read; the stored value is only rewritten when the owner publishes.
- Bookkeeping and catalog prices keep their own monetary units untouched. The catalog adapter reads Toman and IRR price versions and flags other units in the same way.
- A later currency layer only needs to replace `MarketMoney` and the setting; nothing else assumes Toman.

## Business catalog and property adapters

**One composer interface with an adapter per source; no records merged.**

| Context | Source of truth | What the composer shows | What the composer adds |
| --- | --- | --- | --- |
| Personal need or offer | The post | Everything editable | — |
| Business publication of a catalog item | Catalog listing version + price version | A read-only *From your catalog* card: title, version, price, catalog media. *Edit in catalog* opens the catalog editor | Audience, publication period, quantity offered, publication-only details and media |
| Business need for a client | The business's client record | The client reference (business-only) | The usual post content |
| Property need or offer | The post (personal) or the catalog (business) | Property built-in details: type, floor area, rooms, floor, deposit, monthly rent, amenities | Media from the same pipeline |

Publishing from the catalog stores the catalog version id on the post's published version, as `publishListingOffer` already does. Changing the catalog creates a new catalog version, and the post offers *Use the new catalog version* rather than switching silently. Real-estate `structured_data` maps onto the property built-in details through a fixed mapping, so property posts become filterable without copying ownership.

## Database migration approach

**Additive only; old posts stay readable and editable from the first deploy.**

1. New tables: `intent_details`, `intent_versions`, `intent_search_values` and `intent_detail_templates`.
2. New nullable columns: `actor_profile_intents.published_version_id`; `actor_profile_intent_media.visibility` (default *same as post*); `relationships.originating_intent_version_id`. Status gains the value `draft`, a string column, so no enum change is needed.
3. **Backfill version 1** for every existing active, paused or closed post. Its snapshot is built from the current row, published at the row's creation time. Existing deals from a post get that version id. Old `metadata` keys that the composer understands become detail rows; the rest are kept untouched in `metadata`.
4. Search rows are built for backfilled versions in batches, inside the migration, and idempotently.
5. No column is dropped and no amount is converted. Rollback drops the new tables and columns, and old posts keep working exactly as before.
6. The migration is rehearsed on a MariaDB copy (MyISAM default, legacy timestamps) and on the owner's local data before acceptance.

## Accessibility and localization

- Every control has a visible label. The intention chips and the detail-type menu are real buttons with `aria-pressed` or `aria-expanded`. The preview is announced as a region.
- Reordering works by keyboard with *Move up* and *Move down*. Drag-and-drop is an addition, never the only way.
- Focus moves into a new detail's value when it is added, and back to *+ Add detail* when one is removed. Errors are linked to their fields and summarised at the top of the sticky bar.
- Persian and Arabic digits are accepted in every number and amount. Dates use the existing calendar-aware date input (no native date input, per the existing rule), so Jalali works.
- All fixed text exists in four languages. User text (titles, detail names) is shown with `dir=auto`, so mixed Persian and English stays readable.
- Touch targets are at least 44 px. The sticky bar respects the phone safe area and the phone bar from M1. There is no horizontal scroll at 390 px.

## Acceptance scenarios

Each scenario is a browser journey (Persian and English, 390 px and 1440 px) and an automated test.

| # | Scenario | Passes when |
| --- | --- | --- |
| 1 | Simple need from a description only | Publish works with intention + description; no price, topic or detail asked |
| 2 | Offer with custom details and several photos | Three custom details and four photos are saved, ordered and shown in the preview and public page alike |
| 3 | Service with experience, service area and a Toman rate | Rate entered as ۲٬۱۰۰٬۰۰۰ is stored as 2100000 IRT and shown as "2,100,000 Toman" / "۲٬۱۰۰٬۰۰۰ تومان" |
| 4 | Property offer with specialised details and media | Property built-in details appear as suggestions, are filterable, and video uploads show progress |
| 5 | Free, noncommercial need | *Free* chosen; no price control blocks publishing |
| 6 | Edit, reorder and remove custom details | Order survives a reload; a removed detail is gone from the next version only |
| 7 | Save a draft, resume later | The draft is invisible to others, listed as *Draft*, and reopens with everything |
| 8 | Exact preview | The preview HTML for *everyone* equals the public page HTML for an anonymous viewer |
| 9 | Search and filter on built-in details | *Rooms ≥ 2* and *service area* filters return the right posts |
| 10 | Hostile custom details | Labels and values with SQL, HTML or script do nothing beyond showing as text; unknown filter keys are ignored |
| 11 | Edit a published offer with a deal | The deal keeps version *n* terms after version *n+1* is published |
| 12 | Business publication from the catalog | The catalog card is read-only and the publication stores the catalog version id |
| 13 | Private details and files | *Only me* items are absent for other viewers, from search and from the media URL (403) |
| 14 | Layouts | Persian RTL and English LTR at 390 and 1440, light and dark, with no horizontal scroll |
| 15 | Toman everywhere | No currency selector anywhere in the composer; every shown amount says Toman |
| 16 | Old posts | A pre-M2 post opens, shows correctly, can be edited and republished, and keeps its deals |

## Implementation checkpoints and open questions

There is one feature branch with a commit and push per checkpoint, and one pull request when all six pass. Nothing half-built is shown to you.

1. **Typed details and versions.** Tables, models, validation (reusing `SpaceContentSchema` rules), the built-in detail catalog with four languages, version snapshots, backfill and `MarketMoney`. *Done when* migration and rollback pass on sqlite and MariaDB and old posts render from version 1.
2. **Composer.** One page replacing both editors, with intention chips, Describe, Details (suggested, custom, templates), Price and terms, the sticky bar, and drafts. *Done when* scenarios 1, 3, 5, 6, 7 and 15 pass in the browser.
3. **Media and preview.** Documents, *only me* files, progress and retry, the shared presentation component, and the phone *Write / Preview* tabs. *Done when* scenarios 2, 8 and 13 pass.
4. **Publishing and history.** *Publish changes / Discard*, safe direct edits, and deal version links. *Done when* scenarios 11 and 16 pass.
5. **Search, matching, business and property.** Index rows, topic filters, matcher additions, the catalog adapter and the property mapping. *Done when* scenarios 4, 9, 10 and 12 pass.
6. **Acceptance and hardening.** The full browser run (scenario 14), performance check (directory under 300 ms with 1,000 posts locally), the full test suite, PHPStan, Pint and MariaDB.

**Questions with my default answers** (say if you want otherwise):

- *Templates*: personal and per business only, no sharing between people — default yes.
- *File limits*: 12 files per post, video up to the existing asset limit — default yes.
- *Signed-in-only details*: a detail can be *only me* or *same as the post*; a third level (signed-in only on a public post) is left out for simplicity — default left out.
- *Old non-Toman prices*: left unconverted and hidden from viewers until the owner re-enters them — default yes.
