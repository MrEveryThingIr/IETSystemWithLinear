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
        'end-to-end' => '14. End-to-End Guided Example and UI Testing',
        'relationships' => '15. Relationships and Direct Collaboration',
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
Today the strongest implemented foundations are User/Actor identity, Group governance, Membership and permissions, standalone system Access Invitations, Group Invitation/Admission, Personal/GroupSpace/Admission/Relationship/Reference Contexts, one versioned Content system with Blueprints and immutable published revisions, annotations/evidence locators, Submission/Response/Evaluation, the guided Need/Offer + Intent Directory experience, and consent-aware direct Relationships with explicit participant roles and their own Context.

The first published experience can intentionally hide advanced modules through the office-alpha release profile while preserving those kernels for later composition.

Conversation/Timeline, Planner, Accounting, negotiated Proposal/Contract/Commitment/Fulfillment, Matching, realtime, reputation/discovery, and AI remain later roadmap milestones.
TEXT,
                    'how_to_use' => <<<'TEXT'
Start with the action you actually want, not with an internal model name.

In the office-alpha experience:
1. Dashboard is the simple starting point.
2. Needs, offers & services opens the current Intent Directory.
3. Record need / offer starts the guided Intent wizard.
4. Profile manages the Actor information you intentionally maintain/share.
5. Contextual Help opens the relevant section of this manual.

Example: Alice does not need to understand ActorProfileIntent. She chooses “Need / wanted”, “Property / real estate”, enters what she needs, selects the arrangement and visibility, reviews the result, and records it.

Advanced kernels may exist without appearing in normal navigation. The UI should reveal the next meaningful capability only when the current situation makes it useful.
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
Current Context kinds are Personal, GroupSpace, Admission, Relationship, and Reference.

Personal Context can be the home of an Actor's Content. GroupSpace Context provides a governed Group collaboration surface. Admission Context lets a candidate and authorized reviewers collaborate before Membership exists. Relationship Context provides a direct participant-scoped collaboration boundary outside Group Membership. Reference Context hosts maintained shared knowledge such as this System Manual.

Content is not permanently “inside Groups”. A Content item has a home/origin Context for authoring, lifecycle and authorization, while authorized published Content can later be presented or referenced from other Contexts without copying it.
TEXT,
                    'how_to_use' => <<<'TEXT'
When something seems missing, first verify the Context you are operating in. Content libraries, annotations, Submissions and review queues are Context-scoped.

Use Personal Context as the normal home for Alice's own Article or Album. Use GroupSpace Context for Maple Housing Office collaboration. Use Admission Context for a candidate/reviewer onboarding journey. Use Relationship Context after Alice and Bob explicitly accept a direct client/provider Relationship. Use Reference Context for maintained manuals/reference material.

When a later GroupSpace or Relationship needs Alice's already-published Article, reference/present the existing Content rather than creating a duplicate “group article”. Authorization is still checked for the viewer.
TEXT,
                    'authorization' => <<<'TEXT'
Context authorization is distinct from Group Membership. Admission is the main proof: a candidate may access their Admission Context while still being forbidden from normal Group participation.
TEXT,
                    'ideal_target' => <<<'TEXT'
Direct collaboration now uses Relationship Context. Future specialized coordination may add Negotiation, Contract and Project Contexts where their own authorization/lifecycle semantics justify a distinct boundary. The same Content, Conversation, Submission, Asset and annotation capabilities should compose under Context-specific policies rather than duplicate storage engines.
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
One Content kernel powers Personal, GroupSpace, Admission and Reference Content. Built-in Blueprints include Note/Diary, Post, Article, Activity/Report, Evidence/Work Sample, Media Album, Book/Booklet, Lesson, Workbook Page, Questionnaire, and Guide/Documentation.

Blueprint identities and versions are durable. Creating from a Blueprint creates normal independently versioned Content.

A Content item has a home Context for authoring/authorization. The published Content Library shows only sealed published Content the current viewer is authorized to read and supports search, Blueprint/type and semantic Concept filtering.

“Present elsewhere” creates a ContentPlacement that exposes the same published Content identity through another Context; it does not copy or move the artifact. Normal placement follows the current published revision. Exact Evidence References remain pinned to the historical sealed revision/field/block/Asset/relationship they identify.
TEXT,
                    'how_to_use' => <<<'TEXT'
For ordinary authoring, choose the closest human purpose instead of creating a raw Definition.

Example: Alice wants to document Riverside Lot. Choose an Article or Report when the artifact is explanatory, or Media Album when the main purpose is photos. Enter the essential fields in Quick creation, then open Studio only if blocks, media, appearance, outline, revisions or publication are needed.

After publication, open **Content Library**. Use Search, Content type and Concept filters to find the artifact. If you can both read its home Context and manage Content in the destination Context, choose **Present elsewhere**, select the target Context, then choose **Present Content**. The destination presents the existing artifact; it does not receive a clone.

Use the normal Content/placement when the meaning is “show the current published artifact”. Use an exact edition permalink or Evidence Reference when history must not drift.

Type/purpose answers “what kind of artifact is this?” Concepts/categories answer “what is it about?”. Keep those dimensions separate.
TEXT,
                    'authorization' => <<<'TEXT'
Creating Content requires create authority in the Context. Advanced Definition/structure management requires stronger permissions. Blueprint visibility is filtered by Context compatibility and access.

Creating a placement requires both independent read authority for the source/home Context and Content-management authority for the target Context. The target audience receives read access to the published artifact through that target only. Placement does not grant source Context visibility, Studio/edit/revision authority, interaction authority, or permission to reshare transitively.
TEXT,
                    'ideal_target' => <<<'TEXT'
The Library should continue toward richer origin-Context, author, language and date filtering, saved/discovery views, and a compact permission-aware ⋮ action menu. Viewer actions may include open/copy reference/evidence citation/present elsewhere; editor actions may additionally include edit/new revision/blocks/media/appearance/relationships/publication/archive.

A later cross-party workflow may support requesting or approving presentation when no single actor holds both source-read and target-management authority; it must not weaken the current authorization boundary.

Content remains one kernel. Contexts compose it rather than duplicating it.
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
Reader is for consuming the current or selected published edition. Studio is for authorized authoring.

Typical author flow:
1. open the Content;
2. choose Studio when editing is allowed;
3. edit structured fields;
4. use Blocks for document structure;
5. attach image/audio/video/files through Assets;
6. use Appearance only for safe presentation choices;
7. use Outline for composition/contains relationships;
8. publish when the edition is ready.

Typical reader flow:
1. read cleanly;
2. open contextual controls only when needed;
3. select the smallest relevant text/block/field/Asset;
4. choose Question, Correction, Idea, Note or Comment.

Example: Bob notices one sentence in Alice's construction Article is wrong. He anchors a Correction to that exact sentence rather than posting an unrelated general comment.
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
Use the normal Content URL when the intent is “show me this artifact as currently published”.

Use an edition permalink when the intent is “show this exact historical edition”.

Use an Evidence Reference when another domain must persist exactly what was relied on.

Example: Alice's Article may later be presented in Maple Housing Office and follow the current published edition. If Bob submits one exact block as evidence of completed work, the evidence must pin the exact published revision/block so Alice's later edits cannot rewrite history.

A Content link, edition permalink and evidence reference can lead to similar-looking pages but have different semantics: current presentation, human historical navigation, and durable domain provenance.
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
IET separates account access from Group participation. A standalone Access Invitation lets a new person inspect/register without joining a Group. A Group Invitation is the collaboration/admission path for an existing verified account.
TEXT,
                    'current_behavior' => <<<'TEXT'
An authorized platform administrator can issue a private standalone Access Invitation. The invitee may inspect the welcome page, register, verify email and continue to Get Started without Group Membership.

Group Invitations are presented for existing verified users. They lead toward Group Admission/Membership under Group authority.

Admission still has formal lifecycle state and can own an Admission Context. Structured applications/evidence can use Phase 7 Submission/Response/Evaluation. Conversation-first Admission remains a later roadmap composition.
TEXT,
                    'how_to_use' => <<<'TEXT'
Example A — new person:
1. Diego opens Access Invitations.
2. He creates a private invitation for Alice.
3. Alice opens the link and reads the welcome page before registering.
4. Alice creates her account and verifies email.
5. Alice lands on Get Started. She is registered but is not automatically a Maple Housing Office member.

Example B — existing user joins a Group:
1. Bob already has a verified account.
2. Diego opens Maple Housing Office → Invitations.
3. Diego enters Bob's registered email.
4. Bob opens the Group invitation and follows the Admission/Agreement journey.
5. Membership exists only after the authoritative Group flow finalizes it.

Do not use a Group Invitation as the normal new-account mechanism.
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
ActorProfile supports display identity, biography/location/media/preferences, semantic skills/interests/learning goals, selective disclosure and current Need/Offer declarations.

The first Intent release extends Need/Offer with queryable subject kind (Property, Good, Service, Capital, Collaboration, Other), arrangement kind, optional cash range/currency/basis, location, visibility and a non-binding value-exchange preference.

The value-exchange preference can express cash-only, cash-preferred-open-to-mixed-value, open-to-mixed-value, or discuss-later. It never creates ownership, equity, debt, Contract, service obligation, payment or accounting truth.

Need/Offer remains current intent only. It does not create Planner Occurrences, Matches, Proposals, Contracts, Commitments or Fulfillment.
TEXT,
                    'how_to_use' => <<<'TEXT'
Use Record need / offer and begin with the real-world question **What do you want to do?**

Current journey choices are grouped so a normal user does not have to translate their situation into internal model terminology:

- Buy or acquire something
- Sell or transfer something
- Rent / temporarily use something
- Rent out / provide temporary use
- I need a service
- I provide a service
- I want to hire someone
- I am looking for work
- I need capital or financing
- I can provide capital or financing
- I need collaborators or partners
- I want to join/contribute to a collaboration
- Something else

The chosen journey preselects existing Need/Offer and arrangement semantics. It is an authoring path, not a stored business type.

Alice example:
1. Alice chooses **I need a service**.
2. The system already understands this is a Need + Service arrangement and only shows the relevant Service/skill subject.
3. Alice types “Residential construction”.
4. She adds location/cash range only if useful.
5. She chooses her value-exchange preference.
6. She adds human details/visibility.
7. Review shows both the friendly journey and the underlying Need/Offer interpretation.
8. **Record intent** creates one normal ActorProfileIntent.

Bob example: **I am looking for work** maps to Offer + Service. Diego's “I want to hire someone” maps to Need + Service. These do not create an Employment domain or Contract.

Carol example: **I can provide capital or financing** maps to Offer + Capital + Financing. It does not create equity, debt or a loan.

For unusual cases choose **Something else**; step 2 then exposes Need/Offer, subject and arrangement controls manually.

Use the Directory quick filters Needs, Offers, Services, Property, Capital and Collaboration to search records you are authorized to see.
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
                    'title' => '14. End-to-End Guided Example and UI Testing',
                    'summary' => <<<'TEXT'
This chapter connects the manual into one reusable browser story. The same people introduced during registration continue into intents, Groups, Content and later roadmap capabilities so testing does not become a collection of unrelated toy examples.
TEXT,
                    'current_behavior' => <<<'TEXT'
The current executable story covers Diego issuing a standalone Access Invitation, Alice registering/verifying, Alice recording Needs/Offers, Bob/Carol recording complementary Offers, the permission-aware Intent Directory, Bob joining Maple Housing Office through a Group Invitation as an existing verified user, Content/Context authoring, the published Content Library with authorized cross-Context placement, Phase 7 structured Submission/Response/Evaluation, and Phase 10 direct Relationships with explicit consent and a dedicated Relationship Context.

Conversation/Timeline, Planner, Accounting, Proposal/Contract/Commitment/Fulfillment, Matching and AI are roadmap steps and must be labelled as future until their milestone is remotely integrated.
TEXT,
                    'how_to_use' => <<<'TEXT'
Run the story in this order.

1. Diego / Access Invitations
   - Sign in as an authorized platform administrator.
   - Open Access Invitations.
   - Create one private invitation reserved for Alice's new email.
   - Copy the generated private link.

2. Alice / registration
   - Open the link in a private browser.
   - Inspect Welcome before registration.
   - Create Alice's account with the reserved email.
   - Open the verification link from the configured local mail transport.
   - Confirm Get Started opens.

3. Alice / progressive Intent Journey
   - Choose Record need / offer.
   - On “What do you want to do?” select **Sell or transfer something** for Riverside Lot; on step 2 refine the subject from Thing/good to Property.
   - Repeat with **I need a service** for Residential construction.
   - Repeat with **I need capital or financing**.
   - For one case choose “cash preferred, open to a structured mixed-value arrangement”.
   - Confirm the review screen shows the friendly journey plus the underlying Need/Offer/subject/arrangement.
   - Confirm saving creates Intent only: no Match, Contract, ownership, loan, employment or payment.

4. Bob / service and work
   - Use a separate verified Bob account.
   - Choose **I provide a service** for electrical/construction services.
   - Also inspect **I am looking for work** and confirm it resolves to Offer + Service rather than a separate employment engine.
   - Open Needs, offers & services and use Needs, Offers and Services filters.

5. Carol / capital
   - Choose **I can provide capital or financing**.
   - Confirm Capital filtering separates it from Bob's service.

6. Simple product/rental proof
   - Alice can choose **Sell or transfer something** → Thing/good for a used desk.
   - Bob can choose **Buy or acquire something** for the same Concept.
   - A rental uses **Rent or temporarily use something** / **Rent out or provide temporary use** and keeps ownership-transfer semantics out of the record.

7. Visibility
   - Make one Alice intent visible to registered users while her Profile remains private.
   - From Bob, confirm the intent is discoverable without unintended private Profile disclosure.
   - Make another intent Private and confirm Bob cannot see it.

8. Group collaboration
   - Diego creates/opens Maple Housing Office.
   - Invite already-registered Bob from Group Invitations.
   - Confirm an unknown/new email is rejected by the Group-invitation creation flow.
   - Complete only explicit Admission/Agreement/Membership actions required by the configured Group flow.

9. Content Library / placement / exact evidence
   - Alice creates an Article/Report/Album through the closest Blueprint in an authorized home Context and publishes it.
   - Open **Content Library**; confirm drafts are absent, then use Search, Content type and Concept filters.
   - For the cross-Context proof, use an actor who independently can read the source/home Context and manage Content in Maple Housing Office. Choose **Present elsewhere**, select the target Context, then choose **Present Content**.
   - From Bob's target-authorized session, open the presented artifact. Confirm Bob did not gain source Context, Studio, edit or revision authority and cannot transitively reshare it.
   - Create/use an exact edition or Evidence Reference for the current revision/block.
   - Publish a newer source edition. Confirm the normal placement follows the newest publication while the exact Evidence Reference still resolves the older sealed target.
   - Remove the placement and confirm Bob's target-only access disappears; presenting it again should reactivate the same placement identity rather than duplicate provenance.

10. Direct Relationship / Relationship Context
   - From Bob, open Alice's visible Riverside service Intent and choose **Start relationship**.
   - Confirm the purpose is inherited from the Intent and Alice is the invited counterpart.
   - Enter explicit roles such as service provider / client and send the request.
   - Before acceptance, open the Relationship/Context from Bob and Alice and confirm it is inspectable but read-only.
   - Confirm an unrelated Actor cannot open it.
   - From Alice, choose **Accept relationship**.
   - Confirm the Relationship becomes active and its Context now allows active participants to create/interact with ordinary Content.
   - Confirm no Group Membership, Match, Proposal, Contract, ownership, financing right, employment, obligation or payment was created.
   - Separately, Alice can start a direct capital/collaboration Relationship with Carol by selecting the purpose Concept and entering Carol's exact username.
   - End a Relationship and confirm its Context remains readable history but becomes read-only.

11. Structured interaction
   - Start a configured interaction.
   - Save draft and confirm reviewer count does not treat it as submitted.
   - Submit explicitly.
   - From an authorized reviewer, open Review submissions.
   - Finalize Evaluation through the explicit review action.

At every step verify: what object was created, who can see it, what exact action changed state, and what did *not* happen implicitly.
TEXT,
                    'authorization' => <<<'TEXT'
Use separate sessions/accounts when checking visibility and role boundaries. URLs/tokens are never substitutes for authorization. Do not give Alice platform or Group authority merely to make a demo easier.

Future milestones should extend this same story rather than replace it. Relationship is now implemented. When Conversation/Timeline, Planner, Accounting or Contract capability is implemented, append the next Alice/Bob/Carol/Riverside steps here and in docs/LOCAL_ACCEPTANCE_WORKSHEET.md.
TEXT,
                    'ideal_target' => <<<'TEXT'
The final Ideal-v1 browser story continues naturally:

Alice/Bob/Carol Intent/discovery → Relationship Context (current) → Conversation/Timeline → Proposal → exact ContractVersion → Commitments → planned Occurrences → actual Fulfillment/evidence → review → Financial Obligation → accounting → Settlement → Home/Today summaries.

The user should experience one understandable story while each authoritative fact remains owned by its specialized kernel.
TEXT,
                    'misunderstandings' => <<<'TEXT'
A demo story is not permission to auto-create domain consequences. Do not skip explicit acceptance/review/payment actions just because later steps are known in advance.

Documentation examples must never describe a future capability as currently implemented.
TEXT,
                ],
                [
                    'title' => '15. Relationships and Direct Collaboration',
                    'summary' => <<<'TEXT'
A Relationship is IET's explicit direct coordination boundary between named Actors outside Group Membership. It records why the relationship exists, who participates, each human role, lifecycle, provenance, and the dedicated Context where current capabilities can compose.
TEXT,
                    'current_behavior' => <<<'TEXT'
Phase 10 implements Relationship, RelationshipParticipant, RelationshipContext and immutable RelationshipEvent history.

Creating a Relationship produces a proposed request. The creator is an active managing participant; invited Actors remain invited. The Relationship Context exists immediately so participants can inspect the request, but it is read-only until every initial invitee explicitly accepts.

After activation, active participants can create/interact with ordinary Content in that Relationship Context. A participant marked can_manage may manage Content/definitions/review capability. Ending or cancelling preserves participant-readable history but removes write authority.

A Relationship may link to the active visible Intent that originated it. That provenance link does not convert the Intent into a Match, Proposal, Contract, obligation or payment.
TEXT,
                    'how_to_use' => <<<'TEXT'
From a discovered Intent:
1. Bob opens **Needs, offers & services**.
2. Bob finds Alice's visible Riverside construction-service Need.
3. Bob chooses **Start relationship**.
4. The form fixes the purpose to the Intent's Concept and fixes Alice as the counterpart.
5. Bob enters explicit roles such as service provider / client and optionally a clear label such as Riverside electrical work.
6. Bob chooses **Send relationship request**.
7. Alice opens **Relationships**, opens the pending request, reviews purpose and roles, then chooses **Accept relationship** or **Decline**.
8. Only after acceptance does the Relationship Context become writable.

For a direct known-person request:
1. Alice opens **Relationships → Start relationship**.
2. Alice selects a purpose Concept.
3. Alice enters Carol's exact active verified username.
4. Alice records both roles, such as project owner / capital collaborator.
5. Alice sends the request; Carol must accept before collaboration becomes active.

Inside an active Relationship, choose **Open workspace** to use ordinary Context Content. Use Content for human-facing artifacts and evidence; do not write authoritative Contract/payment state into arbitrary Content fields.

To close coordination, an authorized manager chooses **End relationship**. The history remains readable to participants while the workspace becomes read-only.
TEXT,
                    'authorization' => <<<'TEXT'
Relationship access comes from RelationshipParticipant records, not Group Membership.

Recorded participants may view the Relationship and Context history. An invited participant may respond but cannot create/interact with workspace Content while the Relationship is proposed. Active participants gain normal participant collaboration. can_manage participants receive the stronger Context-management capabilities. Outsiders cannot view the Relationship or Context. Ended/cancelled Relationships remain participant-readable and non-writable.

Relationship activation never grants platform authority, Group authority, Group Membership, source authority for unrelated Content, or authority over another Relationship.
TEXT,
                    'ideal_target' => <<<'TEXT'
Phase 11 composes Conversation and a source-linked Timeline inside Relationship and other Contexts without making messages authoritative.

Later phases progressively attach Planner, Proposal/Negotiation, Contract, Commitments, Fulfillment and Accounting when the relationship's purpose and user actions require them. Capability discovery should be purpose-aware and progressive; the product must not force every Relationship through one universal workflow or giant relationship-type enum.

Multi-party creation, participant changes, broader people discovery and richer capability presentation may extend the same kernel without replacing its consent and authorization boundaries.
TEXT,
                    'misunderstandings' => <<<'TEXT'
Relationship does not mean friendship, Match, Contract, employment, ownership, investment right, loan/equity, fulfilled work, debt or payment.

A visible Need/Offer can originate a Relationship, but complementary Intents do not automatically Match. A participant accepting the Relationship accepts the coordination boundary only; exact negotiated terms still require later Proposal/Contract actions. Text saying “I agree” inside Content or future Conversation must not silently change authoritative lifecycle.
TEXT,
                ],
            ],
        ];
    }
}
