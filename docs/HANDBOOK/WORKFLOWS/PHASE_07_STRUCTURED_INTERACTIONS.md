# Phase 7 Workflow — Submission, Response and Evaluation

## What Phase 7 actually does

Phase 7 provides one structured-interaction engine for cases such as school exams, employment applications, questionnaires, assignments and requested evidence.

It deliberately does **not** yet provide conversation-first Admission, realtime notifications, Contract/Commitment or Planner behavior.

~~~text
published Content edition
        +
active InteractionDefinitionVersion
        ↓
eligible Actor starts
        ↓
private Submission draft
        ↓
Responses
        ↓
explicit Submit
        ↓
reviewer-visible immutable Submission
        ↓
optional Evaluation
        ↓
finalized feedback visible to submitter
~~~

## Why the reviewer can show 0 submissions

Typing answers is not submission.

~~~text
Start exam/application
→ Submission(status=draft)

type answers
→ browser/Livewire state until a save/submit action persists them

Save draft
→ Responses persist
→ Submission still draft
→ reviewers intentionally cannot see it
→ review count remains 0

Submit exam/application
→ required responses validated
→ evidence sealed
→ Submission(status=submitted)
→ reviewer can see it
→ submitted count increases
~~~

Draft privacy is deliberate.

## Demo accounts

Run:

~~~bash
php artisan db:seed --class=Phase7InteractionDemoSeeder
~~~

All demo passwords are password.

| Human role | Account | Intended use |
| --- | --- | --- |
| Reviewer / teacher | test@example.com | Review school exam and employment application |
| Learner | phase7.learner@example.com | Submit school exam |
| Employment candidate | phase7.candidate@example.com | Submit employment application |

The command prints the exact URLs for the two Contexts and review queues.

## School exam — exact workflow

The relationship is:

~~~text
Learner Actor
    ↓ submits to
School GroupSpace Context
    ↓ reviewed by
authorized Space/Group reviewer
~~~

Sign in as phase7.learner@example.com and open the printed School exam URL.

In the published Content Reader find **Structured interaction → Laravel Fundamentals Exam**.

If there is no existing attempt, click **Start exam**. Fill the required answers.

Use **Save draft** to preserve work without exposing it to reviewers.

To create the reviewer-visible Submission, click **Submit exam**.

After successful submission the attempt becomes immutable, its evidence hash is sealed, and the reviewer queue can see it.

The demo exam allows **one attempt per learner per active interaction version**. If this learner already submitted that attempt, a second fresh attempt is intentionally unavailable until another eligible learner or a new interaction version is used.

## Who can review the school exam?

It is **not Owner-only**.

For a GroupSpace Context, review authority currently comes from either Group authority with the manage_spaces permission, or an explicit Space participant rule with role manager.

The built-in Group Owner has the required authority, so test@example.com is the demo reviewer. A normal Member is not automatically a reviewer.

A reviewer cannot evaluate their own Submission.

## Employment application — exact workflow

~~~text
Candidate Actor
    ↓ submits to
Admission Context
    ↓ reviewed by
Group Actor with manage_admissions
~~~

Sign in as phase7.candidate@example.com and open the printed employment application URL.

Start the application, answer required questions and attach the required PDF CV.

The portfolio evidence field is optional; the demo seeder prints an immutable Personal Content evidence UUID/link that may be pasted there.

**Save draft** remains private. **Submit application** makes the attempt reviewer-visible and immutable.

The demo application allows up to three attempts and supports withdrawal according to its interaction settings.

## Who can review the employment application?

It is also **not Owner-only**.

An Admission reviewer is any authorized Group participant with the manage_admissions permission.

The built-in Owner has it, which is why test@example.com works in the fixture. A future/custom Group role may also receive manage_admissions.

The candidate cannot evaluate their own Submission.

## Reviewer workflow

Sign in as the authorized reviewer and open the **same Context** that received the Submission.

The Context Content page exposes:

~~~text
Review submissions (N submitted)
~~~

The number counts currently submitted attempts in that Context.

Open it to enter the review queue, then select the submitted attempt. The reviewer can inspect the sealed answers/evidence, start an Evaluation, save an Evaluation draft, and finalize feedback.

Finalizing an Evaluation does **not** approve/reject Admission, create Membership, change Group Agreement, create a Contract, or create a financial obligation.

## Context scoping

A Submission belongs to one exact Context.

~~~text
School exam Submission
→ School GroupSpace Context review queue

Employment application Submission
→ that candidate's Admission Context review queue
~~~

Opening another Context may correctly show zero even while a Submission exists elsewhere.

## Troubleshooting a 0 count

Check, in order:

1. Are you looking at the exact Context containing the exam/application?
2. Did the submitter click **Submit**, not only type answers or Save draft?
3. Are you signed in as an Actor authorized to review that Context?
4. Was the interaction submitted under another Context/version?
5. For the school fixture, has the learner already consumed the single allowed attempt?

If the exact Context still shows zero after a confirmed successful submit, treat it as a defect.
