---
paths:
  - 'database/seeders/**'
---

# Seeders

## Keep DatabaseSeeder minimal and repeatable

`DatabaseSeeder` is the local/testing bootstrap, not the application architecture and not a mandatory demonstration dataset.

Current contract:

- local/testing only;
- creates/fetches `test@example.com` / `testuser`;
- ensures the User has an Actor;
- ensures one active Superadmin platform access grant;
- does not automatically create Groups, Spaces, Content, construction projects, or other domain demonstrations.

Demo/example datasets must be opt-in seeders and safe to rerun without deleting unrelated application data.

Examples may demonstrate domain behavior but must use the same Actions/invariants as normal application flows wherever practical.

Do not add destructive `migrate:fresh` assumptions.

## Legacy construction seeders

Existing construction seeders are historical/demo artifacts from an earlier architecture generation.

Do not extend them as the target domain architecture.

Future construction/project examples should eventually be represented through the accepted Group Blueprint / Domain Pack architecture when that roadmap phase is active.

## English workbook demo

`BritishEnglishFileIntermediatePlusDemoSeeder` is an opt-in local/testing fixture for Content/Book/Lesson/Page behavior.

Keep it separate from `DatabaseSeeder`.

When revisiting it, preserve rights/media safeguards and correct unnecessary rerun revision churn before treating it as a reusable Blueprint source.
