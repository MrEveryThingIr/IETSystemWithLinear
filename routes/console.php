<?php

use App\Actions\Groups\ManageGroupAgreement;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
