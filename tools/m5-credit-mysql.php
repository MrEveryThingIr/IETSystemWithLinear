<?php

// Disposable MySQL regression + real concurrent requests. Never uses the owner's schema.
require __DIR__.'/../vendor/autoload.php';
use App\Actions\Bookkeeping\AddLedgerParty;
use App\Actions\Bookkeeping\CreateLedger;
use App\Actions\Bookkeeping\DecideOnEntry;
use App\Actions\Bookkeeping\RecordSharedEntry;
use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Actor;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Support\Bookkeeping\CreditAccounting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$c = config('database.connections.mysql');
if (! app()->environment('local', 'testing') || ! in_array($c['host'], ['localhost', '127.0.0.1', '::1'], true) || ! empty($c['url'])) {
    throw new RuntimeException('Local MySQL without URL override required.');
}
$worker = ($argv[1] ?? '') === 'worker';
$target = $worker ? ($argv[2] ?? '') : 'iet_m5_credit_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
if (! preg_match('/^iet_m5_credit_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}$/D', $target) || (! $worker && $target === $c['database'])) {
    throw new RuntimeException('Unsafe disposable database name.');
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $target, 'database.connections.mysql.url' => null, 'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync', 'broadcasting.default' => 'null', 'hashing.bcrypt.rounds' => 4]);
DB::purge('mysql');
if ($worker) {
    [$mode, $bookId, $actorId, $otherId, $sourceId, $key] = array_slice($argv, 3);
    $book = Ledger::query()->findOrFail($bookId);
    $actor = Actor::query()->findOrFail($actorId);
    $other = Actor::query()->findOrFail($otherId);
    DB::beginTransaction();
    DB::table('bk_transactions')->count(); // deliberately establish an old repeatable-read snapshot
    try {
        if ($mode === 'approve') {
            $entry = app(DecideOnEntry::class)->approve(Transaction::query()->findOrFail($sourceId), $actor->user);
        } else {
            $entry = app(RecordSharedEntry::class)->execute($book, $actor->user, $mode === 'refund' ? TransactionType::Refund : TransactionType::Payment, $book->partyFor($actor), $book->partyFor($other), $mode === 'refund' ? 600_000 : 1_000_000, '2026-10-29', 'Concurrent request', allocations: [], idempotencyKey: $key, refundPayment: $mode === 'refund' ? Transaction::query()->findOrFail($sourceId) : null);
        }
        DB::commit();
        echo json_encode(['entry' => $entry->id]).PHP_EOL;
    } catch (ValidationException) {
        DB::rollBack();
        echo json_encode(['rejected_capacity' => true]).PHP_EOL;
    }
    exit;
}
$pdo = new PDO('mysql:host='.$c['host'].';port='.$c['port'], $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$env = [...getenv(), 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $target, 'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'], 'DB_URL' => '', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null'];
try {
    echo 'Disposable database: '.$target.PHP_EOL;
    $cmd = [PHP_BINARY, 'vendor/bin/phpunit', '--no-progress', 'tests/Feature/Bookkeeping', 'tests/Feature/DealMoneyTest.php', 'tests/Feature/DealOnAccountTest.php', 'tests/Feature/DealConstructionScenarioTest.php', 'tests/Feature/DealMeasuredWorkTest.php', 'tests/Feature/DealWorkTest.php', 'tests/Feature/DealPerformanceBudgetTest.php', 'tests/Feature/DealDailyWorkMoneyTest.php'];
    $test = proc_open($cmd, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__), $env);
    $testExit = proc_close($test);
    // The PHPUnit schema remains; its test fixture transactions have rolled back.
    $a = Actor::factory()->create();
    $b = Actor::factory()->create();
    $book = app(CreateLedger::class)->execute($a->user, LedgerKind::Shared, 'Parallel credit test');
    app(AddLedgerParty::class)->execute($book, $a->user, $b->user->username, null);
    $parallel = function (array $jobs) use ($target, $env, $book): array {
        $processes = [];
        // Hold the ledger until both processes are running so their writes overlap.
        DB::beginTransaction();
        Ledger::query()->whereKey($book->id)->lockForUpdate()->firstOrFail();
        try {
            foreach ($jobs as $job) {
                $process = proc_open([PHP_BINARY, __FILE__, 'worker', $target, ...array_map('strval', $job)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 20;
            do {
                $waiters = DB::selectOne("SELECT COUNT(DISTINCT w.REQUESTING_THREAD_ID) AS total FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID WHERE l.OBJECT_SCHEMA = ? AND l.OBJECT_NAME = 'bk_ledgers'", [$target])->total;
                if ((int) $waiters >= count($jobs)) {
                    break;
                }
                usleep(20_000);
            } while (microtime(true) < $deadline);
            if ((int) $waiters < count($jobs)) {
                throw new RuntimeException('Could not prove concurrent ledger lock contention.');
            }
            echo 'Verified '.count($jobs).' concurrent MySQL ledger lock waiters'.PHP_EOL;
        } finally {
            DB::commit();
        }
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0) {
                throw new RuntimeException('Worker failed: '.$err.$out);
            }
            $results[] = json_decode(trim($out), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    };
    $same = $parallel([['create', $book->id, $a->id, $b->id, 0, 'parallel-duplicate'], ['create', $book->id, $a->id, $b->id, 0, 'parallel-duplicate']]);
    if ($same[0]['entry'] !== $same[1]['entry']) {
        throw new RuntimeException('Duplicate cash created');
    }
    $payment = Transaction::query()->findOrFail($same[0]['entry']);
    app(DecideOnEntry::class)->approve($payment, $b->user);
    $returns = $parallel([['refund', $book->id, $b->id, $a->id, $payment->id, 'parallel-return-a'], ['refund', $book->id, $b->id, $a->id, $payment->id, 'parallel-return-b']]);
    if (count(array_filter($returns, fn ($r) => isset($r['rejected_capacity']))) !== 1) {
        throw new RuntimeException('Concurrent returns overreserved credit');
    }
    $returnId = collect($returns)->first(fn ($r) => isset($r['entry']))['entry'];
    app(DecideOnEntry::class)->reject(Transaction::query()->findOrFail($returnId), $a->user, 'No return; test reservation release');
    $debts = [];
    foreach ([1, 2] as $i) {
        $debts[] = app(RecordSharedEntry::class)->execute($book, $a->user, TransactionType::Obligation, $book->partyFor($a), $book->partyFor($b), 600_000, '2026-10-29');
    }
    $parallel([['approve', $book->id, $b->id, $a->id, $debts[0]->id, ''], ['approve', $book->id, $b->id, $a->id, $debts[1]->id, '']]);
    $s = app(CreditAccounting::class)->snapshot($book);
    if ($s['payments'][$payment->id]['applied'] !== 1_000_000 || array_sum(array_column($s['obligations'], 'outstanding')) !== 200_000 || $s['issues'] !== []) {
        throw new RuntimeException('Parallel allocation invariant failed');
    }
    echo json_encode(['parallel_duplicate' => 'passed', 'parallel_returns' => 'passed', 'parallel_confirmations' => 'passed', 'regression_exit' => $testExit]).PHP_EOL;
} finally {
    DB::disconnect('mysql');
    $pdo->exec('DROP DATABASE `'.$target.'`');
    echo 'Dropped disposable database '.$target.PHP_EOL;
}
exit($testExit);
