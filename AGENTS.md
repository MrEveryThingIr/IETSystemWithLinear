# IET Project Authority

Before nontrivial implementation, read in this order:

1. `docs/PROJECT_COMPASS.md`
2. `docs/CURRENT_STATE.md`
3. `docs/TARGET_ARCHITECTURE.md`
4. `docs/CAPABILITY_MESH_ARCHITECTURE.md`
5. `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`
6. `docs/PRODUCTION_ROADMAP.md`
7. `docs/CONTINUOUS_REMOTE_EXECUTION.md`
8. `docs/EXAMPLE_STORY_WORLD.md`
9. the active module contract/report
10. relevant ADRs
11. `.ai/rules/index.md` and every matching path rule

The repository is the durable source of truth. Chat history is discussion evidence only.

## Active development mode

The human owner has authorized **browser-gated selective Ideal-v1 assembly**.

Assembly branch:

`codex/ideal-v1-selective-assembly`

The project is not being rebuilt from scratch. Existing integrated code and later candidate branches are source material. Each module is reviewed on a dedicated branch, improved selectively, remotely validated, inspected by the owner in the browser, corrected if needed, and only then merged into the assembly.

**Do not begin implementation of the next module until the current module's browser gate is explicitly accepted.**

The exact module order and admission contract live in `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`.

## Current foundation

The selective assembly was created from accepted integration SHA:

`891b333c49f166e61b9fa466e30742b3d70996c0`

S0 remotely certified and strengthened that baseline. Its exact evidence is recorded in `docs/handoffs/S0-selective-assembly-baseline.md`.

Because the browser-gated process was adopted after S0 remote merge, the current gate is the S0 assembly browser smoke. M1 implementation begins only after that smoke is accepted.

## Phase discipline

- Work on one **selective assembly module** at a time.
- Preserve the module's pre-implementation plan and append actual decisions/evidence as implementation proceeds.
- A module may consume earlier accepted domains but must not silently redefine their authority.
- Keep the module on its review branch through owner browser acceptance; merge only the exact accepted head.
- Do not implement later milestones inside an earlier branch merely because target architecture describes them.
- Prefer the smallest new authoritative layer needed; reuse existing Context, Content, Concept, Asset, Profile, Submission, Group and policy/action systems.
- Never replace specialized domain truth with generic JSON merely for convenience.
- Record meaningful milestone results under `Development-CodexReports/`.
- Record exact local/browser commands and acceptance checks in `docs/LOCAL_ACCEPTANCE_WORKSHEET.md`.
- Update `docs/handoffs/continuous-ideal-v1.md` after every review/acceptance/merge checkpoint.

## Content / Context rule

There is one independent Content system.

- A Content item has an origin/home Context for authoring, ownership and authorization.
- GroupSpace is one Context kind, not the universal Content owner.
- Published Content may be presented/referenced from other authorized Contexts without copying it.
- Normal presentation may follow the current published revision.
- Evidence/history must pin the exact published revision and optional exact block/field/asset/relationship target.
- Posts, Articles, Books, Diaries, Albums, Evidence pages, Lessons and Questionnaires are Content purposes/Blueprint experiences, not parallel storage systems.
- Contexts compose independent capabilities. Do not create duplicate per-Group/per-Relationship versions of Content, Planner, Accounting, Submission, etc.

## Progressive capability rule

User journeys start with human intent and progressively reveal only relevant capabilities.

Examples:

- simple sale: Intent + optional Relationship/discussion + optional finance;
- paid work: Intent + Relationship + Contract + Planner + Fulfillment + finance;
- Riverside Home Project: Property + Service + Capital + Collaboration + multi-party Contract + Planner + Fulfillment + Accounting.

Do not create combinatorial domain types such as `employment_sale_financing`. Compose trustworthy kernels.

## Documentation discipline

Every material user-facing milestone updates both:

- repository developer/architecture docs;
- `App\Support\SystemManualContent`, which is materialized as ordinary versioned IET Content.

Manual content must teach exact UI interaction:

`WHO → WHERE → WHAT UI → CLICK/SELECT/TYPE → DURABLE RESULT → WHO CAN SEE → WHAT IT DOES NOT IMPLY`.

Use the canonical Alice/Bob/Carol/Diego story in `docs/EXAMPLE_STORY_WORLD.md` so registration examples continue naturally into Groups, intents, relationships, work, evidence and accounting.

English is the canonical editorial source. Other language catalogs may remain draft-equivalent until reviewed; never call machine-generated translation native-reviewed.

## Git discipline

- never develop directly on `main`;
- merge browser-accepted modules into `codex/ideal-v1-selective-assembly` through PR;
- use coherent review branches for each module;
- never force-push shared history;
- never rewrite an already recorded checkpoint to hide a later correction;
- use PR/integration boundaries where supported;
- record exact SHA when checkpoint-tag tooling is unavailable;
- production/public release tags remain separate from development checkpoints.

## Database discipline

- shared migrations are append-only;
- never assume `migrate:fresh`;
- preserve immutable evidence/history;
- test forward migration and rollback/reapply where supported;
- use transactions/locks/idempotency for race-sensitive mutations;
- derived balances/caches are never authoritative financial truth.

## Architectural invariants

- User authentication and Actor participation remain distinct.
- Platform authority, Group Membership and Context access remain distinct.
- Conversation/Content wording is collaboration/evidence, not hidden approval/acceptance/payment authority.
- Group Agreement and party-specific negotiated Contract remain distinct.
- Need/Offer is current intent, not Match/Proposal/Contract/obligation.
- Match is advisory and creates no obligation.
- Contract versions are explicit and immutable once accepted.
- Fulfillment records what happened against Commitments.
- financial consequence flows through explicit obligation/accounting actions; balance is derived.
- realtime transport is never authoritative.
- AI, when reintroduced later, may prepare proposals/drafts but does not bypass domain Actions.

## Stop conditions

Pause continuous mode only for a genuine architecture contradiction, destructive/data-loss requirement, unresolved authorization/legal/financial meaning, explicit new dependency/credential approval, unsafe Git state, or unavailable required remote validation.

Ordinary test failures are not stop conditions: fix them, add regression coverage and continue.

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
