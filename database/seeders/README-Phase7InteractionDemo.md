# Phase 7 Interaction Demo

This is an **opt-in local/testing fixture** for browser acceptance of Phase 7 — Submission / Response / Evaluation.

It does not run from the normal production seed path and it does not implement Phase 8 Conversation or Phase 9 realtime transport.

## Seed

~~~bash
php artisan db:seed --class=Phase7InteractionDemoSeeder
~~~

The seeder is intended to be safely repeatable on a normal local development database.

All demo accounts use the development password:

~~~text
password
~~~

Accounts:

- reviewer / teacher: `test@example.com`
- learner: `phase7.learner@example.com`
- employment candidate: `phase7.candidate@example.com`
  - seeded with Persian locale to exercise RTL/localization

The seeder prints the exact local URLs and the candidate's immutable portfolio-evidence UUID.

## School exam proof

As `phase7.learner@example.com`:

1. open **Phase 7 — Laravel Fundamentals Exam**;
2. confirm the exam interaction appears inside the published Content Reader;
3. start the exam;
4. enter answers;
5. save the draft;
6. reload the page and confirm the draft resumes;
7. submit;
8. confirm submitted answers are read-only and the evidence hash is preserved.

As `test@example.com`:

1. use **Review submissions** from the Reader or open the printed review-queue URL;
2. confirm the learner draft was not visible before submit;
3. open the submitted attempt;
4. add scores/criterion feedback;
5. save the evaluation draft if desired;
6. finalize the evaluation;
7. confirm finalized feedback is shown to the learner.

## Employment application proof

As `phase7.candidate@example.com`:

1. open the printed Admission URL;
2. confirm ordinary GroupSpace access is still unavailable before Membership;
3. open **Phase 7 — Backend Developer Application** in the Admission Context;
4. answer the application questions;
5. attach a PDF CV;
6. paste the printed Personal portfolio-evidence UUID or evidence URL into the evidence response;
7. save/reload/resume;
8. submit;
9. confirm the submitted attempt becomes immutable.

As `test@example.com`:

1. open the employment review queue;
2. review the exact submitted answers, CV and portfolio evidence;
3. finalize an Evaluation;
4. confirm the Admission itself is still **under review** and no Membership was created merely because Evaluation finalized.

## Responsive / RTL acceptance

Check the Submission Card and reviewer surfaces at phone width and desktop width.

At minimum:

- English;
- Persian RTL;
- Arabic RTL if convenient.

Look for real blockers: clipped controls, horizontal overflow, inaccessible actions, unreadable ordering, broken file/evidence links, or lifecycle actions that do not match the stored state.

Minor visual polish may be deferred to the later whole-system polish pass.

## Architectural boundary

This demo deliberately proves:

~~~text
Content
+ InteractionDefinitionVersion
+ Submission / Responses / Assets / ContentEvidence
+ Evaluation
~~~

It does **not** make conversational wording authoritative and does not replace Admission approval, Agreement acceptance, Membership finalization, or future Contract actions.

Phase 8 will compose this structured substrate into the conversation-first Admission workspace. Phase 9 will add realtime delivery.
