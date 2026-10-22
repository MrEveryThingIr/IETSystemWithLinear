<?php

use App\Actions\Capabilities\CopyLegacyProfessions;
use App\Actions\Categories\EnsureSharedCategories;
use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Groups\ManageGroupAgreement;
use App\Actions\Planner\MaterializePlannerHorizon;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use App\Support\Categories\CategoryCatalog;
use App\Support\DuePlanReminderEmitter;
use App\Support\Media\FaststartExistingVideos;
use App\Support\Media\ResumableUploads;
use App\Support\NotificationOutboxDispatcher;
use App\Support\Push\DevicePushDispatcher;
use App\Support\ScheduledArtisan;
use Illuminate\Console\Scheduling\Schedule as ConsoleSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('iet:bootstrap-superadmin {--username=} {--email=}', function (): int {
    $existingSuperadmin = PlatformAccessGrant::query()
        ->active()
        ->where('role', PlatformRole::Superadmin->value)
        ->exists();

    if ($existingSuperadmin) {
        $this->info('An active platform superadmin already exists. No changes were made.');

        return 0;
    }

    $username = trim((string) ($this->option('username') ?: config('bootstrap.superadmin.username')));
    $email = trim((string) ($this->option('email') ?: config('bootstrap.superadmin.email')));
    $password = (string) config('bootstrap.superadmin.password');

    if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 64) {
        $this->error('Choose a username between 3 and 64 characters.');

        return 1;
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $this->error('Choose a valid superadmin email address.');

        return 1;
    }

    if ($password === '' || $password === 'password') {
        if ($this->input->isInteractive() === false) {
            $this->error('Set a strong IET_BOOTSTRAP_SUPERADMIN_PASSWORD or run this command interactively.');

            return 1;
        }

        $password = (string) $this->secret('Choose the initial superadmin password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm the password');

        if (hash_equals($password, $confirmation) === false) {
            $this->error('The password confirmation did not match.');

            return 1;
        }
    }

    if (mb_strlen($password) < 12) {
        $this->error('The initial superadmin password must contain at least 12 characters.');

        return 1;
    }

    $conflictingUser = User::query()
        ->where('username', $username)
        ->orWhere('email', $email)
        ->exists();

    if ($conflictingUser) {
        $this->error('That username or email already belongs to an account. No privilege was changed.');

        return 1;
    }

    DB::transaction(function () use ($username, $email, $password): void {
        $user = new User;
        $user->forceFill([
            'username' => $username,
            'email' => $email,
            'email_verified_at' => now(),
            'status' => 'active',
            'password' => Hash::make($password),
        ])->save();

        $actor = $user->actor()->create();
        $actor->profile()->create([
            'display_name' => $username,
        ]);

        $grant = new PlatformAccessGrant;
        $grant->user()->associate($user);
        $grant->role = PlatformRole::Superadmin;
        $grant->granted_at = now();
        $grant->reason = 'First production bootstrap superadmin.';
        $grant->correlation_id = (string) Str::uuid();
        $grant->save();
    });

    $this->info('Initial production superadmin created.');
    $this->line('Username: '.$username);
    $this->line('Email: '.$email);

    return 0;
})->purpose('Create the first production superadmin exactly once');

Artisan::command('planner:materialize {--days=120}', function () {
    $days = (int) $this->option('days');
    $created = app(MaterializePlannerHorizon::class)->execute($days);
    $this->info("Materialized {$created} Planner Occurrences.");
})->purpose('Extend the rolling Planner Occurrence horizon');

Artisan::command('contracts:activate-due', function () {
    $activated = app(ActivateDueContractVersions::class)->execute();
    $this->info("Activated {$activated} due Contract versions.");
})->purpose('Activate accepted Contract versions whose effective time has arrived');

Artisan::command('notifications:dispatch-outbox {--limit=200}', function () {
    $count = app(NotificationOutboxDispatcher::class)->dispatchPending((int) $this->option('limit'));
    $this->info("Queued {$count} pending notification outbox records.");
})->purpose('Requeue undelivered durable notification outbox records');

Artisan::command('notifications:push-devices {--limit=200}', function () {
    $count = app(DevicePushDispatcher::class)->run((int) $this->option('limit'));
    $this->info("Sent {$count} notifications to devices.");
})->purpose('Send delivered notifications to subscribed devices and email important reminders');

Artisan::command('notifications:emit-due-reminders {--limit=250}', function () {
    $count = app(DuePlanReminderEmitter::class)->emit((int) $this->option('limit'));
    $this->info("Requested {$count} due Planner reminder notifications.");
})->purpose('Request due app notifications for Planner reminders');

Schedule::call(fn () => app(ManageGroupAgreement::class)->activateDue())
    ->name('agreements:activate-due')
    ->everyMinute()
    ->withoutOverlapping();

ScheduledArtisan::register(app(ConsoleSchedule::class), 'queue:prune-failed --hours=168')
    ->name('queue:prune-failed')
    ->dailyAt('02:10')
    ->withoutOverlapping();

ScheduledArtisan::register(app(ConsoleSchedule::class), 'queue:prune-batches --hours=168 --unfinished=168 --cancelled=168')
    ->name('queue:prune-batches')
    ->dailyAt('02:20')
    ->withoutOverlapping();

Artisan::command('media:prune-upload-sessions', function () {
    $count = app(ResumableUploads::class)->prune();
    $this->info("Expired {$count} abandoned upload sessions.");
})->purpose('Expire abandoned resumable uploads and delete their partial files');

Artisan::command('media:faststart-existing {--limit=50}', function () {
    $result = app(FaststartExistingVideos::class)->run((int) $this->option('limit'));
    $this->info("Checked {$result['checked']} videos, made {$result['optimized']} fast-start copies, {$result['failed']} failed.");
})->purpose('Make fast-start playback copies of older MP4 videos (index first, originals untouched)');

ScheduledArtisan::register(app(ConsoleSchedule::class), 'media:faststart-existing --limit=20')
    ->name('media:faststart-existing')
    ->dailyAt('03:10')
    ->withoutOverlapping();

ScheduledArtisan::register(app(ConsoleSchedule::class), 'media:prune-upload-sessions')
    ->name('media:prune-upload-sessions')
    ->hourly()
    ->withoutOverlapping();

ScheduledArtisan::register(app(ConsoleSchedule::class), 'planner:materialize --days=120')
    ->name('planner:materialize')
    ->dailyAt('00:05')
    ->withoutOverlapping();

ScheduledArtisan::register(app(ConsoleSchedule::class), 'contracts:activate-due')
    ->name('contracts:activate-due')
    ->everyMinute()
    ->withoutOverlapping();

ScheduledArtisan::register(app(ConsoleSchedule::class), 'notifications:dispatch-outbox --limit=500')
    ->name('notifications:dispatch-outbox')
    ->everyMinute()
    ->withoutOverlapping();

ScheduledArtisan::register(app(ConsoleSchedule::class), 'notifications:emit-due-reminders --limit=500')
    ->name('notifications:emit-due-reminders')
    ->everyMinute()
    ->withoutOverlapping();

ScheduledArtisan::register(app(ConsoleSchedule::class), 'notifications:push-devices --limit=300')
    ->name('notifications:push-devices')
    ->everyMinute()
    ->withoutOverlapping(10);

Artisan::command('iet:taxonomy:sync {--dry-run : Only report what the copy would do}', function (): int {
    $shared = app(EnsureSharedCategories::class);
    if ($this->option('dry-run')) {
        $report = app(CopyLegacyProfessions::class)->execute(true);
        $this->info('Would copy: '.$report['people'].' people skills, '.$report['business_members'].' business member skills. Nothing was written.');

        return 0;
    }
    if (! $shared->execute()) {
        $this->error('No active superadmin yet: run iet:bootstrap-superadmin first.');

        return 1;
    }
    Cache::forever('categories:shared:fingerprint', EnsureSharedCategories::fingerprint());
    CategoryCatalog::bumpVersion();
    $report = app(CopyLegacyProfessions::class)->execute();
    $catalog = app(CategoryCatalog::class);
    $linked = 0;
    DB::table('business_categories')->whereNull('concept_id')->orderBy('id')->each(function ($category) use ($catalog, &$linked): void {
        $conceptId = $catalog->exactSharedMatch((string) $category->name);
        if ($conceptId !== null) {
            DB::table('business_categories')->where('id', $category->id)->whereNull('concept_id')->update(['concept_id' => $conceptId]);
            $linked++;
        }
    });
    $this->info('Shared categories are up to date.');
    $this->info('Business categories linked to a shared category by exact name: '.$linked.'.');
    $this->info('Copied into capabilities: '.$report['people'].' people skills, '.$report['business_members'].' business member skills; new categories for unlisted skills: '.$report['new_categories'].'; skipped: '.$report['skipped'].'. Old records were not changed.');

    return 0;
})->purpose('Install or update the shared categories and copy old skills into capabilities (safe to repeat)');
