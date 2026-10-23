<?php

require __DIR__.'/../vendor/autoload.php';

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\ReconcileOccurrenceWindows;
use App\Models\Actor;
use App\Models\User;
use App\PlanScheduleFrequency;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (! app()->environment('local') || config('database.default') !== 'mysql' || $connection['database'] !== 'iet_living_planner_demo') {
    throw new RuntimeException('This tool requires the named isolated local MySQL demo.');
}
$mode = $argv[1] ?? 'upgrade';
if ($mode === 'prepare-race') {
    $user = User::query()->where('email', 'planner-owner@example.test')->firstOrFail();
    $plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($user), $user, 'Concurrent catch-up fixture');
    app(CreatePlanScheduleRule::class)->execute($plan, $user, PlanScheduleFrequency::Once, now()->subDays(2)->toDateString(), '09:00', 30);
    echo "Prepared independent catch-up occurrence.\n";
    exit(0);
}
if ($mode === 'worker') {
    echo json_encode(['reconciled' => app(ReconcileOccurrenceWindows::class)->execute(500)]).PHP_EOL;
    exit(0);
}
if ($mode === 'verify-race') {
    $duplicates = DB::table('plan_occurrence_events')->select('plan_occurrence_id')->where('event_type', 'window_closed')
        ->groupBy('plan_occurrence_id')->havingRaw('COUNT(*) > 1')->count();
    $missing = DB::table('plan_occurrences')->whereNull('window_closed_at')->where('window_end_at', '<', now())->count();
    if ($duplicates !== 0 || $missing !== 0) {
        throw new RuntimeException('Concurrent reconciliation invariant failed.');
    }
    echo json_encode(['duplicate_window_events' => $duplicates, 'unprocessed_elapsed_windows' => $missing]).PHP_EOL;
    exit(0);
}
if ($mode !== 'upgrade') {
    throw new RuntimeException('Unknown mode.');
}
$pdo = new PDO('mysql:host='.$connection['host'].';port='.$connection['port'], $connection['username'], $connection['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database = 'iet_living_planner_upgrade_'.date('Ymd_His');
if (! preg_match('/^iet_living_planner_upgrade_\d{8}_\d{6}$/D', $database)) {
    throw new RuntimeException('Unsafe rehearsal name.');
}
$pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.connections.mysql.database' => $database]);
DB::purge('mysql');
$paths = collect(glob(database_path('migrations/*.php')))->filter(fn ($path) => ! str_contains(basename($path), '2026_11_0'))->values()->all();
Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]);
$user = Actor::factory()->create()->user;
$plan = app(CreatePlan::class)->execute(app(EnsurePersonalContext::class)->execute($user), $user, 'Preserved upgrade fixture');
$before = DB::table('plans')->where('id', $plan->id)->first();
$paths = glob(database_path('migrations/2026_11_0*.php'));
Artisan::call('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true]);
$after = DB::table('plans')->where('id', $plan->id)->first();
foreach (get_object_vars($before) as $key => $value) {
    if ($after->{$key} !== $value) {
        throw new RuntimeException('Upgrade changed existing Plan field '.$key);
    }
}
Artisan::call('migrate:rollback', ['--step' => 4, '--force' => true]);
if (DB::table('plans')->where('id', $plan->id)->value('title') !== $before->title) {
    throw new RuntimeException('Rollback lost existing Plan.');
}
Artisan::call('migrate', ['--force' => true]);
$report = ['database' => $database, 'server' => DB::selectOne('SELECT VERSION() AS version')->version,
    'migrations' => DB::table('migrations')->count(), 'populated_upgrade' => true, 'rollback_reapply' => true,
    'preserved_plan_id' => $plan->id, 'non_innodb' => DB::table('information_schema.tables')->where('table_schema', $database)->where('table_type', 'BASE TABLE')->where('engine', '!=', 'InnoDB')->count()];
file_put_contents(storage_path('release-acceptance/living/mysql-results.json'), json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
