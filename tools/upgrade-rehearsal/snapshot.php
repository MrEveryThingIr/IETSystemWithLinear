<?php

/*
 * Read-only snapshot of a database for the upgrade rehearsal: the row count of
 * every table and fingerprints of the records an upgrade must never change.
 * Works with any IET version (it only uses the database).
 *
 * php tools/upgrade-rehearsal/snapshot.php <app-dir> > snapshot.json
 */

$appDir = $argv[1] ?? getcwd();
require $appDir.'/vendor/autoload.php';
$app = require $appDir.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Only this database's tables (MySQL lists every schema on the server).
$database = DB::getDatabaseName();
$tables = collect(Schema::getTables())
    ->filter(fn (array $t): bool => ! isset($t['schema']) || $t['schema'] === null || $t['schema'] === $database || $t['schema'] === 'main')
    ->pluck('name')->unique()->sort()->values();
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = DB::table($table)->count();
}

// Records that must survive an upgrade unchanged (only columns that exist in every version).
$fingerprints = [
    'users' => ['id', 'username', 'email', 'password', 'locale'],
    'actors' => ['id', 'user_id', 'status'],
    'professions' => ['id', 'code', 'name'],
    'actor_professions' => '*',
    'business_membership_profession' => '*',
    'businesses' => ['id', 'uuid', 'name', 'slug', 'status', 'visibility'],
    'business_memberships' => ['id', 'business_id', 'actor_id', 'role', 'status'],
    'business_listings' => ['id', 'uuid', 'business_id', 'listing_type', 'status'],
    'actor_profile_intents' => ['id', 'uuid', 'actor_profile_id', 'kind', 'concept_id', 'title', 'status', 'visibility', 'cash_min', 'cash_max', 'cash_basis'],
    'contracts' => ['id', 'uuid', 'status'],
    'contract_service_terms' => ['id', 'uuid', 'unit_rate_minor', 'auto_create_plan', 'auto_recognize_obligation', 'plan_frequency', 'plan_starts_on'],
    'commitments' => ['id', 'uuid', 'quantity', 'status'],
    'fulfillments' => ['id', 'uuid', 'quantity', 'status'],
    'plans' => ['id', 'uuid', 'title', 'status'],
    'plan_occurrences' => ['id', 'uuid', 'status'],
    'bk_ledgers' => ['id', 'uuid', 'kind', 'name'],
    'bk_transactions' => ['id', 'uuid', 'type', 'status', 'amount', 'ledger_id', 'idempotency_key'],
    'conversations' => ['id', 'uuid', 'context_id', 'key'],
    'conversation_messages' => ['id', 'uuid', 'conversation_id', 'author_actor_id', 'body'],
    'conversation_message_revisions' => '*',
    'assets' => ['id', 'uuid', 'storage_key', 'sha256'],
    'space_contents' => ['id', 'uuid', 'status'],
    'groups' => ['id', 'name'],
    'concepts' => ['id', 'uuid', 'slug', 'status'],
];

$fp = [];
foreach ($fingerprints as $table => $columns) {
    if (! Schema::hasTable($table)) {
        continue;
    }
    $existing = Schema::getColumnListing($table);
    $columns = $columns === '*' ? $existing : array_values(array_intersect($columns, $existing));
    if ($columns === []) {
        continue;
    }
    $rows = DB::table($table)->orderBy($columns[0])->get($columns)->map(fn ($row) => json_encode(array_map(
        fn ($v) => is_numeric($v) ? (string) (0 + $v) : $v,
        (array) $row,
    )))->all();
    $fp[$table] = ['columns' => $columns, 'rows' => count($rows), 'sha1' => sha1(implode("\n", $rows))];
}

echo json_encode(['counts' => $counts, 'fingerprints' => $fp], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
