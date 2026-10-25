<?php

// Additive/rollback rehearsal on a newly created, explicitly named local database only.
require __DIR__.'/../vendor/autoload.php';

use App\Models\ActorProfileIntent;
use App\Models\Relationship;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (! app()->environment('local', 'testing') || ! in_array($connection['host'], ['localhost', '127.0.0.1', '::1'], true)
    || ! empty($connection['url'])) {
    throw new RuntimeException('Rehearsal requires an explicitly local MySQL connection without URL overrides.');
}
$target = 'iet_m2_rehearsal_'.date('Ymd_His');
if (! preg_match('/^iet_m2_rehearsal_\d{8}_\d{6}$/D', $target)) {
    throw new RuntimeException('Invalid rehearsal database name.');
}
$pdo = new PDO('mysql:host='.$connection['host'].';port='.$connection['port'], $connection['username'], $connection['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $target, 'database.connections.mysql.url' => null,
    'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync', 'broadcasting.default' => 'null']);
DB::purge('mysql');
Artisan::call('migrate', ['--force' => true]);
$migration = require database_path('migrations/2026_11_10_000000_create_adaptive_intention_foundation.php');
$migration->down();
$post = ActorProfileIntent::factory()->create(['cash_min' => '50.00', 'currency_code' => 'USD', 'metadata' => ['legacy_unknown' => 'preserved']]);
$deal = Relationship::factory()->create(['originating_intent_id' => $post->id]);
$before = $post->fresh()->getRawOriginal();
$migration->up();
foreach ($before as $key => $value) {
    if ($post->fresh()->getRawOriginal($key) !== $value) {
        throw new RuntimeException('Existing field changed: '.$key);
    }
}
if ($deal->fresh()->originating_intent_version_id !== $post->fresh()->published_version_id) {
    throw new RuntimeException('Deal version was not backfilled.');
}
$migration->down();
$migration->up();
$report = ['database' => $target, 'server' => DB::selectOne('SELECT VERSION() AS version')->version,
    'migrations' => DB::table('migrations')->count(), 'existing_fields_preserved' => true, 'deal_version_backfill' => true,
    'rollback_reapply' => true, 'non_innodb' => DB::table('information_schema.tables')->where('table_schema', $target)->where('table_type', 'BASE TABLE')->where('engine', '!=', 'InnoDB')->count()];
$directory = storage_path('release-acceptance/m2');
@mkdir($directory, 0777, true);
file_put_contents($directory.'/mysql-rehearsal.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
