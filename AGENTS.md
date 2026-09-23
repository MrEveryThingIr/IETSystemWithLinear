# IET Project Authority

Before any nontrivial implementation work, read the repository's canonical product documents in this order:

1. `docs/PROJECT_COMPASS.md`
2. `docs/CURRENT_STATE.md`
3. `docs/TARGET_ARCHITECTURE.md`
4. `docs/PRODUCTION_ROADMAP.md`
5. the currently active phase contract (`docs/PHASE_07_SUBMISSION_EVALUATION.md`)
6. relevant ADRs
7. `.ai/rules/index.md` and every matching path rule

The repository is the durable source of truth. Chat transcripts, legacy migrations, issue comments, and historical reports are supporting evidence only.

## Phase discipline

- Work on one accepted roadmap phase at a time.
- Do not implement later phases speculatively.
- Audit existing code/tests before changing architecture.
- Preserve frozen boundaries unless the human owner explicitly approves a new architecture decision.
- Record meaningful phase results under `Development-CodexReports/`.
- Stop at the phase exit gate for review before beginning the next phase.

## Documentation discipline

IET has one architecture and two intentionally different documentation surfaces:

- repository Markdown under `docs/` is developer/architecture authority;
- end-user manuals/help/courses are normal versioned IET Content, currently materialized in the authenticated `Reference` Context through the ordinary Blueprint/Reader/annotation system.

Do not create a parallel user-documentation product or a separately evolving handbook branch.

For every milestone that changes material user-facing behavior:

- update the official System Manual Content source while runtime work is still active;
- document WHO → WHERE/Context → WHAT object → ACTION → durable RESULT → WHO CAN SEE IT;
- distinguish current implemented behavior from ideal target behavior;
- preserve official editions as immutable published Content revisions;
- keep questions, notes, corrections and ideas as edition/section-specific annotation overlays until an authorized maintainer resolves them;
- when feedback is incorporated, link the immutable disposition to the later sealed official revision;
- keep contextual Help routing accurate for affected product surfaces;
- do not formally close the milestone until its material workflow is understandable from the System Manual.

English is the initial canonical editorial source. Persian is the next reviewed translation target; Arabic and Simplified Chinese follow the same reviewed translation lifecycle. AI-assisted translation may be draft material but is not automatically native-quality/verified.

Canonical architecture documents remain authoritative over explanatory System Manual prose when a conflict is discovered; fix the manual through a new official revision rather than silently changing architecture.

## AI assistance and development provenance discipline

For AI-assisted application behavior:

- AI plans against the exact current authorized object/revision; it does not receive direct database authority.
- Apply through existing policies and domain Actions, and reauthorize at mutation time.
- Reject stale plans after the target changes.
- Never auto-publish or convert natural-language wording into submit/approve/accept/finalize/payment authority.
- Never accept arbitrary generated PHP, Blade, JavaScript, SQL, shell code, or executable validation/configuration.
- Generated media must enter through the normal private Asset provenance/rights/scan/readiness pipeline before it can be attached or published.
- Development Origins are immutable supporting provenance. They may link a curated chat/design summary to phase/version/commits/docs, but canonical repository authority remains unchanged.
- Do not automatically copy private raw chat history into durable application records.

Binding cross-cutting contract: `docs/AI_ASSISTANCE_AND_DEVELOPMENT_PROVENANCE.md`.

## Current roadmap position

- Phase 0 — canonical architecture: complete.
- Phase 1 — production invitation/registration/admission journey: complete and accepted.
- Phase 2 — delivery and operations baseline: complete.
- Phase 3 — Concept Kernel: complete.
- Phase 4 — Actor/Party and progressive Profile: complete and human-owner accepted at `fef290d2f1d58f69ddab1fbfd00ec68ff2a186d7`.
- Phase 5 — Generic Content Context: **complete and human-owner accepted on 2026-09-22**.
- Accepted Phase 5 branch HEAD before closure docs: `2561eda91c3e1db84c77816e8c15324d8f0fd939`.
- Phase 6 — Content Blueprints and unified productized authoring: **complete and human-owner accepted for roadmap progression on 2026-09-22**.
- Frozen Phase 6 runtime candidate: `ad07445b16a708b4efd67461f5cef12201ffa8b1` (GitHub Actions run `35739828516`: 364 tests / 1934 assertions, PHPStan/Pint/Vite/ops/security green).
- Local Phase 6 closure on synchronized HEAD `b33bdcf`: three migrations applied; focused gate 20 tests / 121 assertions; full suite 364 / 1934; PHPStan and Vite green; clean diff/tree after restoring unrelated whole-repository Pint rewrites; browser review found no blocking defect.
- Phase 7 — Submission / Response / Evaluation is **runtime technically complete / remote-CI green; final owner-local/browser/mobile/RTL acceptance pending** on `feat/phase-07-submission-evaluation`. Binding contract: `docs/PHASE_07_SUBMISSION_EVALUATION.md`.
- Do not create parallel Personal/Group/Admission Content systems, silently upgrade existing Content, or turn evidence count into reputation.
- Phase 6 preserves the proven `SpaceContent*` substrate while making it Context-generic and Blueprint-first. Do not mass-rename it, remove legacy compatibility columns, rewrite sealed publication evidence, invent fake Groups, or pull Phase 7/8/9/10/11/13/14 work forward.
- Temporal/Profile boundaries from Phase 4 remain binding.

## Current pre-release slice

The explicitly authorized active slice is `feat/pre-release-office-intent-registry`, based on the accepted Phase 7/manual/AI-provenance line. Its purpose is a small invitation-only alpha: standalone account Access Invitation, guided Profile Need/Offer creation, permission-aware read-only Intent Directory, and non-binding cash/mixed-value negotiation preference.

This slice must not introduce automated Matching, Proposal/Contract/Commitment, Planner, ownership/capital rights, payment, or Accounting semantics. `ActorProfileIntent` remains current intent only. Phase 8 stays closed until this release gate is accepted.

The default release profile is `office_alpha`: keep ordinary navigation intentionally focused on Dashboard, Needs/Offers/Services and Profile, while preserving advanced kernels behind the `full` release profile. Do not delete mature domains merely to simplify the published surface.

AI assistance is disabled by default for the alpha; enabling it later still requires the binding AI contract.

Remote runtime candidate: `cb4ae0783dc8f0d8ad424422b4cc7d6a9dc01520`. GitHub Actions run `35903601901` is green at 419 tests / 2306 assertions with PHPStan/Pint/Vite/migrations/ops/backup/security green. Do not tag until owner-local/browser/mobile/RTL acceptance is recorded.

## Previous technical candidate

The Phase 7 + Documentation-as-Content runtime candidate is:

`6e6443051e93d4f0fd653758ba1979a7dc831de1`

on:

`feat/phase-07-submission-evaluation`

GitHub Actions run `35848121420` on that exact runtime candidate is green:

- **398 PHPUnit tests / 2169 assertions**;
- PHPStan: no errors;
- changed-file Pint: **327 files passed**;
- Vite production build: passed;
- Phase 7 rollback/reapply + scheduler/database-queue smoke: passed;
- SQLite backup → restore smoke: passed;
- npm audit: 0 vulnerabilities;
- Composer security audit: clean.

Phases 7A–7E remain technically complete. The post-Phase-7 documentation/audit slice is also technically green: authenticated Reference Context, official versioned System Manual Content, Guide/Documentation Blueprint, contextual Help routing, exact-section Question/Correction/Idea feedback, immutable feedback-disposition provenance, and explicit repository-source manual sync are automated.

The docs/audit pass also hardened Content Actions so required UUID/hash/evidence identity is supplied explicitly by authoritative Actions rather than relying only on Eloquent create events. This preserves deterministic behavior even in tests or tooling that fake events.

Do not begin Phase 8 runtime work until the owner completes the local migration/seed/browser acceptance of this candidate. The browser gate now includes both the existing Phase 7 submit/review journey and the System Manual / contextual Help / feedback experience. Conversation remains collaboration rather than authority; Phase 7 does not implement Admission v2 Conversation or realtime infrastructure. Future work must preserve the canonical connected-life proof in PROJECT_COMPASS: direct Contract → Commitment → Planner/Occurrence → Fulfillment/evidence → obligation → settlement/accounting, with Need/Offer/Matching optional and downstream of direct contracting.

## Architectural stop conditions

Stop and request review before code changes that would:

- change User/Actor identity semantics;
- bypass Invitation → Admission → Membership;
- weaken immutable Agreement/Content/accounting evidence;
- introduce a universal JSON entity in place of specialized domains;
- make GroupSpace the permanent universal container for all future Content;
- connect experimental financial instruments to external money;
- add destructive migrations or discard user data;
- silently change a frozen boundary in `docs/PROJECT_COMPASS.md`.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
