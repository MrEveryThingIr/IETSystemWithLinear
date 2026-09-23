<?php

namespace App\Support;

class SystemManualContent
{
    public const GROUP_NAME = 'IET System Manual';

    public const SPACE_NAME = 'Manual';

    public const SPACE_SLUG = 'manual';

    public const ROOT_TITLE = 'IET System Manual';

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
TEXT,
                    'how_to_use' => <<<'TEXT'
When using IET, first identify the boundary you are operating in: your personal workspace, a Group, a Group Space, or an Admission. Then identify whether you are reading human-facing Content, changing an authoritative domain object, or collaborating around one.

Prefer the purpose-specific UI. You should not need to manipulate raw database concepts to perform ordinary work.
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
Current Context kinds are Personal, GroupSpace, and Admission.

Personal Context holds an Actor's personal Content. GroupSpace Context powers Content/collaboration in a Group Space. Admission Context allows a candidate and authorized reviewers to work together before Membership exists.
TEXT,
                    'how_to_use' => <<<'TEXT'
When something seems “missing”, first verify that you are looking in the correct Context. Submission counts, Content libraries, annotations, and reviewer queues are Context-scoped.

Use Personal Context for private/general artifacts. Use GroupSpace Context for Group collaboration. Use Admission Context for pre-membership onboarding/application work.
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
Invitations bring a person toward a Group. Admission handles reviewed onboarding before Membership. The candidate can collaborate in an Admission Context without receiving ordinary Group access.
TEXT,
                    'current_behavior' => <<<'TEXT'
Invitations can support registration/login and resume Admission. Admission has formal lifecycle state and can own an Admission Context. Candidates remain outside normal Group participation until an explicit authorized finalization creates Membership.

Phase 7 can place structured applications/evidence inside the Admission Context, but the conversation-first Admission v2 experience is still the next major product phase.
TEXT,
                    'how_to_use' => <<<'TEXT'
Use the invitation link to enter the intended Group onboarding journey. If review is required, the candidate completes requested structured work inside the Admission Context. Reviewers should use explicit Admission and Evaluation actions rather than interpreting free-form notes as state transitions.
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
The Content annotation system already supports question, correction, idea, note, and comment kinds with exact revision/block/field/text/Asset/relationship anchors. Replies and answers preserve discussion history.

Annotations remain attached to the exact edition where they were created. A formal “this correction/idea was incorporated into official revision N” contribution-resolution relation is not implemented yet.
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
A contribution-resolution workflow should let maintainers mark a question/correction/idea as reviewed, accepted, rejected, superseded, or incorporated. When incorporated, the new official Content revision and relevant product release should explicitly reference the originating annotations.

Support/community participants should be able to answer questions, while official answers or accepted changes remain distinguishable from ordinary replies.
TEXT,
                    'misunderstandings' => <<<'TEXT'
An annotation is never silently edited into the origin. The origin stays clean and historically exact. Community enhancements and proposed changes are overlays until an authorized revision incorporates them.
TEXT,
                ],
            ],
        ];
    }
}
