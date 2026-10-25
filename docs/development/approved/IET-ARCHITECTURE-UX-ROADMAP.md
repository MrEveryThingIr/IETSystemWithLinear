# IET architecture, UX and roadmap — final plan

Oct 24, 2026 · @MrEveryThing

This plan replaces the *final proposal* of the same date as the single reference for implementation. Its domain model stands; this plan corrects four weak points found in the code and adds the user experience, the roadmap and the tests. Rule for every screen: **the system carries the complexity, the person sees the next step.**

## A. Revised architecture

The seven domains, the journey (Items → Market → Deal → Deal account → Records) and Referrals as a later domain all stand unchanged. A second check of the code against the proposal found six statements that were too strong or too costly. Each is corrected here, and the correction replaces what the proposal said.

| # | What the proposal said | What the code shows | Correction (now binding) |
| --- | --- | --- | --- |
| 1 | Double counting is "impossible by construction" | `bk_transactions` is unique on *(recorded\_by\_actor\_id, idempotency\_key)* only, and the source index (`bk_tx_source_idx`) is not unique. Two different people could post the same source twice; only application code prevents it | Add a stored generated column `live_source_key` (the source key while the entry is live, otherwise NULL) with a **global unique index**. Over-allocation is blocked inside one database transaction that locks the owed rows (`lockForUpdate`, InnoDB). Section F lists the tests that prove both |
| 2 | Done happens "automatically when the end date passes and nothing is open" | No such rule exists yet | **Removed.** A date never closes anything. After the end date the deal shows *Ready to finish?* to both parties. *Finished* needs both to confirm and no open delivery or dispute. Money is separate: a deal can be *Finished · 2,200,000 still owed*, and its account stays open until settled |
| 3 | Merge Proposal and Contract into a new Terms chain | `ContractVersion` already has versions, supersession, effective dates, per-party acceptance and activation, and is the more mature of the two | **Keep the contract chain as the single Terms chain.** A deal gets its contract when the first terms are sent; a version is *offered, accepted, superseded or withdrawn*. Proposal tables are migrated into it and retired, and no new table is built |
| 4 | Personal and business books show "From deals" | `bk_ledgers.owner_actor_id` is a person only, so a business cannot own a book yet | Book ownership moves to the `Party` columns before any business book appears. Ordered in the roadmap (M4) |
| 5 | Navigation Today · Messages · Market · Deals · Planner · Bookkeeping · Organizations · Content | The live sidebar is Messages · Needs & offers · *Work* (deals **and** planner together) · Organizations · Money · Content | Planner becomes its own destination, so a personal plan never sits under "Work". *Money* (not "Bookkeeping") stays as the label people already know. Section B has the final menu |
| 6 | Records show deliveries the owner reported | Showing unconfirmed lines on a shareable page invites misreading | Unconfirmed lines appear only on the owner's own view, as *waiting for confirmation*, and can never be shared |

Two things were checked and confirmed sound. The database connection forces InnoDB (`config/database.php`), so the host's MyISAM default does not apply. This still needs verifying on the production tables during the next rehearsal. Planner reports already link to deliveries by `plan_occurrence_id`, so the planner link needs no new table.

## B. Human-centered UX architecture

**Every screen answers four questions in this order: what is happening now, what do I do next, what is already agreed, and what is still open.** Five rules make that hold everywhere:

1. **One home per fact.** Each fact is edited in exactly one place. Elsewhere it appears as a summary with an *Edit* link that returns to where the person came from.
2. **Intentions first.** People start from a sentence ("I need someone…"), never from an object type.
3. **One next step.** Each deal, plan or book shows at most one highlighted action for the current person. Everything else is secondary.
4. **Defaults, then detail.** The simple form is complete and safe on its own. Advanced fields open in place, on the same record.
5. **Nothing consequential without consent.** The system may prefill, suggest and calculate. Only a party can accept terms, confirm work or confirm money, and the button says exactly what it commits to.

### Navigation

| Where | Items | Why |
| --- | --- | --- |
| Sidebar, desktop (6) | Today · Messages · Market · Deals · Planner · Money | The six daily destinations; each owns one kind of task |
| Sidebar, lower group | Businesses & groups · Learn (Content) | Places people go into, less often |
| Account menu (avatar) | Me (profile, my items, records, sharing) · Vault · Settings · What's new | Personal things kept out of the work menu |
| Mobile bottom bar | Today · Deals · Planner · Messages · More | Thumb reach; *More* holds the rest in the same order |
| Everywhere: **+ button** | "What would you like to do?" | The intention launcher below |

The intention launcher offers eight plain sentences, and each opens the one right form:

| Sentence | Opens |
| --- | --- |
| I need something or someone | New Need |
| I can offer something | New Offer, which starts from an Ability or creates one |
| I know how to do something | New Ability |
| I'm interested in something | New Interest |
| I want to plan something | New plan in the Planner |
| I want to record money | My book, or a shared entry with a person |
| I want to make an arrangement with someone | New deal with a chosen person or business |
| I want to introduce someone | Invite or introduce (later, with Referrals) |

The old duplicate entry points are removed: the *What I can do* menu link, profile tags, the profession page and the Work hub. Their routes redirect to the one home.

### Screen responsibilities

| Screen | Its one reason to exist | Main action |
| --- | --- | --- |
| Today | What needs me today, across all domains | The first waiting item |
| Market | Find people, offers and needs, and publish my own | Contact or Start a deal |
| Deals | All my arrangements, grouped by *needs me*, *working*, *talking*, *finished* | Open the deal that needs me |
| Deal page | One arrangement from first talk to final record | Its single next step |
| Planner | My plans and routines, with or without deals | Report today's items |
| Money | My books and every balance I am part of | Record or confirm |
| Me › My items | Interests, abilities, offers and needs in one list | Add (via the launcher) |
| Me › Records | What I can prove, and who can see it | Share |
| Business admin | Run the business: catalog, clients, intake, portal, and its deals and money | Depends on the tab |

### The deal page

The stages people see are **Talking · Agreed · Working · Finished**, adopted from your review. They appear as a small stepper under the title.

- **Header:** the other party, the stage, and one *Next step* card. Examples: "Review Sara's new terms", "Confirm 2 deliveries", "Sara says she paid 2,000,000. Did you receive it?"
- **Tab: Overview** (default). The agreement in three lines (what, how much, when), a work progress bar, the money summary, and open items.
- **Tab: Work.** Schedule and deliveries, each with its status and a confirm or query button.
- **Tab: Money.** This deal's account (wording below).
- **Tab: Terms.** The current agreement, and the version history behind *Show earlier versions*.
- **Tab: Messages.** This deal's conversation.

Amendments, disputes and evidence appear in place, inside the tab they concern, when they exist. The chosen tab is kept in the URL, so *Back* returns to it.

### Money words: one meaning each, the same on every screen

| Word | Meaning | Calculated from |
| --- | --- | --- |
| Expected | Agreed but not yet earned | Agreed total minus earned (fixed-quantity deals only) |
| Earned | Work the payer accepted | Sum of owed entries |
| Paid | Payments the payee confirmed | Sum of confirmed payment allocations |
| Remaining | Earned minus paid | Calculated, never stored |
| Waiting for you | A payment or delivery the other side reported | Pending entries addressed to me |
| Disputed | Held apart until resolved | Owed entries in dispute; excluded from Remaining |
| Next settlement | When the next statement is due | Settlement cycle in the terms |

Units are shown beside money when the deal is per unit: "6,300,000 of 21,000,000 paid · 3 of 10 days settled".

### Progressive disclosure

| Flow | Simple form (complete and safe) | Revealed when needed |
| --- | --- | --- |
| New deal terms | What, quantity and unit, price, start date, payment timing (*after each day, weekly, monthly, at the end*) | Custom units, milestones, acceptance rule, notice to end, schedule details |
| Offer | Title, category, asking price (type + amount), who can see it | Price range, unit definition, availability, media |
| Delivery | "Done today?" with times prefilled from the plan | Quantity changes, attachments, corrections |
| Payment | Amount and date; settles the oldest remaining first | Choose which days or items it settles; wallet |
| Business deal | Signed as the business automatically when I'm its owner or manager | Choosing a signer, staff permissions |
| Records | "Share" with a person or link | Per-line selection, evidence detail, counterparty consent status |

### Feedback, errors and return paths

- Every confirm action shows a one-line result in place ("Day 4 confirmed · 2,100,000 added to earned"), plus *Undo* where undo is honest. Undo is only offered for pending items, never for confirmed money.
- A refused action explains why and what would make it possible ("Only an Atlas manager can accept these terms; Mehdi has been asked").
- Every empty state carries one sentence and the one action that fills it.
- Small edits open in a drawer over the page, never on a new page. Pages reached from elsewhere show *Back to {origin}*.

## C. Domain-to-UI mapping

Each domain has **one home**, where its records are created and edited. Everywhere else it appears as a read-only **projection** with a link home. A projection never stores its own copy.

| Domain | Home (edit here only) | Shown as a projection in | What the projection shows |
| --- | --- | --- | --- |
| Items | Me › My items | Market (my offers' source ability), Business admin › Team abilities, Today (suggestions for my interests, only to me) | Ability name and level; interest purposes, owner only |
| Catalog | Business admin › Catalog | Public portal `/b/{slug}`; Market offers; Deal terms (frozen summary) | Item title, version, price version |
| Market | Market (my needs and offers are under *Mine*) | Me › My items; Business admin › Published; Today (new matches) | Status, audience, responses |
| Deals | Deal page | Today (next steps); Business admin › Deals (filtered to the business); Planner occurrence ("part of deal"); Money (deal accounts) | Stage, next step, other party |
| Planner | Planner | Today (today's items); Deal › Work (bound plan's schedule and times only) | Times and status; never private fields |
| Bookkeeping | Money (my books); Deal › Money (deal account); Business admin › Money (business books) | Today (payments waiting for me); Personal and business books (*From deals*, read-only) | Earned, paid, remaining, waiting |
| Records | Me › Records | Shared record page (the grant's audience only); Market profile card (only lines shared with the market) | Confirmed facts with evidence level |
| Referrals (later) | Me › Introductions | Deal ("introduced by", with consent); Money (rewards in their referral deal) | Introduction status; reward status |

Deal › Money and Business admin › Money are homes of the *same* bookkeeping domain, shown in different places for convenience. They edit the same records and share one component, so their rules cannot drift apart. In a book, a *From deals* line is visibly different: grey, with a link icon and the label "Counted in the deal account". The book's own total sits above these lines, and their separate total sits below them, under its own heading.

## D. Workflows

Thirteen journeys are covered, each in two tables. The first table is what the person experiences; the second is what the system guarantees. The drawing at the end follows one journey across every domain.

### What the person experiences

| # | Workflow | Real goal | Simplest journey | Asked | Inferred safely | Where |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Create an Ability | "I know how to repair computers" | + › I know how to do something › pick category › level › Save | Category, level | Visibility: only me; years: blank | Me › My items |
| 2 | Create an Interest | "I enjoy chess" | + › I'm interested… › category › tick purposes › Save | Category, purposes | Private; suggestions shown afterwards | Me › My items |
| 3 | Publish an Offer | "I provide repairs" | + › I can offer… › choose ability (or add) › title, asking price › Publish | Title, price type and amount, audience | Category and unit from the ability; currency toman | Market › Mine |
| 4 | Publish a Need | "Fix my laptop" | + › I need… › what, by when, budget (optional) › Publish | What, category | Location from profile, if shared; audience: signed-in users | Market › Mine |
| 5 | Find and contact | "Find a welder" | Market › search or matches › profile card › Message, or Start a deal | Nothing until the message | Matches from category and place | Market, Messages |
| 6 | Negotiate | "Agree a price" | Deal › Terms › edit three lines › Send | What, quantity, price, start, payment timing | Prefilled from the offer or need and the price version | Deal › Terms |
| 7 | Accept terms | "Say yes" | Next-step card › review summary › *Accept and agree* | One confirmation | Signer and authority from my role | Deal header |
| 8 | Schedule and work | "Put the days in my calendar" | Deal › Work › *Schedule it* › confirm the suggested plan | Confirm or adjust times | Plan from the terms' schedule | Deal › Work, then Planner |
| 9 | Confirm deliveries | "Yes, he came today" | Today or Deal › *Confirm* (or *Query*) | One tap; reason if querying | Amount from the agreement version | Today, Deal › Work |
| 10 | Record and confirm payment | "I paid him" / "I got it" | Payer: *I paid* › amount, date. Payee: *Received?* › Yes or No | Amount, date | Settles oldest remaining first | Deal › Money, Today |
| 11 | Business catalog and intake | "Show my services; take requests" | Business › Catalog › add item › Publish to portal and/or Market. Intake: Leads › *Open a deal* or *Publish need* | Item details, price | Signer = me as manager | Business admin |
| 12 | Review my records | "Show what I've done" | Me › Records › pick lines › Share with… | Audience | Only confirmed lines are shareable | Me › Records |
| 13 | Introduce someone | "Ali should meet Atlas" | Deal or profile › Introduce › choose both › Send | The two parties, a note | Consent requested from both | Me › Introductions (later) |

### What the system guarantees

| # | Advanced, on demand | Failure → recovery | Domain responsibility | Invariant | Observable acceptance |
| --- | --- | --- | --- | --- | --- |
| 1 | Years, confidence, notes, sharing | Duplicate category → "You already have this" + open it | Items | One ability per holder and category | Same category twice opens the existing one |
| 2 | Visibility per interest | None needed | Items | Private by default | A second account cannot see it in any page or API |
| 3 | Range, unit definition, media, availability | Price missing → "Choose *on request* or enter an amount" | Market; price versions | Editing creates a version | Changing price leaves existing deal terms unchanged |
| 4 | Schedule, location detail | No match yet → empty state with *Notify me* | Market | Audience respected | Hidden from a non-audience account |
| 5 | Filters, saved searches | Blocked person → not shown | Market, Messaging | Blocking hides both ways | Search never lists a blocker |
| 6 | Units, milestones, acceptance rule, notice | Conflicting edit → "Sara sent a newer version" + compare | Deals | Sent versions are frozen | Old version cannot be accepted once a newer one is sent |
| 7 | Signer choice for businesses and groups | Not authorised → names who can sign and notifies them | Deals + `PartyAuthority` | No account signs both sides | Self-sign on both sides is refused |
| 8 | Custom recurrence, reminders, attach existing plan | Clash with another plan → shown before saving | Planner | Planner never writes deal data | Plan report creates only a *draft* delivery |
| 9 | Partial quantity, attachments | Query → provider sees reason, corrects or disputes | Deals | Payer confirms delivery | Owed entry appears only after confirmation |
| 10 | Pick items to settle, wallet, in-kind | Payee says *No* → payment marked not received; both see it | Bookkeeping | Payee confirms payment; no over-allocation | Remaining never below zero; "n of m" exact |
| 11 | Staff permissions, price histories, intake fields | Lead without contact → cannot convert until reachable | Catalog, Business; Deals for deals | Business is the party; staff is signer | Portal shows no IET chrome |
| 12 | Per-line evidence, consent status | Counterparty refused naming → shown as "a business in Construction" | Records | Private by default; no scores | A revoked share link returns *not available* |
| 13 | Programme terms (with funders) | Pre-existing relationship → "not eligible", reason shown | Referrals | Settled money only; no hidden deduction | Reward shows pending → payable → settled or reversed |

### One journey across domains

&#91;embedded content: hiring a worker · person, page and domain per step\]

The person moves between two pages while five domains record the facts underneath. The deal page composes Planner times and Money figures as projections, so nothing is entered twice.

## E. Implementation roadmap

**Eight milestones. Each one ends with a journey a person can complete in the browser.** Each is a feature branch with one pull request into `integration/v2`, merged only after you test it locally and accept it. Production is never touched by a milestone. Release and deployment remain separate decisions.

The gate every milestone must pass, in addition to its own criteria:

- **Automated:** PHPUnit, PHPStan with 0 errors, Pint, and the MariaDB smoke run.
- **Browser:** the milestone's journeys run end to end in Persian (RTL) and English (LTR), at 390 px and 1280 px, in light and dark themes, with keyboard-only operation and visible focus. Screenshots are attached to the pull request.
- **States:** every new screen has an empty state, a loading state, an error state and a success message.
- **Language:** all text in four languages. No internal word (proposal, contract, obligation, fulfilment, ledger, intent) appears anywhere on screen.

### M1 · One home for me: navigation and My items

- **User sees:** the new six-item menu, the + launcher, a mobile bottom bar, Planner as its own destination, and *Me › My items* holding interests and abilities (offers and needs listed and linked).
- **Domain:** a new Interest model; Ability (`Capability`) becomes the only skill record.
- **Reuse:** `Capability`, `SaveCapability`, the categories picker, `ExperienceNavigation`, `PartyAuthority`.
- **Retire:** the profession page, `ActorProfession` (after the copy is verified), the has-skill, interested-in, wants-to-learn, needs and offers tags in `ConceptAssertion`, the *What I can do* menu link, and the Work hub. Old routes redirect.
- **Database:** a new `interests` table; a copy-and-verify migration; no data is deleted in this milestone.
- **Privacy:** interests are visible only to their owner, and abilities default to *only me*.
- **Depends on:** nothing.
- **Done when:** section G's scenarios pass in the browser.
- **Risk and rollback:** the copy is additive and the old tables stay, so rollback is a route switch.

### M2 · Offers and needs that speak plainly

- **User sees:** offer and need forms launched from intentions; *Asking price* with types; units suggested by category; price history on the offer.
- **Domain:** a Unit registry; price types; offer price versions.
- **Reuse:** `ActorProfileIntent`, `CreateActorProfileIntent`, `IntentMatchFinder`, `BusinessMarketService`.
- **Consolidate:** `RateBasis` and free-text units become Units; `ProfileIntentExchangePreference` maps onto price types; the profile page's needs-and-offers editor merges into Market › Mine.
- **Database:** `units` and `offer_price_versions` tables; a nullable `unit_id` column; backfill from the existing values.
- **Privacy:** the chosen audience is respected in search and matches.
- **Depends on:** M1.
- **Done when:** journeys 3–5 pass, and a price edit creates a version that leaves any terms unchanged.
- **Risk and rollback:** new nullable columns; the old columns stay read until M5.

### M3 · Deals: talking and agreeing

- **User sees:** the Deals list grouped by *needs me*; a deal page with stepper, next-step card, Overview, Terms and Messages; the simple terms form; *Accept and agree*.
- **Domain:** Deal (`Relationship`) gets `deal_parties` with Party columns and signer snapshots; contract versions become the single Terms chain; per-action authority with the conflict rule.
- **Reuse:** `CreateContractVersion`, `AcceptContractVersion`, `FinalizeContractVersionAcceptance`, `ProposeContractAmendment`, `ContractServiceTerm`, and the conversation engine.
- **Retire:** Proposal screens and actions, `DealPipeline`'s six steps, and separate Proposal and Contract menu entries (migrated, then removed).
- **Database:** `deal_parties`; a version status *offered or withdrawn*; Party columns on the contract parties; a migration that turns proposals into contract versions with full history.
- **Authorization:** only parties see a deal; only authorized signers accept; no account accepts both sides.
- **Depends on:** M2 for prefilling, though it can start in parallel.
- **Done when:** journeys 6–7 pass, including a business signer and a refused self-sign.
- **Risk and rollback:** this is the largest migration. It is rehearsed on a copy of real data first, and the old tables stay until M5 is accepted.

### M4 · Working: schedule, deliveries, Today

- **User sees:** Deal › Work; *Schedule it*; plan reports offered as draft deliveries; *Confirm* and *Query* buttons on Today and on the deal.
- **Domain:** What's due (`Commitment`) and Delivery (`Fulfillment`) with Party columns; the planner event becomes a draft delivery.
- **Reuse:** `BootstrapContractServiceWorkflow`, `SubmitFulfillment`, `ReviewFulfillment`, `CommitmentPlanBinding`, `HomeTodayProjection`, the planner's `TransitionPlanOccurrence`.
- **Database:** Party columns on commitments; a generated unique key for live deliveries per occurrence.
- **Privacy:** no planner field beyond times and status crosses into deals (tested at the endpoint).
- **Depends on:** M3.
- **Done when:** journeys 8–9 pass for the welder scenario's 10 days.
- **Risk and rollback:** additive; planner behaviour is unchanged without a deal.

### M5 · Money that adds up

- **User sees:** Deal › Money with the money words; *I paid* and *Received?*; "n of m days settled"; statements; *From deals* in My book; *Ready to finish?*
- **Domain:** ledger kind *deal*; book ownership by Party; confirmed owed posting; allocation service; statements.
- **Reuse:** the whole bookkeeping module, `EntrySource`, `ServiceCompensation`, and `ProposeWorkBookkeepingEntry`, refactored into a posting service.
- **Retire:** the proposed-debt path for deal work.
- **Database:** `live_source_key` with its unique index; owner Party columns on `bk_ledgers`; a `bk_statements` table.
- **Invariants:** F1–F7, all tested.
- **Depends on:** M4.
- **Done when:** the welder scenario reproduces exactly: 16,700,000 of 18,900,000 paid, 7 of 9 days settled and 1 partly, and no total counts anything twice.
- **Risk and rollback:** a new unique index needs a pre-check for existing duplicates; the migration refuses to run if any are found.

### M6 · The business runs on it

- **User sees:** Business admin gains Deals and Money tabs; *Open a deal* from a lead or client; catalog item to offer in one step; staff permissions.
- **Domain:** a business party across deals and books; Lead conversion.
- **Reuse:** all business services, `publishListingOffer`, `publishClientNeed`, `PublicIntakePortal`.
- **Database:** a lead status on business contacts; staff action permissions.
- **Authorization:** the business party, staff signers and the conflict rule.
- **Depends on:** M5.
- **Done when:** journey 11 passes from portal intake to a paid delivery, and the portal still shows no IET chrome.
- **Risk and rollback:** permissions default to owner and managers only.

### M7 · Records

- **User sees:** Me › Records with confirmed lines, a review at deal end, and sharing.
- **Domain:** a records projection, reviews, and consent to naming the counterparty.
- **Reuse:** `ActorProfileDisclosureGrant` and the profile sharing pages.
- **Database:** `deal_reviews`, consent flags, and an optional projection cache.
- **Privacy:** private by default; no scores; no planner data.
- **Depends on:** M5.
- **Done when:** journey 12 passes, and a revoked share is unreachable.
- **Risk and rollback:** read-only projection, so it can be switched off.

### M8 · Introductions, then rewards

- **User sees:** first *Introduce*, with consent and status; later, rewards with clear status.
- **Domain:** Referrals: introductions, referral deals, entitlements.
- **Reuse:** `AccessInvitation`, `GroupInvitation`, Deals and Bookkeeping.
- **Database:** `introductions` and `entitlements`.
- **Depends on:** M5. **Money rewards also need legal approval**, so introductions ship first and rewards stay off until then.
- **Done when:** journey 13 passes, and the 334,000 reward is computed once, survives replay, and reverses on refund.
- **Risk and rollback:** rewards sit behind a feature switch that is off by default.

## F. Invariants and regression tests

Each rule is enforced at the strongest layer available: the database first, then a locked transaction, then code. Each rule has a named test that runs on sqlite in CI and on MariaDB in the smoke run. "By construction" is claimed only where the database itself refuses the violation.

| ID | Rule | Enforced by | Regression test (proves it) | From |
| --- | --- | --- | --- | --- |
| F1 | One live entry per workflow source, across all books | Unique index on generated `live_source_key` | `PostingIdempotencyTest`: post the same delivery twice, from two actors, in two requests → one row; voiding frees the key for a correction | M5 |
| F2 | Payments never allocate more than an owed entry's remaining amount | One transaction; `lockForUpdate` on the owed rows; sum check | `AllocationConcurrencyTest`: two allocations race on MariaDB → one succeeds and remaining ≥ 0 | M5 |
| F3 | A book's total includes only its own entries | Totals query filters on `ledger_id`; *From deals* inserts no rows | `LinkedLinesNotCountedTest`: business book total unchanged after the deal account posts | M5 |
| F4 | Owed entry only after the payer's confirmation | Posting service requires an accepted review id; FK to it | `OwedRequiresAcceptanceTest` | M5 |
| F5 | Payment counts only after the payee's confirmation | Status machine; confirmation unique per transaction and party (exists) | `PaymentConfirmationTest` | M5 |
| F6 | Money is never edited | Model guard on lifecycle fields (exists in `Transaction`); corrections are linked rows | `MoneyImmutabilityTest` | M5 |
| F7 | Replaying events changes nothing | F1 + idempotent handlers | `WelderScenarioReplayTest`: run the 10-day scenario, replay every event, compare totals | M5 |
| F8 | Nothing binds before every required acceptance | Version status machine; activation only in `FinalizeContractVersionAcceptance` | `NoAgreementFromTalkTest`: messages and drafts create no commitment or entry | M3 |
| F9 | Sent terms and agreements are frozen | Model guard; terms text is a content revision | `FrozenTermsTest`: editing the offer or catalog after acceptance leaves the terms unchanged | M2–M3 |
| F10 | No account signs both sides; signer authority is snapshotted | `PartyAuthority` + conflict check in the accept action | `SelfDealingRefusedTest`, `AuthoritySnapshotTest` | M3 |
| F11 | Planner never decides fulfilment or money; private fields never leave | Deals reads only id, status and times; API resource whitelist | `PlannerPrivacyBoundaryTest`: deal endpoints and pages never contain reason, note, difficulty, habit or urge | M4 |
| F12 | A date never finishes a deal | Finish requires both confirmations and no open items | `NoSilentFinishTest`: time-travel past the end date → still Working, *Ready to finish?* shown | M5 |
| F13 | Private by default; sharing is explicit and revocable | Policies + default values | `PrivacyDefaultsTest` for interests, abilities and records; `RevokedShareTest` | M1, M7 |
| F14 | No score or inferred unreliability | Absence of any such field; review is attributed text | `NoScoreTest` (schema and rendered pages) | M7 |
| F15 | Rewards only from settled money, once, reversible; never deducted from the invitee | Unique *(referral deal, allocation, version)*; funder Party fixed by referral terms | `RewardOnceTest`, `RewardReversalTest`, `NoHiddenDeductionTest` | M8 |
| F16 | One home per fact in the UI | Route and form inventory | `SingleFormPerRecordTest`: each model has exactly one create and edit route | M1 onward |
| F17 | No internal words on screen | Language-file scan | `NoInternalWordsTest`: proposal, contract, obligation, fulfilment, ledger and intent absent from user strings in four languages | M1 onward |

## G. First milestone in detail: M1, one home for me

**Goal:** after M1, a person can say what they can do and what interests them in exactly one place, reach it from anywhere through the + launcher, and find Planner without going through "Work". It starts first because duplicate skill entry points were the confusion you named most, and every later milestone builds on its navigation and launcher.

### Files and services (checked in the repository)

| Area | Existing file | Change |
| --- | --- | --- |
| Menu | `app/Services/Surfaces/ExperienceNavigation.php` | Primary becomes Today, Messages, Market, Deals, Planner, Money; lower group Businesses & groups, Learn |
| Menu views | `resources/views/components/app/sidebar.blade.php`, `navbar.blade.php`, `user-menu.blade.php` | Six items; + launcher; account menu gains *Me*; the *What I can do* link is removed |
| Mobile | (none today) | New bottom bar component: Today, Deals, Planner, Messages, More |
| Launcher | (new) `app/Livewire/Launcher.php` + view | Eight sentences; each opens its one form in a drawer |
| Work hub | `ExperienceHubController::work`, route `experience.work` | Route redirects to Deals; Planner gets its own menu item |
| My items | `app/Livewire/Profile/Capabilities.php` (204 lines) | Grows into *Me › My items*: Interests, Abilities, then Offers and Needs listed with links to Market |
| Ability save | `app/Actions/Capabilities/SaveCapability.php`, `app/Models/Capability.php` | Reused unchanged; uniqueness per holder and category added |
| Interest | (new) `app/Models/Interest.php`, `app/Actions/Items/SaveInterest.php`, `app/Policies/InterestPolicy.php` | Owner only; purposes: follow, learn, enjoy, work in, join others, create |
| Profile tags | `app/Livewire/Profile/Semantics.php`, `ConceptAssertion` (predicates) | has\_skill → Ability; interested\_in and wants\_to\_learn → Interest; needs and offers removed from the profile page; content predicates untouched |
| Profile page | `resources/views/livewire/profile/manage.blade.php` (embeds Semantics and Intents) | Stops embedding them; shows a short summary with *Edit in My items* |
| Old skills | `ActorProfession`, `CopyLegacyProfessions`, route `profile.professions.index` | Copy verified by the migration; the route redirects to My items |
| Business team | `app/Livewire/Business/MemberCapabilities.php` | Unchanged; shows a link to each member's abilities only where shared |
| Language | `lang/*/experience.php`, `capabilities.php`, new `items.php` | Plain words in four languages |

**Database:** a new `interests` table: uuid, `holder_actor_id`, `concept_id`, `purposes` (JSON set), visibility (default *only me*), status and timestamps, unique on *(holder, concept)*. There is also a migration *copy profile tags and professions into Interest and Ability*. It is idempotent, reports its counts, and deletes nothing. The `capabilities` table gains a unique *(holder, concept)* key after the duplicates are merged; the migration lists any duplicates instead of guessing.

### Expected UI behaviour

1. The + button sits at the top of the sidebar on desktop and in the bottom bar on mobile. It opens a sheet of eight sentences.
2. *I know how to do something* opens a drawer with a category search and a level picker (beginner, intermediate, advanced, expert). It saves in one tap and shows "Added to My items · visible only to you" with a *Share* link.
3. *I'm interested in something* shows the category and six purpose chips, with none preselected; saving needs at least one. The result reads "Only you can see your interests."
4. My items shows four short sections, Interests, Abilities, Offers and Needs, each with its count and an *Add* button. An empty section shows one sentence and the add button.
5. Choosing a category already in use opens the existing item, with a note: "You already have this. Change it here."
6. An interest with purpose *learn* shows up to three suggestions only to its owner: courses and groups in that category. If there are none, it shows nothing rather than an empty box.

### Acceptance scenarios (run in the browser, in Persian and English, desktop and mobile)

| # | Given | When | Then |
| --- | --- | --- | --- |
| 1 | A user with old profession "welder" and a has-skill tag for welding | They open My items | One ability *Welding*, at the old level, appears exactly once |
| 2 | A user with interested-in and wants-to-learn tags for chess | They open My items | One interest *Chess* with purposes *follow* and *learn* |
| 3 | Any user | They tap + › I'm interested…, pick *Programming*, tick *learn* | It appears in My items; a second account cannot find it anywhere (page, search, API) |
| 4 | A user with ability *Welding* | They add *Welding* again | The existing ability opens; no duplicate is created |
| 5 | Any user | They visit `/profile/professions` or `/work` | They are redirected to My items or Deals, with no error |
| 6 | Any user, phone width | They use the bottom bar | Today, Deals, Planner, Messages and More are reachable; nothing scrolls sideways; Persian is right to left |
| 7 | Any user | They scan every page reachable in the menu | None shows profession, has skill, intent, proposal or contract (F17) |
| 8 | Planner user with no deals | They open Planner from the menu | Their plans open directly, with no "Work" step |

**Not in M1:** offer and need forms keep working exactly as today until M2. My items only lists and links them, so nothing is half-moved.

## H. Readiness

**Ready to start M1 on your acceptance.** Nothing in M1 depends on an open decision below.

| Status | Decision | Note |
| --- | --- | --- |
| Frozen | Seven domains + later Referrals; one home per fact; Party ownership with signer snapshots | Changes need a new review |
| Frozen | Contract versions as the single Terms chain; nothing binds before every acceptance | Correction A3 |
| Frozen | Owed entry posted confirmed after payer acceptance; payee confirms payments; accrue per delivery, settle per period | Tests F1–F7 |
| Frozen | No automatic finish; Finished (work) is separate from settled (money) | Correction A2 |
| Frozen | Navigation: six items + launcher + mobile bar; Planner independent; *Money* label | Correction A5 |
| Frozen | Records private by default; no scores; no planner data beyond deal-bound times |  |
| Open, with default | Delivery acceptance by silence after N days | Default: off; explicit confirmation only |
| Open, with default | Currencies other than toman in books | Default: toman only |
| Open, with default | Records for businesses and groups | Default: people only until M7 is accepted |
| Open, with default | Default audience for offers | Default: signed-in users |
| Open, with default | Allocation order | Default: oldest first, payer may choose |
| Needs approval later | Money rewards for referrals (tax, commission, consumer disclosure, anti-pyramid, privacy) | Legal review per jurisdiction before M8 rewards switch on |
| Needs approval later | Advances: pre-paid work and loans against future work | Risk owner, limits and a lending-law check; not planned |
| Needs approval later | Production: each release, its migrations, backup and rollback | Separate authorisation, as always; rehearse M3 and M5 migrations on a copy of real data first |
| To verify | Production tables use InnoDB, which transactions and locks require | Check during the next upgrade rehearsal (`SHOW TABLE STATUS`) |
