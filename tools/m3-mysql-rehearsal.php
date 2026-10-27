<?php

// M3 migration 2026_11_16 rehearsal on a newly created, explicitly named local database only.
require __DIR__.'/../vendor/autoload.php';

use App\Models\ContractVersion;
use App\Support\Deals\AgreementTerms;
use App\Support\Deals\DealTerms;
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
$target = 'iet_m3_rehearsal_'.date('Ymd_His');
if (! str_starts_with($target, 'iet_m3_rehearsal_') || $target === $connection['database']) {
    throw new RuntimeException('Rehearsal database must be a new iet_m3_rehearsal_* name, never the configured application database.');
}
$pdo = new PDO('mysql:host='.$connection['host'].';port='.$connection['port'], $connection['username'], $connection['password']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $target, 'database.connections.mysql.url' => null,
    'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync', 'broadcasting.default' => 'null']);
DB::purge('mysql');
Artisan::call('migrate', ['--force' => true]);
$migration = require database_path('migrations/2026_11_16_000000_add_terms_data_to_contract_versions.php');

// 1. Populate with Agreements as they exist BEFORE this migration (columns absent).
$migration->down();
if (Schema::hasColumn('contract_versions', 'terms_data')) {
    throw new RuntimeException('down() did not remove terms_data');
}
$versions = ContractVersion::factory()->count(3)->create();
$before = $versions->mapWithKeys(fn ($v) => [$v->id => DB::table('contract_versions')->where('id', $v->id)->first()])->all();

// 2. Apply: every historical row is unchanged and the new columns are null.
$migration->up();
foreach ($before as $id => $row) {
    $now = DB::table('contract_versions')->where('id', $id)->first();
    foreach ((array) $row as $key => $value) {
        if ($now->{$key} !== $value) {
            throw new RuntimeException("Historical Agreement column changed: {$key}");
        }
    }
    if ($now->terms_data !== null || $now->terms_hash !== null) {
        throw new RuntimeException('New columns must be null for historical versions');
    }
}
$first = ContractVersion::query()->findOrFail(array_key_first($before));
$resolved = AgreementTerms::of($first);

// 3. New data after the migration: an amendment-style row with typed terms round-trips.
$terms = ['title' => 'Rehearsal', 'summary' => 'A', 'currency' => 'IRT', 'clauses' => [['kind' => 'scope', 'text' => 'x']]];
$amended = ContractVersion::factory()->create(['terms_data' => $terms, 'terms_hash' => DealTerms::hash($terms)]);
$stored = ContractVersion::query()->findOrFail($amended->id);
// MySQL JSON normalises key order; the canonical hash must survive that.
$roundTrip = DealTerms::hash($stored->terms_data) === $stored->terms_hash;
$amended = $amended->id;

// 4. Rollback compatibility: down() drops only the two columns; every other value survives; up() re-applies.
DB::table('contract_versions')->where('id', $amended)->delete();
$migration->down();
foreach ($before as $id => $row) {
    $now = DB::table('contract_versions')->where('id', $id)->first();
    foreach ((array) $row as $key => $value) {
        if ($now->{$key} !== $value) {
            throw new RuntimeException("Rollback altered column: {$key}");
        }
    }
}
$migration->up();

$report = ['database' => $target, 'server' => DB::selectOne('SELECT VERSION() AS version')->version, 'migrations' => DB::table('migrations')->count(),
    'historical_versions' => count($before), 'historical_rows_unchanged_after_up' => true, 'new_columns_null_for_history' => true,
    'first_version_terms_resolved_without_error' => $resolved === null || is_array($resolved), 'typed_terms_round_trip' => $roundTrip,
    'rollback_preserves_rows' => true, 'reapply_ok' => Schema::hasColumn('contract_versions', 'terms_hash'),
    'non_innodb' => DB::table('information_schema.tables')->where('table_schema', $target)->where('table_type', 'BASE TABLE')->where('engine', '!=', 'InnoDB')->count()];
$directory = storage_path('release-acceptance/m3');
@mkdir($directory, 0777, true);
file_put_contents($directory.'/mysql-rehearsal.json', json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
$pdo->exec('DROP DATABASE `'.$target.'`');
echo "dropped {$target}\n";
