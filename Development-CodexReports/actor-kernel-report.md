# Actor kernel implementation report

Project: C:\laragon\www\EveryThing. No commits made.

## Implemented

- Added the four-column actors kernel: id, nullable unique user_id, created_at, updated_at.
- User deletion sets user_id to NULL, retaining the Actor; Actor deletion leaves the User intact.
- Actor uses HasFactory with default guarded attributes. Association writes explicitly use associate/dissociate or the User relationship; no convenience fillable fields were added.
- User::actor() is HasOne; Actor::user() is BelongsTo; no withDefault().
- RegisterUser creates one Actor after creating User, in the existing transaction, before scheduling Registered after commit.
- ActorFactory defaults to an associated User and supports withoutUser(). UserFactory is unchanged.
- Seeder explicitly creates an Actor for its User.
- Added four Livewire/Flux management pages in the existing app shell, with paginated listing, details, optional user association, editing, and confirmation-dialog deletion.
- Available-user validation and unique constraint protect associations; uniqueness races are converted to validation feedback.
- Added Actors as a real sidebar link using actors.* active state.
- No Entity, profiles, roles, permissions, groups, memberships, invitations, Filament, soft deletes, or additional Actor fields.

## Live development database

Applied only the new migration 2026_09_07_182924_create_actors_table. The accepted users migration was not modified. Existing data was preserved.

Boost initially could not connect because MySQL was stopped. After the user started it, Boost found one existing User. A one-time transaction created its missing Actor without dispatching registration notifications. No backfill command/framework was added to the project.

Final Boost counts: 1 User, 1 Actor, 0 Users without an Actor.

Boost verified the live MySQL schema:

| Column | Type | Nullable | Details |
|---|---|---|---|
| id | bigint unsigned | No | Auto-increment primary key |
| user_id | bigint unsigned | Yes | Unique index, FK to users.id, ON DELETE SET NULL |
| created_at | timestamp | Yes | Standard timestamp |
| updated_at | timestamp | Yes | Standard timestamp |

No triggers or additional constraints/fields. FK update behavior: NO ACTION.

## Routes

All four routes inherit web and require auth, account.active and verified. GET routes also support HEAD. Livewire update requests reapply the previously configured persistent middleware.

| Method | URI | Name | Livewire component |
|---|---|---|---|
| GET | /actors | actors.index | App\Livewire\Actors\Index |
| GET | /actors/create | actors.create | App\Livewire\Actors\Create |
| GET | /actors/{actor} | actors.show | App\Livewire\Actors\Show |
| GET | /actors/{actor}/edit | actors.edit | App\Livewire\Actors\Edit |

Existing auth routes/middleware and Livewire registration logic were unchanged. Registration now provisions the Actor transactionally.

## Verification

- Focused Actor tests: 20 passed, 96 assertions.
- Existing auth suite (including extended registration tests): 34 passed, 193 assertions.
- npm run build: passed.
- composer quality: exit 0. Pint passed, PHPStan zero errors, full suite 71 passed / 319 assertions.
- git diff --check: passed; a Windows line-ending normalization notice remains for the sidebar view.
- Actor pages render through HTTP tests; create, update, delete and association changes are covered through Livewire tests. A real Livewire HTTP update test proves verification middleware is reapplied.
- Tests use SQLite in memory; the deployed schema and backfill counts were inspected separately on MySQL using Boost's local tool runner. Connected Boost MCP tools remain unavailable in this task.

## Limitations and operational notes

- TEMPORARY AUTHORIZATION: every authenticated, active, verified user can list and manage ALL Actors and their user associations. There are no ownership, role or permission restrictions, as requested.
- Registration provisions exactly one Actor, but explicit detachment, reassignment or deletion through management may subsequently leave a User without an Actor. The database guarantees at most one, not mandatory existence.
- Deletion is hard deletion and is currently acceptable because no dependent domain tables exist. Future domain foreign keys/deletion rules must prevent deleting referenced Actors; there is no speculative dependency registry.
- The eligible-user select loads all eligible users; searchable/paginated selection is deferred until scale warrants it.
- Windows denied overwriting old compiled-view cache files. Checks passed using a fresh temporary VIEW_COMPILED_PATH, with no application configuration changes. PHPUnit also could not overwrite its pre-existing administrator-owned .phpunit.result.cache; composer quality still exited successfully and all tests passed. Focused runs used --do-not-cache-result.
- The frontend build retains the existing non-blocking warning about the optional fontaine package.
- Browser interaction was not manually tested in this milestone.

## Every changed file

### Modified

- `C:\laragon\www\EveryThing\app\Actions\Auth\RegisterUser.php`
- `C:\laragon\www\EveryThing\app\Models\User.php`
- `C:\laragon\www\EveryThing\database\seeders\DatabaseSeeder.php`
- `C:\laragon\www\EveryThing\resources\views\components\app\sidebar.blade.php`
- `C:\laragon\www\EveryThing\routes\web.php`
- `C:\laragon\www\EveryThing\tests\Feature\Auth\RegistrationTest.php`

### Added

- `C:\laragon\www\EveryThing\app\Livewire\Actors\Create.php`
- `C:\laragon\www\EveryThing\app\Livewire\Actors\Edit.php`
- `C:\laragon\www\EveryThing\app\Livewire\Actors\Index.php`
- `C:\laragon\www\EveryThing\app\Livewire\Actors\Show.php`
- `C:\laragon\www\EveryThing\app\Models\Actor.php`
- `C:\laragon\www\EveryThing\database\factories\ActorFactory.php`
- `C:\laragon\www\EveryThing\database\migrations\2026_09_07_182924_create_actors_table.php`
- `C:\laragon\www\EveryThing\resources\views\livewire\actors\create.blade.php`
- `C:\laragon\www\EveryThing\resources\views\livewire\actors\edit.blade.php`
- `C:\laragon\www\EveryThing\resources\views\livewire\actors\index.blade.php`
- `C:\laragon\www\EveryThing\resources\views\livewire\actors\show.blade.php`
- `C:\laragon\www\EveryThing\tests\Feature\Actors\ActorManagementTest.php`
- `C:\laragon\www\EveryThing\tests\Feature\Models\ActorTest.php`

The accompanying actor-kernel.diff includes all modified and new files.
