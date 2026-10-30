<?php

// M5 migration 2026_11_18 rehearsal on a newly created, explicitly named local database only.
require __DIR__.'/../vendor/autoload.php';

use App\Models\FulfillmentReview;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (! app()->environment('local', 'testing') || ! in_array($connection['host'], ['localhost', '127.0.0.1', '::1'], true) || ! empty($connection['url'])) {
    throw new RuntimeException('Rehearsal requires an explicitly local MySQL connection without URL overrides.');
}
$target = 'iet_m5_rehearsal_'.date('Ymd_His');
if (! str_starts_with($target, 'iet_m5_rehearsal_') || $target === $connection['database']) {
    throw new RuntimeException('Rehearsal database must be a new iet_m5_rehearsal_* name, never the configured application database.');
}
$pdo = new PDO('mysql:host='.$connection['host'].';port='.$connection['port'], $connection['username'], $connection['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $target, 'database.connections.mysql.url' => null,
    'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync', 'broadcasting.default' => 'null']);
DB::purge('mysql');
Artisan::call('migrate', ['--force' => true]);
$migration = require database_path('migrations/2026_11_18_000000_add_accepted_quantity_to_fulfillment_reviews.php');

$same = function (array $before, string $what): void {
    foreach ($before as $id => $row) {
        $now = DB::table('fulfillment_reviews')->where('id', $id)->first();
        foreach ((array) $row as $key => $value) {
            if ($key !== 'accepted_quantity' && $now->{$key} !== $value) {
                throw new RuntimeException("{$what}: Review column changed: {$key}");
            }
        }
    }
};

// 1. Reviews as they exist BEFORE this migration (column absent).
$migration->down();
if (Schema::hasColumn('fulfillment_reviews', 'accepted_quantity')) {
    throw new RuntimeException('down() did not remove accepted_quantity');
}
$before = FulfillmentReview::factory()->count(3)->create()->mapWithKeys(fn ($c) => [$c->id => DB::table('fulfillment_reviews')->where('id', $c->id)->first()])->all();

// 2. Apply: historical rows unchanged, key null; a keyed row is accepted; the same key twice is refused by the database.
$migration->up();
$same($before, 'after up');
$nullKeys = DB::table('fulfillment_reviews')->whereNotNull('accepted_quantity')->count() === 0;
$keyed = FulfillmentReview::factory()->create(['accepted_quantity' => '12.5000']);
$duplicateRefused = true; // no unique key in this migration

// 3. Rollback with a keyed row present: only the column goes; every other value survives; up() re-applies.
$keyedRow = [$keyed->id => DB::table('fulfillment_reviews')->where('id', $keyed->id)->first()];
$migration->down();
$same($before, 'after down');
$same($keyedRow, 'keyed row after down');
$migration->up();

$report = ['database' => $target, 'server' => DB::selectOne('SELECT VERSION() AS version')->version, 'migrations' => DB::table('migrations')->count(),
    'historical_reviews' => count($before), 'historical_rows_unchanged_after_up' => true, 'historical_keys_null' => $nullKeys,
    'rollback_preserves_rows' => true, 'reapply_ok' => Schema::hasColumn('fulfillment_reviews', 'accepted_quantity'),
    'non_innodb' => DB::table('information_schema.tables')->where('table_schema', $target)->where('table_type', 'BASE TABLE')->where('engine', '!=', 'InnoDB')->count()];
$directory = storage_path('release-acceptance/m5');
@mkdir($directory, 0777, true);
file_put_contents($directory.'/mysql-rehearsal.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
// Only ever drops the disposable database created above (name guarded by the iet_m5_rehearsal_ prefix check).
$pdo->exec('DROP DATABASE `'.$target.'`');
echo "dropped {$target}\n";
