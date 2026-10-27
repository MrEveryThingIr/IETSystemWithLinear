<?php

// M4 migration 2026_11_17 rehearsal on a newly created, explicitly named local database only.
require __DIR__.'/../vendor/autoload.php';

use App\Models\Commitment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (! app()->environment('local', 'testing') || ! in_array($connection['host'], ['localhost', '127.0.0.1', '::1'], true) || ! empty($connection['url'])) {
    throw new RuntimeException('Rehearsal requires an explicitly local MySQL connection without URL overrides.');
}
$target = 'iet_m4_rehearsal_'.date('Ymd_His');
if (! str_starts_with($target, 'iet_m4_rehearsal_') || $target === $connection['database']) {
    throw new RuntimeException('Rehearsal database must be a new iet_m4_rehearsal_* name, never the configured application database.');
}
$pdo = new PDO('mysql:host='.$connection['host'].';port='.$connection['port'], $connection['username'], $connection['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $target, 'database.connections.mysql.url' => null,
    'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync', 'broadcasting.default' => 'null']);
DB::purge('mysql');
Artisan::call('migrate', ['--force' => true]);
$migration = require database_path('migrations/2026_11_17_000000_add_derivation_key_to_commitments.php');

$same = function (array $before, string $what): void {
    foreach ($before as $id => $row) {
        $now = DB::table('commitments')->where('id', $id)->first();
        foreach ((array) $row as $key => $value) {
            if ($key !== 'derivation_key' && $now->{$key} !== $value) {
                throw new RuntimeException("{$what}: Commitment column changed: {$key}");
            }
        }
    }
};

// 1. Commitments as they exist BEFORE this migration (column absent).
$migration->down();
if (Schema::hasColumn('commitments', 'derivation_key')) {
    throw new RuntimeException('down() did not remove derivation_key');
}
$before = Commitment::factory()->count(3)->create()->mapWithKeys(fn ($c) => [$c->id => DB::table('commitments')->where('id', $c->id)->first()])->all();

// 2. Apply: historical rows unchanged, key null; a keyed row is accepted; the same key twice is refused by the database.
$migration->up();
$same($before, 'after up');
$nullKeys = DB::table('commitments')->whereNotNull('derivation_key')->count() === 0;
$keyed = Commitment::factory()->create(['derivation_key' => '1:rehearsal']);
$duplicateRefused = false;
try {
    Commitment::factory()->create(['derivation_key' => '1:rehearsal']);
} catch (QueryException $e) {
    $duplicateRefused = (int) ($e->errorInfo[1] ?? 0) === 1062;
}

// 3. Rollback with a keyed row present: only the column goes; every other value survives; up() re-applies.
$keyedRow = [$keyed->id => DB::table('commitments')->where('id', $keyed->id)->first()];
$migration->down();
$same($before, 'after down');
$same($keyedRow, 'keyed row after down');
$migration->up();

$report = ['database' => $target, 'server' => DB::selectOne('SELECT VERSION() AS version')->version, 'migrations' => DB::table('migrations')->count(),
    'historical_commitments' => count($before), 'historical_rows_unchanged_after_up' => true, 'historical_keys_null' => $nullKeys,
    'duplicate_key_refused_by_database' => $duplicateRefused, 'rollback_preserves_rows' => true, 'reapply_ok' => Schema::hasColumn('commitments', 'derivation_key'),
    'non_innodb' => DB::table('information_schema.tables')->where('table_schema', $target)->where('table_type', 'BASE TABLE')->where('engine', '!=', 'InnoDB')->count()];
$directory = storage_path('release-acceptance/m4');
@mkdir($directory, 0777, true);
file_put_contents($directory.'/mysql-rehearsal.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
// Only ever drops the disposable database created above (name guarded by the iet_m4_rehearsal_ prefix check).
$pdo->exec('DROP DATABASE `'.$target.'`');
echo "dropped {$target}\n";
