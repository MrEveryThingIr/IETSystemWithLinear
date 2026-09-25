<?php

use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Groups\ManageGroupAgreement;
use App\Actions\Planner\MaterializePlannerHorizon;
use App\Support\DuePlanReminderEmitter;
use App\Support\NotificationOutboxDispatcher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
