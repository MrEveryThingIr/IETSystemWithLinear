<?php

use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Groups\ManageGroupAgreement;
use App\Actions\Planner\MaterializePlannerHorizon;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use App\Support\DuePlanReminderEmitter;
use App\Support\NotificationOutboxDispatcher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
        $user = User::query()->create([
            'username' => $username,
            'email' => $email,
            'email_verified_at' => now(),
            'status' => 'active',
            'password' => Hash::make($password),
        ]);

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

Artisan::command('notifications:emit-due-reminders {--limit=250}', function () {
    $count = app(DuePlanReminderEmitter::class)->emit((int) $this->option('limit'));
    $this->info("Requested {$count} due Planner reminder notifications.");
})->purpose('Request due app notifications for Planner reminders');

Schedule::call(fn () => app(ManageGroupAgreement::class)->activateDue())
    ->name('agreements:activate-due')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=168')
    ->name('queue:prune-failed')
    ->dailyAt('02:10')
    ->withoutOverlapping();

Schedule::command('queue:prune-batches --hours=168 --unfinished=168 --cancelled=168')
    ->name('queue:prune-batches')
    ->dailyAt('02:20')
    ->withoutOverlapping();

Schedule::command('planner:materialize --days=120')
    ->name('planner:materialize')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Schedule::command('contracts:activate-due')
    ->name('contracts:activate-due')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('notifications:dispatch-outbox --limit=500')
    ->name('notifications:dispatch-outbox')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('notifications:emit-due-reminders --limit=500')
    ->name('notifications:emit-due-reminders')
    ->everyMinute()
    ->withoutOverlapping();
