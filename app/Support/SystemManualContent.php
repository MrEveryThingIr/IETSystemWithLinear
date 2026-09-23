<?php

namespace App\Support;

class SystemManualContent
{
    public const REFERENCE_KEY = 'system-manual';

    public const ROOT_TITLE = 'IET System Manual';

    /** @var array<string, string> */
    public const CHAPTER_TITLES = [
        'mental-model' => '1. The IET Mental Model',
        'identity' => '2. User, Actor, and Identity',
        'groups' => '3. Groups, Memberships, Roles, and Permissions',
        'contexts' => '4. Contexts: Where Work Happens',
        'content' => '5. Content, Definitions, and Blueprints',
        'reader' => '6. Reader, Studio, Blocks, Assets, and Annotations',
        'evidence' => '7. Publishing, Editions, Permalinks, and Evidence References',
        'admission' => '8. Invitations and Admissions',
        'submissions' => '9. Submission, Response, and Evaluation',
        'roadmap' => '10. Connected-Life Target and Roadmap',
        'feedback' => '11. Reporting Questions, Problems, Corrections, and Ideas',
        'profile-concepts' => '12. Profile, Concepts, Skills, Interests, Goals, Needs, and Offers',
        'agreements' => '13. Group Agreements and Versioned Governance',
        'ai-assistance' => '14. AI-Assisted Authoring and Development Origins',
    ];

    /** @return array{summary: string, chapters: list<array{title: string, summary: string, current_behavior: string, how_to_use: string, authorization: string, ideal_target: string, misunderstandings: string}>} */
    public function english(): array
    {
        return [
            'summary' => <<<'TEXT'
This manual explains how IET works today, why each object exists, how to use the current product efficiently, and how the implemented system is intended to evolve toward the connected-life platform described by the project compass.

Every chapter deliberately separates current implemented behavior from ideal target behavior. Use the clean Reader when you only want the official origin. Use annotations when you want to ask a question, report a correction, record a private note, or propose an improvement against an exact section of an exact edition.
TEXT,
            'chapters' => [
                [
                    'title' => '1. The IET Mental Model',
                    'summary' => <<<'TEXT'
IET is a coordination platform, not a collection of unrelated apps. Identity, Groups, Contexts, Content, plans, agreements, work, evidence, money, learning, and collaboration are intended to form one connected graph while each domain keeps the rules needed to make its facts trustworthy.
TEXT,
                    'current_behavior' => <<<'TEXT'
Today the strongest implemented foundations are User/Actor identity, Group governance, Membership and permissions, invitations and Admission, Personal/GroupSpace/Admission Contexts, versioned Content with Blueprints and immutable published revisions, annotations/evidence locators, and Phase 7 Submission/Response/Evaluation.

Later kernels such as Conversation-first collaboration, Planner, negotiated Contract/Commitment/Fulfillment, and Accounting are not yet fully implemented. The current product should therefore be understood as a growing platform kernel rather than the final connected-life experience.

For the first invitation-only office alpha, the normal navigation is intentionally smaller than the full implemented kernel. Ordinary users see Dashboard, Needs/Offers/Services, Profile, and contextual Help; authorized platform administrators also see Access Invitations. Groups, Content authoring, audit surfaces, and other advanced kernels remain implemented but are not promoted in normal alpha navigation.
TEXT,
                    'how_to_use' => <<<'TEXT'
When using IET, first identify the boundary you are operating in: your personal workspace, a Group, a Group Space, or an Admission. Then identify whether you are reading human-facing Content, changing an authoritative domain object, or collaborating around one.

Prefer the purpose-specific UI. You should not need to manipulate raw database concepts to perform ordinary work.

In the office alpha, begin with the Access Invitation journey and the Needs/Offers/Services directory. The reduced navigation is a release-experience choice, not deletion of the deeper architecture. Development or later releases can restore the full navigation profile without rewriting domain data.
TEXT,
                    'authorization' => <<<'TEXT'
Authorization is contextual. Platform authority does not imply Group authority. Group Membership does not imply access to every Context. Context access does not necessarily mean Membership. Critical transitions are always rechecked by server-side policy and domain Actions.
TEXT,
                    'ideal_target' => <<<'TEXT'
The target experience is a connected life/work graph where a user can move naturally from discussion to agreement, planning, execution, evidence, review, financial consequences, settlement, and learning without jumping between unrelated systems.

Content becomes the primary human-facing work surface, while Contract, Planner, Fulfillment, Accounting and other kernels remain authoritative underneath.
TEXT,
                    'misunderstandings' => <<<'TEXT'
“Everything is one thing” does not mean one giant table or one generic JSON object. It means specialized facts remain linked and explainable from one coherent system.

A message, Content paragraph, or annotation does not become authoritative merely because it says “I accept”, “approved”, “paid”, or similar words.
TEXT,
                ],
                [
                    'title' => '2. User, Actor, and Identity',
                    'summary' => <<<'TEXT'
A User is the authentication account. An Actor is the participant identity used by the domain. Keeping them separate prevents login mechanics from becoming the identity recorded in agreements, content, history, work, or future organization representation.
TEXT,
                    'current_behavior' => <<<'TEXT'
A User owns credentials, email verification, sessions, locale, timezone, account status, and platform access. The current compatibility model usually gives one active User one person Actor.

Domain records such as authored Content, Group creation, annotations, Submissions, and Evaluations attribute activity to the Actor.
TEXT,
                    'how_to_use' => <<<'TEXT'
Use account/profile screens for authentication and personal profile settings. When reading domain history, think in terms of Actors: who authored, submitted, reviewed, invited, accepted, or participated.

If a future organization or system bot acts in IET, it should eventually be represented by an Actor with explicit acting authority rather than by overloading one person's User account.
TEXT,
                    'authorization' => <<<'TEXT'
A User authenticates the request. Policies then determine whether that User may act as the relevant Actor in the requested scope.

Today organization/system Actor switching is not yet a general end-user feature.
TEXT,
                    'ideal_target' => <<<'TEXT'
One User may eventually be explicitly authorized to act as multiple Actors, for example as themselves and as an organization. Acting authority must be auditable, revocable, scoped, and never inferred merely from Group Membership.
TEXT,
                    'misunderstandings' => <<<'TEXT'
User and Actor are not duplicate names for the same concept. User is login identity; Actor is durable domain participation identity.
TEXT,
                ],
                [
                    'title' => '3. Groups, Memberships, Roles, and Permissions',
                    'summary' => <<<'TEXT'
A Group is a governed community/team/project/class/business boundary. Membership records participation. Roles and permissions determine authority inside that Group.
TEXT,
                    'current_behavior' => <<<'TEXT'
Creating a Group provisions the creator as an active member and owner. Groups can contain Spaces, invitations, Admissions, Agreements, and role/permission assignments.

A Membership answers whether an Actor participates. Permissions answer what that Actor may do. The built-in owner role has broad Group authority, but ordinary members do not automatically manage admissions, spaces, agreements, or other participants.
TEXT,
                    'how_to_use' => <<<'TEXT'
Create a Group when several Actors need shared governance over an ongoing environment. Use roles/permissions to grant only the authority people actually need.

Do not use a Group merely to store private personal information; Personal Context exists for that.
TEXT,
                    'authorization' => <<<'TEXT'
Group permissions are isolated to that Group. Managing one Group does not grant authority in another Group or at platform level.

Some Contexts associated with a Group may have additional access rules beyond Membership.
TEXT,
                    'ideal_target' => <<<'TEXT'
Groups should become configurable through versioned Group Blueprints and focused Domain Packs, allowing environments such as a chess school, work project, business, or learning community to be created without exposing generic infrastructure to normal users.
TEXT,
                    'misunderstandings' => <<<'TEXT'
Membership is not authorization. Owner is not the only possible reviewer/manager role. A custom role may receive a specific permission without becoming owner.
TEXT,
                ],
                [
                    'title' => '4. Contexts: Where Work Happens',
                    'summary' => <<<'TEXT'
A Context is a bounded collaboration and visibility environment. It answers who may enter, view, create, interact, review, and manage the artifacts inside it.
TEXT,
                    'current_behavior' => <<<'TEXT'
Current Context kinds are Personal, GroupSpace, Admission, and Reference.

Personal Context holds an Actor's personal Content. GroupSpace Context powers Content/collaboration in a Group Space. Admission Context allows a candidate and authorized reviewers to work together before Membership exists. Reference Context hosts maintained system/reference knowledge that all active verified users may read and annotate while only its designated manager may author/manage official Content.
TEXT,
                    'how_to_use' => <<<'TEXT'
When something seems “missing”, first verify that you are looking in the correct Context. Submission counts, Content libraries, annotations, and reviewer queues are Context-scoped.

Use Personal Context for private/general artifacts. Use GroupSpace Context for Group collaboration. Use Admission Context for pre-membership onboarding/application work. Use Reference Context for shared maintained manuals, policies, and reference knowledge.
TEXT,
                    'authorization' => <<<'TEXT'
Context authorization is distinct from Group Membership. Admission is the main proof: a candidate may access their Admission Context while still being forbidden from normal Group participation.
TEXT,
                    'ideal_target' => <<<'TEXT'
Future Context kinds include DirectCollaboration, Negotiation, Contract, and Project. The same Content, Conversation, Submission, Asset and annotation capabilities should compose inside them under their own access policies.
TEXT,
                    'misunderstandings' => <<<'TEXT'
If a reviewer page shows zero items, it may simply be another Context. URLs or IDs do not grant access; server authorization is always rechecked.
TEXT,
                ],
                [
                    'title' => '5. Content, Definitions, and Blueprints',
                    'summary' => <<<'TEXT'
Content is IET's universal human-facing artifact layer. Definitions describe structured fields. Blueprints are reusable recipes that create normal Content with sensible structure, presentation, semantics, and interaction defaults.
TEXT,
                    'current_behavior' => <<<'TEXT'
One Content kernel powers Personal, GroupSpace, and Admission Content. Built-in Blueprints include Note/Diary, Post, Article, Activity/Report, Evidence/Work Sample, Media Album, Book/Booklet, Lesson, Workbook Page, Questionnaire, and Guide/Documentation.

Blueprint identities and versions are durable. System-, Actor-, and Context-scoped Blueprint identities already exist in the kernel. Clone provenance is supported. The full end-user Blueprint editor/versioning experience is not yet productized.
TEXT,
                    'how_to_use' => <<<'TEXT'
For ordinary authoring, start from the closest Blueprint instead of creating raw Definitions. Use Quick creation for essential fields, then the Studio when you need blocks, media, appearance, outline, revisions, or publication.

Use a Blueprint as a starting recipe, not as the permanent owner of the resulting Content.
TEXT,
                    'authorization' => <<<'TEXT'
Creating Content requires create authority in the Context. Advanced Definition/structure management requires stronger permissions. Blueprint visibility is filtered by Context compatibility and access.
TEXT,
                    'ideal_target' => <<<'TEXT'
Authorized users should be able to clone a Blueprint, create Actor- or Context-owned Blueprints from scratch, edit safe draft versions, preview them, and publish immutable Blueprint versions.

Blueprints may suggest relevant contextual capabilities, but they never execute arbitrary code or grant authority.
TEXT,
                    'misunderstandings' => <<<'TEXT'
Content is not a replacement for every domain model. A Contract may have terms Content, but its parties/acceptances/commitments remain Contract-domain truth. An invoice may render through Content, but accounting truth belongs to the ledger.
TEXT,
                ],
                [
                    'title' => '6. Reader, Studio, Blocks, Assets, and Annotations',
                    'summary' => <<<'TEXT'
The Studio is the authoring surface. The Reader is the published human-facing surface. Blocks organize document structure, Assets provide files/media, and annotations let users discuss or privately study exact parts of an edition.
TEXT,
                    'current_behavior' => <<<'TEXT'
The Reader supports reactions and annotations. Top-level annotation kinds include comment, note, question, correction, and idea; replies/answers are separate child roles.

Annotations can target a whole revision, field, exact text range, Asset, relationship, or block. They are immutable interaction history and may be private or shared with the Space audience.
TEXT,
                    'how_to_use' => <<<'TEXT'
Use a private note for personal study. Use a question when you want an answer. Use correction when the official material appears wrong. Use idea when proposing an enhancement.

Select the most precise target possible: the exact text, block, field, image/file, or section. This gives future maintainers clear context and preserves what you actually commented on even when later editions change.
TEXT,
                    'authorization' => <<<'TEXT'
You may annotate only when the current Content/Context interaction policy and your permissions allow it. Shared annotations are visible only to the authorized audience of that Context. Attachments follow Asset authorization.
TEXT,
                    'ideal_target' => <<<'TEXT'
The Reader should become a contextual work surface. In clean mode it shows only the origin artifact. In work mode it may show permission-aware capability cards at document or block level: submit, review, start/end work, inspect/propose Contract changes, view Planner/Fulfillment state, or display derived accounting summaries.

Those controls call authoritative domain Actions; the Content text itself never performs the transition.
TEXT,
                    'misunderstandings' => <<<'TEXT'
An annotation is collaboration, not authority. Writing “approved” does not approve an Admission. Writing “I accept” does not accept a Contract. An idea/correction remains attached to the edition where it was made until an explicit contribution-resolution workflow connects it to a later official revision.
TEXT,
                ],
                [
                    'title' => '7. Publishing, Editions, Permalinks, and Evidence References',
                    'summary' => <<<'TEXT'
Publishing seals an immutable edition. A normal Content link identifies the artifact; an edition permalink identifies one exact revision; a Content Evidence Reference is a first-class immutable domain locator to exact sealed evidence.
TEXT,
                    'current_behavior' => <<<'TEXT'
Published revisions receive verifiable publication evidence and cannot be silently rewritten. Later changes create new revisions.

Edition permalinks continue resolving the chosen historical revision after newer editions exist. Evidence References may target a whole revision or precise field/block/Asset/relationship and are authorization-controlled.
TEXT,
                    'how_to_use' => <<<'TEXT'
Use the normal Content URL when you want the current artifact. Use an edition permalink when a human needs a stable historical page. Use an Evidence Reference when another domain, such as a Submission, must persist exactly what evidence was cited.

Authorized editors can create a whole-revision Evidence Reference from the published Reader.
TEXT,
                    'authorization' => <<<'TEXT'
A permalink or evidence UUID is not a security capability. The viewer must still be authorized to see the underlying Content and Context.
TEXT,
                    'ideal_target' => <<<'TEXT'
Historical evidence should remain explainable across Contracts, Fulfillments, evaluations, learning/reputation, and support workflows. Exact evidence identity should survive all later Content edits.

Accepted documentation corrections/ideas should later be explicitly linked as provenance of the official revision that incorporated them.
TEXT,
                    'misunderstandings' => <<<'TEXT'
A permalink and Evidence Reference may lead to the same visible edition, but they solve different problems: navigation versus durable domain provenance.
TEXT,
                ],
                [
                    'title' => '8. Invitations and Admissions',
                    'summary' => <<<'TEXT'
IET now distinguishes system Access Invitations from Group Invitations. An Access Invitation allows a new person to inspect the invitation-only welcome experience and create an account without joining a Group. A Group Invitation is for an existing verified account and begins the Group Admission journey before Membership.
TEXT,
                    'current_behavior' => <<<'TEXT'
A standalone Access Invitation is the preferred new-account doorway. It may be reserved for one email or issued as a bounded reusable private office link. Registration creates the User and Actor identity, records immutable invitation acceptance evidence, requires email verification, and then returns the person to Get Started. It does not create Group Membership.

Group Invitations are now presented as invitations between existing verified users. Opening one leads into the existing Group Admission journey, where the candidate remains outside normal Group participation until an explicit authorized finalization creates Membership.

Phase 7 can place structured applications/evidence inside an Admission Context, but the conversation-first Admission v2 experience remains later roadmap work.
TEXT,
                    'how_to_use' => <<<'TEXT'
For a new person, issue a system Access Invitation. The invitee may inspect the welcome page before registering, then verifies email and continues to Get Started without being forced into a Group.

Use a Group Invitation only when inviting an existing verified account to a specific Group. If Group review is required, the candidate completes the Admission journey and any requested structured work inside the Admission Context. Reviewers should use explicit Admission and Evaluation actions rather than interpreting free-form notes as state transitions.
TEXT,
                    'authorization' => <<<'TEXT'
Candidates may access their Admission Context but not normal GroupSpace Content unless separately authorized. Admission reviewers require the Group's manage_admissions authority; they do not have to be the owner.
TEXT,
                    'ideal_target' => <<<'TEXT'
Admission v2 should feel conversation-first: candidate/reviewer discussion, structured requirement cards, evidence, system timeline, explicit approval/rejection/agreement actions, clear reviewer attention state, and later realtime delivery.

Messages remain collaboration; authoritative transitions remain explicit.
TEXT,
                    'misunderstandings' => <<<'TEXT'
An Evaluation does not automatically approve Admission or create Membership. Candidate discussion must not mutate a Group-wide Agreement or create a private Contract implicitly.
TEXT,
                ],
                [
                    'title' => '9. Submission, Response, and Evaluation',
                    'summary' => <<<'TEXT'
Phase 7 provides the structured interaction engine used for exams, applications, questionnaires, assignments, and evidence requests.
TEXT,
                    'current_behavior' => <<<'TEXT'
An InteractionDefinition has immutable versions. A Submission is one Actor's attempt against one exact version. Responses belong to that attempt. Drafts are private to the submitter. Explicit Submit validates required responses, seals evidence, makes the attempt reviewer-visible, and prevents further edits to that submitted evidence.

Evaluations are explicit reviewer evidence with score/criteria/feedback where configured.
TEXT,
                    'how_to_use' => <<<'TEXT'
Start the interaction, enter answers, and use Save draft when work is not ready for review. Reviewers intentionally still see zero while the attempt is draft. Click the purpose-specific Submit action to create reviewer-visible work.

Reviewers must open the same Context that owns the Submission. The Context Content page exposes Review submissions with the current submitted count.
TEXT,
                    'authorization' => <<<'TEXT'
Submit eligibility is defined by the interaction and Context. GroupSpace review authority currently comes from manage_spaces or explicit Space manager access. Admission review authority comes from manage_admissions. A reviewer cannot evaluate their own Submission.
TEXT,
                    'ideal_target' => <<<'TEXT'
These structured cards should appear naturally inside future Conversation and workflow experiences so both sides immediately see what changed, what is waiting, and what action is available without searching unrelated pages.

Realtime delivery may notify both sides, but the database Submission/Evaluation remains authoritative.
TEXT,
                    'misunderstandings' => <<<'TEXT'
Typing answers or Save draft is not Submit. An Evaluation does not itself approve Admission, create Membership, activate a Contract, or create a financial obligation.
TEXT,
                ],
                [
                    'title' => '10. Connected-Life Target and Roadmap',
                    'summary' => <<<'TEXT'
The long-term proof for IET is not “can it store many object types?” but “can real life flow through one connected system without losing authority or provenance?”
TEXT,
                    'current_behavior' => <<<'TEXT'
Identity, governance, Context, Content and structured interaction foundations exist. Conversation-first Admission, realtime collaboration, generic Workflow, Planner, Domain Packs, direct Contract/Commitment/Fulfillment, matching, Accounting, financial laboratory, discovery, pilots, and production release remain later roadmap work.
TEXT,
                    'how_to_use' => <<<'TEXT'
Use today's implemented kernels for what they already guarantee. Do not fake future Contract, Planner, payment, or fulfillment truth by writing prose into Content fields.

When a real use case exposes friction, annotate the relevant manual/system Content precisely. That feedback should influence the next official documentation edition and, when appropriate, the product roadmap.
TEXT,
                    'authorization' => <<<'TEXT'
Roadmap evolution is an architecture/governance decision. User ideas are valuable evidence, but they do not silently change system rules. Accepted changes become explicit code/domain changes and new official documentation revisions.
TEXT,
                    'ideal_target' => <<<'TEXT'
A direct paid-work relationship should eventually flow as:

request/discussion → exact Contract version → explicit acceptance → Commitments → scheduled Occurrences → actual start/end + evidence → review/acceptance → earned financial obligation → payment/settlement → accounting.

The system should be able to explain scheduled, worked, accepted, earned, paid, outstanding, and disputed state while preserving the exact Contract version and evidence that governed each fact.
TEXT,
                    'misunderstandings' => <<<'TEXT'
The roadmap is not a promise that every future concept already exists. Documentation must always label current behavior separately from target behavior so users and developers do not confuse architecture with implementation.
TEXT,
                ],
                [
                    'title' => '11. Reporting Questions, Problems, Corrections, and Ideas',
                    'summary' => <<<'TEXT'
The preferred feedback path is section-specific annotation on the exact documentation or system Content edition that caused the question or idea.
TEXT,
                    'current_behavior' => <<<'TEXT'
The Content annotation system supports question, correction, idea, note, and comment kinds with exact revision/block/field/text/Asset/relationship anchors. Replies and answers preserve discussion history.

Annotations remain attached to the exact edition where they were created. Authorized maintainers can append immutable feedback dispositions such as reviewed, accepted, rejected, superseded, and incorporated. An incorporated disposition must point to a later sealed revision of the same Content, preserving a durable chain from the original user feedback to the official edition that incorporated it.
TEXT,
                    'how_to_use' => <<<'TEXT'
When you encounter a problem, open the most relevant manual page or system Content. Select the exact sentence, word, block, field, image, or section if possible. Choose:

- Question: you need an explanation or answer.
- Correction: the documented/system behavior appears wrong.
- Idea: you propose an improvement.
- Note: private learning or working note.
- Comment: general discussion.

Use private visibility for personal study; use shared visibility when the authorized community/support audience should see and answer it. Attach files/media when they materially explain the issue.
TEXT,
                    'authorization' => <<<'TEXT'
Annotation visibility never bypasses the Context audience. Shared feedback is visible only to users authorized for that Context. Accepting a suggestion into official documentation or product behavior requires maintainer/domain authority.
TEXT,
                    'ideal_target' => <<<'TEXT'
The disposition history should grow into a complete contribution workflow: triage queues, optional maintainer notes, official/support answer identity, release-note linkage, filtering by status, and clear visibility of which product release addressed an accepted issue.

Support/community participants should be able to answer questions, while official answers and accepted/incorporated changes remain visually distinguishable from ordinary replies.
TEXT,
                    'misunderstandings' => <<<'TEXT'
An annotation is never silently edited into the origin. The origin stays clean and historically exact. Community enhancements and proposed changes are overlays until an authorized revision incorporates them.
TEXT,
                ],
                [
                    'title' => '12. Profile, Concepts, Skills, Interests, Goals, Needs, and Offers',
                    'summary' => <<<'TEXT'
The Profile describes an Actor progressively. Concepts provide reusable semantic meaning; predicates describe how the Actor relates to that meaning. This lets the same Concept represent “I know Laravel”, “I want to learn Laravel”, “I need Laravel help”, or “I offer Laravel work” without creating four duplicate Laravel concepts.
TEXT,
                    'current_behavior' => <<<'TEXT'
ActorProfile currently supports display identity, headline, biography, location, website, privacy controls, profile media, locale/time preferences, semantic skills/interests/learning goals, recurring Profile Needs/Offers, purpose-specific completeness requirements, and selective disclosure to a chosen recipient.

The first publishable Intent Directory builds directly on those Profile Need/Offer declarations rather than inventing a parallel marketplace model. A guided wizard records Need or Offer, broad subject such as Property/Good/Service/Capital/Collaboration, a reusable Concept, the requested arrangement, location, optional cash range, timing, visibility, and a non-binding value-exchange preference.

Value-exchange preference distinguishes cash-only, cash-preferred-but-open-to-mixed-value, open-to-mixed-value, and discuss-later cases. Mixed value may be described as money plus clearly valued property/use rights, capital participation, or services/skills, but this preference creates no ownership share, debt, Contract, Commitment, payment, or accounting entry.

The read-only Needs, Offers & Services directory shows only active records the viewer is authorized to see and supports practical filters for needs, offers, services, property, capital, collaboration, subject text, and location.

Need/Offer cadence and directory discovery describe current intent only. They do not generate Planner Occurrences, Match records, proposals, Contracts, Commitments, or financial records.

Selective disclosure grants live access to selected current Profile information. If the underlying mutable Profile fact changes, closes, expires, or becomes invalid, the shared view follows the current state. It is not immutable Contract evidence.
TEXT,
                    'how_to_use' => <<<'TEXT'
Build the Profile progressively instead of trying to complete every field at registration.

Reuse an existing Concept when the meaning already exists. Create a personal Concept only when no suitable reusable Concept exists. Add the relationship that actually applies: skill, interest, learning goal, Need, or Offer.

For the first office-oriented release, prefer Record need / offer for new entries. Choose Need when seeking something and Offer when making something available. Choose the broad subject and the plain-language arrangement, then add only the location, cash range, description, timing, and negotiation preference that are actually useful.

Use Registered users visibility when the intent should appear in the invitation-only directory without making unrelated Profile information public. Private keeps the intent out of other users' directory results. The directory is discovery only: staff or users manually compare suitable cases for now.

Use the value-exchange preference to state negotiating openness, not final terms. Exact percentages, ownership, service valuation, capital rights, or payment obligations belong to a future explicit Proposal/Negotiation/Contract flow.

Do not use Profile cadence as a substitute for a future Planner schedule, and do not treat a self-reported proficiency percentage as externally verified reputation.
TEXT,
                    'authorization' => <<<'TEXT'
Profile mutation is owner-controlled. Individual semantic items and intents may have their own visibility. Private information is not exposed merely because the overall Profile is public.

A selective disclosure grant is explicit, recipient-specific, optionally expiring, revocable, and limited to selected Profile fields/assertions/intents. Possessing a grant URL does not bypass recipient authorization.
TEXT,
                    'ideal_target' => <<<'TEXT'
Profile should remain the trustworthy upstream description of an Actor while later Admission, Planner, Matching, Contract, and reputation systems consume only the information they are explicitly allowed to use.

Measurement should become semantically appropriate rather than forcing every relationship into one percentage. Skill proficiency, learning progress, interest strength, goal priority, evidence maturity, verification, and reputation are different dimensions and should remain explainable.

Future organization/system Actors and explicit acting authority should reuse the same semantic foundations without collapsing User authentication into Actor identity.
TEXT,
                    'misunderstandings' => <<<'TEXT'
A Concept is the meaning; “skill”, “interest”, “wants to learn”, “needs”, and “offers” are relationships to that meaning.

A recurring Profile Need is not a calendar. A Profile Offer is not a binding promise. A disclosure is not Membership or Context authorization. A self-rating is not verification or reputation.
TEXT,
                ],
                [
                    'title' => '13. Group Agreements and Versioned Governance',
                    'summary' => <<<'TEXT'
A Group Agreement defines Group-wide participation rules through explicit immutable versions, governance approval, activation, and exact-version acceptance evidence. It is different from a future party-specific negotiated Contract.
TEXT,
                    'current_behavior' => <<<'TEXT'
Groups can maintain versioned Agreements. Agreement versions preserve exact terms, content hashes, approvals, activation/effective state, and acceptance evidence.

Invitation/Admission flows can require the candidate to accept the exact Agreement version. The system rechecks required acceptance at authoritative transitions such as candidate submission/finalization so a stale or different version cannot silently satisfy the requirement.

Current Group Agreement versions still own their human-readable long-text terms directly. The Content kernel now has stronger authored-document capabilities, so the long-term architecture plans to let an Agreement version reference an exact sealed Content revision without moving Agreement lifecycle/party/acceptance authority into Content.
TEXT,
                    'how_to_use' => <<<'TEXT'
Use a Group Agreement for rules that govern the Group or its participation. Publish/activate a new version when those rules change; do not edit accepted historical terms in place.

When a candidate/member must accept terms, make sure the UI/action is the explicit Agreement acceptance flow for the exact version. A discussion comment or Content annotation saying “I agree” is useful evidence of conversation but is not the acceptance action.

If a rule applies only to specific parties, such as negotiated compensation for one worker, do not mutate the Group-wide Agreement. That belongs to the future negotiated Contract model.
TEXT,
                    'authorization' => <<<'TEXT'
Agreement drafting, approval, activation, and governance actions require the relevant Group permissions. Acceptance evidence records the exact Agreement/version and the authorized acting identity.

Group authority is scoped to that Group. It does not grant authority over another Group or platform-wide governance.
TEXT,
                    'ideal_target' => <<<'TEXT'
Group-wide governance should remain distinct from negotiated bilateral/multi-party Contracts.

Human-readable terms should increasingly use exact sealed Content revisions for rich authoring, media, annotations, evidence and historical permalinks, while the Agreement domain continues to own versions, required approvals, effective periods, acceptance/reacceptance rules, and governance history.

When discussion reveals that Group-wide rules should change, the system should create/propose a new Agreement version with explicit activation/effective timing rather than mutating the active version. Party-specific negotiated terms should route into the future Contract/Commitment system.
TEXT,
                    'misunderstandings' => <<<'TEXT'
Agreement is not merely a document. The document explains the terms; the Agreement domain records which version is proposed/approved/active and who accepted it.

A candidate-specific negotiation must not silently rewrite the rules for every Group member. An annotation saying “accepted” is not acceptance authority.
TEXT,
                ],
                [
                    'title' => '14. AI-Assisted Authoring and Development Origins',
                    'summary' => <<<'TEXT'
AI assistance in IET helps translate a user's intent into trusted, reviewable changes without making the model an authority. Development Origins preserve a curated historical link from important design conversations to the roadmap, repository documents, commits, and system versions they influenced.
TEXT,
                    'current_behavior' => <<<'TEXT'
The AI Content Assistant architecture exists but is feature-gated off by default in the first published alpha. When an operator deliberately enables and configures it, authorized Content editors can open AI Content Assistant from the Content Studio, describe a desired result, review a structured proposal, and explicitly apply that proposal to a new draft revision. Creating a proposal then sends the user's prompt and the exact current Content snapshot to the configured AI provider; the server-side provider credential is never exposed to the browser. The assistant may propose changes to existing structured fields, safe document blocks, and presentation tokens. It uses the same Content policies and Actions as ordinary Studio editing, refuses stale proposals after the Content has changed, and never publishes automatically.

Requests for generated image, audio, or video are currently recorded as media requests only. This first slice does not invent Asset identities or bypass the private Asset scanning, rights, readiness, and publication pipeline.

Platform users with View Platform Audit authority can also capture a Development Origin: a reviewed summary plus optional source link, roadmap phase, system version, branch, baseline/result Git commit SHAs, and related repository paths. Development Origins are immutable provenance. They do not replace canonical repository documents.
TEXT,
                    'how_to_use' => <<<'TEXT'
When AI assistance has been explicitly enabled by the operator, open an editable Content Studio and choose AI Content Assistant. Describe the exact section, wording, structure, or appearance you want. Review the proposed changes before applying them. After application, return to Studio, inspect the new draft revision, adjust it normally if necessary, and publish only through the ordinary explicit publish action.

For a meaningful product-development conversation, an authorized platform auditor can open Development Origins and store a concise reviewed summary rather than copying an entire private conversation by default. Relate it to the phase, branch, exact baseline/result commits, and canonical repository paths that were changed because of the discussion.
TEXT,
                    'authorization' => <<<'TEXT'
AI assistance never grants authority. Planning and application both require the same authenticated Content update permission that a human editor needs, and application rechecks authorization. The provider API key remains server-side. Generated proposals cannot execute arbitrary PHP, JavaScript, HTML, SQL, shell commands, or hidden domain transitions.

Development Origins are platform-audit history and currently require View Platform Audit capability. Group roles alone do not grant this platform authority.
TEXT,
                    'ideal_target' => <<<'TEXT'
The same human-directed seam can later support section-specific assistants and form assistance throughout IET:

current Context/object + user's intent
→ AI proposes trusted structured values/actions
→ schema and authorization validation
→ human review/confirmation where authority matters
→ existing domain Action
→ durable state + provenance

Future media generation should create normal private Assets through an authorized provider adapter, then pass scanning, rights, readiness, and Content attachment rules before publication. Later Admission/Conversation experiences may embed AI-assisted structured cards, but messages or AI wording must never silently submit, approve, accept an Agreement/Contract, finalize Membership, create financial truth, or perform another authoritative transition.
TEXT,
                    'misunderstandings' => <<<'TEXT'
An AI proposal is not a system decision. “Submit”, “approve”, “accept”, “publish”, “paid”, and similar authoritative facts still require explicit authorized domain Actions.

A source chat is useful provenance, not canonical architecture. The repository's accepted architecture/roadmap documents remain authoritative. Development Origins should normally preserve a curated summary and references, not automatically ingest private raw chat history.

AI-assisted Content does not mean arbitrary executable widgets. New functionality must be implemented as a trusted platform capability, component, Blueprint, or domain Action and then exposed to the assistant through a validated registry.
TEXT,
                ],
            ],
        ];
    }
}
