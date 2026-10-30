<?php

// Read-only owner-data inventory and hypothetical reconciliation. Never applies anything.
require __DIR__.'/../vendor/autoload.php';
use App\Models\Bookkeeping\Ledger;
use App\Support\Bookkeeping\CreditAccounting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$c = config('database.connections.mysql');
if (! app()->environment('local', 'testing') || ! in_array($c['host'], ['localhost', '127.0.0.1', '::1'], true) || ! empty($c['url']) || config('database.default') !== 'mysql') {
    throw new RuntimeException('Explicitly local MySQL connection required.');
}
DB::connection()->getPdo()->exec('SET TRANSACTION READ ONLY');
DB::beginTransaction();
try {
    $report = ['mode' => 'READ ONLY; proposals require explicit review and authorization', 'policy' => 'fifo_v1', 'ledgers' => []];
    foreach (Ledger::query()->whereIn('kind', ['shared', 'project'])->get() as $ledger) {
        $current = app(CreditAccounting::class)->snapshot($ledger);
        if ($current['payments'] === [] && $current['issues'] === []) {
            continue;
        }
        $report['ledgers'][] = ['ledger_uuid' => $ledger->uuid, 'current' => $current, 'hypothetical_after_review' => app(CreditAccounting::class)->snapshot($ledger, true)];
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    DB::rollBack();
}
