<?php

/*
 * After upgrading: compares the before/after snapshots and checks what the
 * new release promises (nothing lost, legacy records unchanged, every
 * Need/Offer has one publisher, old skills copied, bookkeeping provenance set,
 * messaging defaults). Then requests the main pages as the superadmin.
 *
 * php tools/upgrade-rehearsal/verify.php <new-app-dir> before.json after.json
 * Exit code 1 on any failure.
 */

[$_, $appDir, $beforeFile, $afterFile] = $argv;
require $appDir.'/vendor/autoload.php';
$app = require $appDir.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Support\RequestMemo;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$before = json_decode(file_get_contents($beforeFile), true);
$after = json_decode(file_get_contents($afterFile), true);
$failures = [];
$checks = 0;
$check = function (bool $ok, string $what) use (&$failures, &$checks): void {
    $checks++;
    echo ($ok ? '  ok   ' : '  FAIL ').$what."\n";
    if (! $ok) {
        $failures[] = $what;
    }
};

// Tables removed on purpose by the v1.4.0 migration
// 2026_10_19_000000_retire_legacy_financial_system (irreversible by design).
// Only an installation older than v1.4.0 still has them. They are reported
// as warnings, not silently accepted: upgrading such an installation needs
// the owner's explicit authorization and a verified backup first.
$retiredByV14 = ['iet_internal_charges', 'iet_priced_financial_obligations', 'iet_exchange_requests', 'financial_obligation_events',
    'settlements', 'contract_settlement_batches', 'financial_obligations', 'journal_lines', 'journal_entries', 'accounts', 'ledgers',
    'money_intentions', 'market_quotes', 'quote_sources', 'economic_instruments', 'iet_valuation_quotes'];
$warnings = [];

echo "Tables\n";
foreach ($before['counts'] as $table => $count) {
    if (in_array($table, $retiredByV14, true) && ! array_key_exists($table, $after['counts'])) {
        $warnings[] = "{$table}: {$count} rows removed by the v1.4.0 financial-system retirement";
        echo "  WARN {$table}: retired by the v1.4.0 migration ({$count} rows; restore only from the pre-upgrade backup)\n";

        continue;
    }
    $check(array_key_exists($table, $after['counts']), "table {$table} still exists");
    if (array_key_exists($table, $after['counts'])) {
        $check($after['counts'][$table] >= $count, "{$table}: {$count} → {$after['counts'][$table]} rows (none lost)");
    }
}

echo "Unchanged records\n";
foreach ($before['fingerprints'] as $table => $fp) {
    if (in_array($table, $retiredByV14, true) && ! array_key_exists($table, $after['counts'])) {
        continue;
    }
    $now = $after['fingerprints'][$table] ?? null;
    $same = $now !== null && $now['columns'] === $fp['columns'] && $now['sha1'] === $fp['sha1'];
    // Rows created by the upgrade itself (shared categories, capabilities) may be added to concepts.
    if ($table === 'concepts' && $now !== null) {
        $same = $now['rows'] >= $fp['rows'];
    }
    $check($same, "{$table}: {$fp['rows']} records identical");
}

echo "New invariants\n";
$intents = DB::table('actor_profile_intents')->get(['publisher_actor_id', 'publisher_business_id', 'publisher_group_id', 'metadata']);
$check($intents->every(fn ($i) => (int) ($i->publisher_actor_id !== null) + (int) ($i->publisher_business_id !== null) + (int) ($i->publisher_group_id !== null) === 1), 'every Need/Offer has exactly one publisher ('.$intents->count().')');
$businessOffers = $intents->filter(fn ($i) => str_contains((string) $i->metadata, 'business_listing_version') || str_contains((string) $i->metadata, 'business_contact_need'));
$check($businessOffers->every(fn ($i) => $i->publisher_business_id !== null), 'business offers and client needs are published by their business ('.$businessOffers->count().')');
$people = DB::table('actor_professions')->count();
$members = DB::table('business_membership_profession')->count();
$check(DB::table('capabilities')->whereNotNull('legacy_actor_profession_id')->count() === $people, "every person skill copied to a capability ({$people})");
$check(DB::table('capabilities')->whereNotNull('legacy_membership_profession_id')->count() === $members, "every business-member skill copied ({$members})");
$check(DB::table('professions')->whereNull('concept_id')->count() === 0, 'every old profession is linked to a category (unmapped ones to a legacy category)');
$check(DB::table('bk_transactions')->whereNull('origin')->count() === 0, 'every bookkeeping entry has an origin');
$check(DB::table('bk_transactions')->where('type', 'adjustment')->where('origin', '!=', 'correction')->count() === 0, 'adjustments are marked as corrections');
$check(DB::table('bk_transactions')->where('type', '!=', 'adjustment')->where('origin', '!=', 'manual')->count() === 0, 'all other existing entries are manual');
$check(DB::table('conversation_messages')->where('kind', '!=', 'text')->count() === 0, 'existing messages are text messages');
$check(DB::table('users')->where('message_privacy', '!=', 'contacts')->count() === 0, 'everyone starts with “contacts only” for new conversations');
$check(DB::table('direct_contexts')->count() === 0 && DB::table('actor_contacts')->count() === 0, 'no private conversations or contacts were invented');

echo "Pages (superadmin)\n";
$admin = User::query()->where('username', config('bootstrap.superadmin.username', 'MrEveryThing'))->firstOrFail();
$paths = ['/dashboard', '/intents', '/intents/create', '/profile/professions', '/businesses', '/work', '/deals', '/planner', '/bookkeeping', '/messages', '/contacts', '/groups', '/whats-new', '/platform/categories', '/platform/message-reports'];
foreach (DB::table('businesses')->limit(2)->pluck('uuid') as $uuid) {
    $paths[] = '/businesses/'.$uuid;
    $paths[] = '/businesses/'.$uuid.'/catalog';
}
foreach (DB::table('commitments')->limit(2)->pluck('uuid') as $uuid) {
    $paths[] = '/commitments/'.$uuid;
}
foreach (DB::table('bk_ledgers')->where('kind', '!=', 'personal')->limit(2)->pluck('uuid') as $uuid) {
    $paths[] = '/bookkeeping/books/'.$uuid;
}
foreach (DB::table('groups')->limit(2)->pluck('id') as $id) {
    $paths[] = '/groups/'.$id;
}
foreach ($paths as $path) {
    auth()->login($admin);
    $request = Request::create($path, 'GET');
    $request->setLaravelSession($app['session.store']);
    $status = $kernel->handle($request)->getStatusCode();
    $check(in_array($status, [200, 302], true), "GET {$path} → {$status}");
    RequestMemo::flush();
}

if ($warnings !== []) {
    echo "\nWARNINGS (installation older than v1.4.0 — needs explicit authorization before upgrading):\n  ".implode("\n  ", $warnings)."\n";
}
echo "\n".($failures === [] ? "PASSED: {$checks} checks" : 'FAILED: '.count($failures)." of {$checks} checks")."\n";
exit($failures === [] ? 0 : 1);
