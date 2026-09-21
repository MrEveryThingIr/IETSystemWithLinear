# Dependency and Security Update Process

## Purpose

Keep Laravel/PHP, JavaScript tooling, GitHub Actions, and direct dependencies current without turning dependency updates into unreviewed production changes.

## Automated signals

- Dependabot checks Composer weekly.
- Dependabot checks npm weekly.
- Dependabot checks GitHub Actions monthly.
- CI runs `composer audit --locked`.
- GitHub security/advisory features may add additional signals when enabled for the repository.

## Routine cadence

At least weekly:

1. review Dependabot/security alerts;
2. classify severity and whether the vulnerable code path is used;
3. update the narrowest safe dependency set;
4. keep Composer/npm lockfiles synchronized;
5. run the full required validation;
6. review release notes for framework/security-sensitive updates;
7. merge through the normal reviewed branch/PR path.

High/critical exploitable advisories should be treated as release-blocking until remediated or an explicit documented mitigation exists.

## Composer

Use:

~~~text
composer validate --strict
composer audit --locked
composer outdated --direct
~~~

Prefer targeted updates rather than unrestricted `composer update` on production branches.

After changing PHP dependencies run:

~~~text
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan test --compact
~~~

## npm

The repository commits `package-lock.json`; use the lockfile as the reproducible npm source:

~~~text
npm ci
npm audit
npm outdated
npm run build
~~~

Do not merge package manifest changes without the corresponding lockfile update.

## GitHub Actions

Dependabot may update action versions. Review permissions and release notes. Workflows should keep minimum required permissions; the CI workflow currently needs only repository contents read access.

## Major upgrades

Framework/runtime major upgrades require a dedicated branch and explicit compatibility review:

- PHP version/runtime extensions;
- Laravel;
- Livewire/Flux;
- Spatie Permission;
- PHPUnit/Larastan;
- Vite/Tailwind/Node.

Do not combine a major framework upgrade with unrelated domain feature work.

## Emergency process

For an actively exploited critical issue:

1. identify affected deployed versions;
2. apply vendor mitigation or targeted update;
3. run the narrowest security regression plus the full gate when feasible;
4. deploy with recorded commit/version;
5. verify health/logs;
6. document any temporary workaround and removal date.

Never hide a failed security gate by disabling the audit globally without an explicit owner-reviewed exception.
